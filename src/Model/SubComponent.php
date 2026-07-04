<?php
declare(strict_types=1);

namespace HL7v2\Model;

class SubComponent
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Get sub-component value
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
