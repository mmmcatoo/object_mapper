<?php

namespace ObjectMapper\Constraints;

use JsonException;
use ObjectMapper\Annotations\Alias;
use ObjectMapper\Annotations\DefaultValue;
use ObjectMapper\Annotations\Formatter;
use ObjectMapper\Annotations\ObjectMap;
use ObjectMapper\Model\ValueTypes;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use ReflectionType;
use RuntimeException;

abstract class Converter
{
    /**
     * 原始数组对象
     * @var array|null
     */
    private static ?array $cache = null;

    /**
     * 将字符串绑定到对应的类中
     * @param string $text 要解析的原始格式
     * @param string $className 类的名称
     * @return object 实例且赋值之后的类
     * @throws JsonException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public abstract function unmarshal(string $text, string $className): object;

    /**
     * @param array $dataArray 原始数据
     * @param string $className 类的名称
     * @return object
     * @throws ReflectionException
     */
    public abstract function decode(array $dataArray, string $className): object;

    /**
     * @param array $dataArray 原始数据
     * @param object $instance 类对象
     * @return void
     * @throws ReflectionException
     */
    public abstract function readValue(array $dataArray, object $instance): void;

    /**
     * 将数组转换为对象
     * @param array $dataArray 数组类型的数据集合
     * @param string|object $className 类的名称
     * @return object 实例且赋值之后的类
     * @throws ReflectionException
     */
    protected function convert(array $dataArray, string|object $className): object
    {
        // 设置原始内容
        if (is_null(self::$cache)) {
            self::$cache = $dataArray;
        }
        // 使用反射读取属性值
        $instance = new ReflectionClass(trim($className, '?'));
        // 创建实例对象
        $object = $instance->newInstanceWithoutConstructor();
        // 读取属性列表
        $properties = $instance->getProperties();
        // 循环处理属性值
        foreach ($properties as $property) {
            // 先读取名称
            $propertyName = $this->getName($property);
            // 在读取类型
            $types = $property->getType();
            // 读取值是否存在
            $values = $this->getValues($property, $dataArray, $propertyName);
            // 尝试赋值操作
            $this->tryAssignValue($object, $types, $values, $property);
        }

        return $object;
    }

    /**
     * 获取类的属性名称或是别称
     * @param ReflectionProperty $property 反射的属性方法
     * @return string 属性名称
     */
    protected function getName(ReflectionProperty $property): string
    {
        try {
            /** @var Alias $object */
            $object = $this->getAttribute($property, Alias::class);
            return $object->getName();
        } catch (RuntimeException $e) {
            // 返回标准名称
            return $property->getName();
        }
    }

    /**
     * 读取存在的值或是设定的默认值
     * @param ReflectionProperty $property 反射的属性方法
     * @param array $jsonArrayValue 原始数组类型
     * @param string $propertyName 属性名称
     * @return ValueTypes 值类型对象
     */
    protected function getValues(ReflectionProperty $property, array $jsonArrayValue, string $propertyName): ValueTypes
    {
        // 判断是否存在传递过来的值
        if (isset($jsonArrayValue[$propertyName])) {
            return new ValueTypes(true, $jsonArrayValue[$propertyName]);
        }
        // 查看是否存在DefaultValue
        try {
            /** @var DefaultValue $object */
            $object = $this->getAttribute($property, DefaultValue::class);
            // 使用默认值填充
            return new ValueTypes(true, $object->getValues());
        } catch (RuntimeException $e) {
            // 不存在默认值
            return new ValueTypes(false, null);
        }
    }

    /**
     * @param object $object 实列对象
     * @param ReflectionType|null $types 字段类型
     * @param ValueTypes $values 值类型对象
     * @param ReflectionProperty $property 反射的属性方法
     * @return void 无
     * @throws ReflectionException
     */
    protected function tryAssignValue(object $object, ?ReflectionType $types, ValueTypes $values, ReflectionProperty $property)
    {
        $typesName = $types->getName();
        // 定义简单类型
        $simpleTypes = ['int', 'float', 'string', 'bool'];
        if (in_array($typesName, $simpleTypes)) {
            // 简单类型直接赋值
            $this->assignValue($object, $property, $this->castValue($property, $values->getValue(), $types));
        } elseif ($typesName === 'array') {
            // 主要判断是不是ObjectMap
            try {
                $ruleName = $this->getAttribute($property, ObjectMap::class)->getClassName();
                // 按照记录读取类型
                if ($values->isHasValue()) {
                    // 转换值类型
                    $cache = array_map(function ($v) use ($ruleName) {
                        try {
                            return $this->convert((array) $v, $ruleName);
                        } catch (\Exception $e) {
                            return null;
                        }
                    }, (array) $values->getValue());
                    // 设置相关的值
                    $this->assignValue($object, $property, $cache);
                } else {
                    // 空的设置为空数组
                    $this->assignValue($object, $property, []);
                }
            } catch (RuntimeException $e) {
                // 直接赋值即可
                $this->assignValue($object, $property, $values->isHasValue() ? $values->getValue() : []);
            }
        } else {
            if (!$values->isHasValue() || is_null($values->getValue())) {
                // 判断是否可以用NULL
                if ($types->allowsNull()) {
                    $this->assignValue($object, $property, null);
                } else {
                    // 使用自己的类型填充
                    $this->assignValue($object, $property, new $typesName);
                }
            } else {
                $this->assignValue($object, $property, $this->convert((array)$values->getValue(), $types));
            }
        }
    }

    /**
     * 读取注解值并且在存在的时候返回其实例
     * @param ReflectionProperty $property 反射的属性对象
     * @param string $attributeClass 注解类名称
     * @return object 注解实例
     */
    private function getAttribute(ReflectionProperty $property, string $attributeClass): object
    {
        // 是否有特殊注解
        $hasAlias = $property->getAttributes($attributeClass);
        if (count($hasAlias) > 0) {
            // 返回注解实例
            return $hasAlias[0]->newInstance();
        }
        // 注解不存在
        throw new RuntimeException();
    }

    /**
     * 类型转换
     * @param ReflectionProperty $property
     * @param mixed $values 原始值类型
     * @param string $types 目标值类型
     * @return string|int|bool|float|null 目标值
     */
    private function castValue(ReflectionProperty $property, mixed $values, string $types): string|int|bool|null|float
    {
        try {
            // 优先使用自定义转换器
            /** @var Formatter $formatter */
            $formatter = $this->getAttribute($property, Formatter::class);
            return $formatter->getFormatter()->process($values, $property->getName(), self::$cache ?? []);
        } catch (RuntimeException $e) {
            return match ($types) {
                'string' => strval($values),
                'int' => intval($values),
                'float' => floatval($values),
                'bool' => boolval($values),
                default => null
            };
        }
    }

    /**
     * 将值赋予对象
     * @param object $object 实列对象
     * @param ReflectionProperty $property 属性反射对象
     * @param float|bool|int|string|null|array|object $castValue 赋值对象
     * @return void
     */
    private function assignValue(object $object, ReflectionProperty $property, float|bool|int|string|null|array|object $castValue)
    {
        $method = sprintf('set%s', ucfirst($property->getName()));
        if ($property->isPublic()) {
            // 属性可访问 直接赋值
            $property->setValue($object, $castValue);
        } elseif (method_exists($object, $method)) {
            // 存在赋值函数
            call_user_func([$object, $method], $castValue);
        } else {
            // 先修改访问级别
            $property->setAccessible(true);
            // 然后赋值
            $property->setValue($object, $castValue);
        }
    }
}