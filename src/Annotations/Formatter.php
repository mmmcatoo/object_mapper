<?php

namespace ObjectMapper\Annotations;

use Attribute;
use ObjectMapper\Constraints\FormatterCallback;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Formatter
{
    private FormatterCallback $formatter;

    /**
     * @param string $formatter
     */
    public function __construct(string $formatter)
    {
        $this->formatter = new $formatter;
    }

    /**
     * @return FormatterCallback
     */
    public function getFormatter(): FormatterCallback
    {
        return $this->formatter;
    }
}