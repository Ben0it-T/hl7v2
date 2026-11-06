<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value\Primitive;

use HL7v2\Validation\Value\Primitive\DTValidator;
use HL7v2\Validation\Value\Primitive\TMValidator;
use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * DTM, date/time (HL7 v2.5 section 2.A.22):
 * YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ], a date of the calendar and
 * a 24-hour clock time; the time zone is an offset +/-HHMM.
 */
final readonly class DTMValidator implements PrimitiveValidator
{
    public function check(string $value): ?string
    {
        $pattern = '/^(\d{4})(?:(\d{2})(?:(\d{2})(?:(\d{2})(?:(\d{2})(?:(\d{2})(?:\.\d{1,4})?)?)?)?)?)?([+-]\d{4})?$/';
        if (preg_match($pattern, $value, $parts) !== 1) {
            return "'$value' is not a date/time in the format YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ].";
        }

        if (!DTValidator::isDate($parts[1], $parts[2] ?? '', $parts[3] ?? '')) {
            return "'$value' is not a date of the calendar.";
        }

        $hour = $parts[4] ?? '';
        if ($hour !== '' && !TMValidator::isTime($hour, $parts[5] ?? '', $parts[6] ?? '')) {
            return "'$value' is not a time of the 24-hour clock.";
        }

        return TMValidator::isTimeZone($parts[7] ?? '')
            ? null
            : "'$value' has an invalid time zone offset.";
    }
}
