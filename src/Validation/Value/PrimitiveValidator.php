<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

/**
 * Validator of a primitive data type, on the Decoded value of a leaf.
 *
 * A validator keeps no state: the same instance checks every value.
 */
interface PrimitiveValidator
{
    /**
     * Check a value against the data type.
     *
     * @param string $value Decoded value, not empty.
     *
     * @return string|null Null when the value is valid; otherwise why it is not.
     */
    public function check(string $value): ?string;
}
