<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\EscapeSequences;

/**
 * Sub-component, the smallest element of a message.
 *
 * Keeps the Raw value as received, and exposes the Decoded value
 * (escape sequences resolved, see EscapeSequences).
 */
class SubComponent
{
    private string $value;
    private ?EscapeSequences $escapeSequences;

    /**
     * @param string $value Raw value.
     * @param EscapeSequences|null $escapeSequences Escape sequences of the
     *        message; null when the value must not be decoded (MSH-1, MSH-2).
     */
    public function __construct(string $value, ?EscapeSequences $escapeSequences = null)
    {
        $this->value = $value;
        $this->escapeSequences = $escapeSequences;
    }

    /**
     * Get sub-component value (Raw value).
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the Raw value, as received.
     *
     * @return string
     */
    public function getRawValue(): string
    {
        return $this->value;
    }

    /**
     * Get the Decoded value: escape sequences resolved.
     *
     * @return string
     */
    public function getDecodedValue(): string
    {
        return $this->escapeSequences?->decode($this->value) ?? $this->value;
    }
}
