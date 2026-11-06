<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value\Primitive;

use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * NM, numeric (HL7 v2.5 section 2.A.47): an optional leading sign (+ or -),
 * the digits and an optional decimal point. No other character is allowed.
 *
 * At least one digit is required; the decimal point may come first or last
 * (".5", "1."): the definition does not require digits on both sides.
 */
final readonly class NMValidator implements PrimitiveValidator
{
    public function check(string $value): ?string
    {
        return preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value) === 1
            ? null
            : "'$value' is not a number (optional sign, digits, optional decimal point).";
    }
}
