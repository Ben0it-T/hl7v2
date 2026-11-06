<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value\Primitive;

use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * SI, sequence ID (HL7 v2.5 section 2.A.69): a non-negative integer.
 *
 * Its maximum length (4) is the HL7 length of the element, checked with the
 * other lengths.
 */
final readonly class SIValidator implements PrimitiveValidator
{
    public function check(string $value): ?string
    {
        return preg_match('/^\d+$/', $value) === 1
            ? null
            : "'$value' is not a non-negative integer.";
    }
}
