<?php

namespace ObjectMapper\Model;

class ValueTypes
{
    private bool $hasValue;

    private mixed $value;

    /**
     * @param bool $hasValue
     * @param mixed $value
     */
    public function __construct(bool $hasValue, mixed $value)
    {
        $this->hasValue = $hasValue;
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isHasValue(): bool
    {
        return $this->hasValue;
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}