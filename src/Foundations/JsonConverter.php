<?php

namespace ObjectMapper\Foundations;

use JsonException;
use ObjectMapper\Constraints\Converter;

class JsonConverter extends Converter
{
    public function unmarshal(string $text, string $className): object
    {
        $dataArray = json_decode($text, true);
        if (json_last_error()) {
            // 解析发生错误
            throw new JsonException(json_last_error_msg());
        }
        return $this->convert($dataArray, $className);
    }

    public function decode(array $dataArray, string $className): object
    {
        return $this->convert($dataArray, $className);
    }

    public function readValue(array $dataArray, object $instance): void
    {
        $this->convert($dataArray, $instance);
    }
}