<?php

namespace ObjectMapper\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ObjectMap
{
    private string $className;

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}