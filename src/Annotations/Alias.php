<?php

namespace ObjectMapper\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Alias
{
    /**
     * 参考值
     * @var string
     */
    private string $name;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}