<?php

namespace ObjectMapper\Constraints;

use ObjectMapper\Annotations\Alias;
use ObjectMapper\Annotations\ArrayList;
use ObjectMapper\Annotations\ObjectMap;
use RuntimeException;

class Skeleton
{
    /**
     * 模板内容
     * @var string[]
     */
    private array $template = [];

    /**
     * Getter内容
     * @var array
     */
    private array $getters = [];

    /**
     * 使用的注解内容
     * @var array
     */
    private array $attributes = [];

    /**
     * 命名空间
     * @var string
     */
    private string $namespace = '';

    /**
     * 类名称
     * @var string
     */
    private string $className = '';

    /**
     * 类型转换
     * @var array|string[]
     */
    private array $convert = [
        'integer' => 'int',
        'double' => 'float'
    ];

    /**
     * 将数组转换为类结构体
     * @param array $dataArray 数组
     * @param string $namespace 命名空间
     * @param string $path 保存路径
     * @return void
     */
    public function build(array $dataArray, string $className, string $namespace, string $path)
    {
        // 生产类的信息
        $this->writeInformation($namespace, $className);
        // 开始读取字段值
        foreach ($dataArray as $key => $value) {
            // 对Key进行处理
            $writeKey = preg_replace_callback('/([-_]+\w)/', function ($group) {
                return strtoupper(trim($group[0], '-_'));
            }, $key);
            // 读取字段类型
            $rawType = gettype($value);
            $types = $this->convert[$rawType] ?? $rawType;
            // 读取名称是否一致
            if ($writeKey !== $key) {
                // 需要导入Alias
                $this->useAttributed(Alias::class, [
                    'name' => $key
                ]);
            }
            if (in_array($types, ['int', 'float', 'string', 'bool'])) {
                // 简单类型
                $this->writeLine(sprintf('    private %s $%s;', $types, $writeKey), '');
                // 设置Getter
                $this->writeGetter($types, $writeKey);
            } elseif (isset($value[0])) {
                // 探测是否为单一类型
                if (!$this->singleType($value)) {
                    // 非单一类型 标记为ArrayList
                    $this->useAttributed(ArrayList::class, []);
                    // 复杂类型
                    $this->writeLine(sprintf('    private array $%s;', $writeKey), '');
                    // 设置Getter
                    $this->writeGetter('array', $writeKey);
                } else {
                    // 标记为单一类型 转换为类类型
                    $types = ucfirst($writeKey);
                    // 复杂类型
                    $this->useAttributed(ObjectMap::class, [
                        'className' => sprintf('%s::class', $types)
                    ]);
                    $this->writeLine(sprintf('    private array $%s;', $writeKey), '');
                    // 设置Getter
                    $this->writeGetter('array', $writeKey);
                    // 写入对应的类内容
                    (new Skeleton())->build($value[0], $types, $namespace, $path);
                }
            } else {
                // 转换为类类型
                $types = ucfirst($writeKey);
                // 复杂类型
                $this->writeLine(sprintf('    private %s $%s;', $types, $writeKey), '');
                // 设置Getter
                $this->writeGetter($types, $writeKey);
                // 写入对应的类内容
                (new Skeleton())->build($value, $types, $namespace, $path);
            }
        }
        // 保存文件
        $this->saveClass($className, $path);
    }

    /**
     * 拼接模板内容
     * @param string[] $content 写入单行的内容
     * @return void
     */
    private function writeLine(string ...$content)
    {
        $this->template = array_merge($this->template, $content);
    }

    /**
     * 拼接注解类到注释中
     * @param string $className 注解名称
     * @param array $options 注解参数
     * @return void
     */
    private function useAttributed(string $className, array $options)
    {
        $this->attributes = array_unique(array_merge($this->attributes, [$className]));
        $params = [];
        foreach ($options as $option => $value) {
            if (stristr($value, '::')) {
                $params[] = sprintf('%s: %s', $option, $value);
                $this->attributes = array_unique(array_merge($this->attributes, [
                    preg_replace('%\\{2,}%', '\\', sprintf('%s\\%s', $this->namespace, substr($value, 0, strpos($value, ':'))))
                ]));
            } else {
                $params[] = sprintf('%s: \'%s\'', $option, $value);
            }
        }
        $chunks = explode('\\', $className);
        if (count($options)) {
            $this->writeLine(sprintf('    #[%s(%s)]', array_pop($chunks), implode(', ', $params)));
        } else {
            $this->writeLine(sprintf('    #[%s]', array_pop($chunks)));
        }
    }

    private function saveClass(string $className, string $path)
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new RuntimeException('尝试保存的路径错误');
        }
        // 插入头部必要信息
        $meta = array_merge([
            '<?php', '', sprintf('namespace %s;', $this->namespace), '',
        ], array_map(function ($item) {
            return sprintf('use %s;', $item);
        }, $this->attributes), [
            '',
            sprintf('class %s', $this->className),
            '{',
            ''
        ]);
        // 合并Getters
        $this->template = array_merge($this->template, $this->getters, ['}']);
        $saveName = preg_replace(sprintf('#%s{2,}#', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR, sprintf('%s%s%s.php', $path, DIRECTORY_SEPARATOR, $className));
        file_put_contents($saveName, implode("\r\n", $meta) . implode("\r\n", $this->template));
    }

    private function writeGetter(string $types, mixed $writeKey)
    {
        $this->getters = array_merge($this->getters, [
            sprintf('    public function get%s(): %s', ucfirst($writeKey), $types),
            '    {',
            sprintf('        return $this->%s;', $writeKey),
            '    }',
            ''
        ]);
    }

    private function writeInformation(string $namespace, string $className)
    {
        $this->namespace = $namespace;
        $this->className = $className;
    }

    private function singleType(mixed $value): bool
    {
        if (!is_array($value[0])) {
            return false;
        }
        $firstKey = array_keys($value[0]);
        for ($i = 1; $i < count($value); $i++) {
            if (count(array_diff($firstKey, array_keys($value[$i])))) {
                return false;
            }
        }
        return true;
    }
}