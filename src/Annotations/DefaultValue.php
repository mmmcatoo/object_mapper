<?php

namespace ObjectMapper\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    private mixed $values;

    /**
     * @param mixed $values
     */
    public function __construct(mixed $values)
    {
        $this->values = $values;
    }

    /**
     * @return mixed
     */
    public function getValues(): mixed
    {
        return $this->values;
    }
}