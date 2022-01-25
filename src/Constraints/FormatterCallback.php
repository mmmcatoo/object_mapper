<?php

namespace ObjectMapper\Constraints;

interface FormatterCallback
{
    /**
     * 转换相关的内容
     * @param mixed $value 原始值类型
     * @param string $fieldName 字段名称
     * @param array $original 原始数组内容
     * @return mixed
     */
    public function process(mixed $value, string $fieldName, array $original): mixed;
}