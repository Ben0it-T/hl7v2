<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value\Primitive;

use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * TM, time (HL7 v2.5 section 2.A.75): HH[MM[SS[.S[S[S[S]]]]]][+/-ZZZZ],
 * a 24-hour clock time; the time zone is an offset +/-HHMM.
 */
final readonly class TMValidator implements PrimitiveValidator
{
    public function check(string $value): ?string
    {
        if (preg_match('/^(\d{2})(?:(\d{2})(?:(\d{2})(?:\.\d{1,4})?)?)?([+-]\d{4})?$/', $value, $parts) !== 1) {
            return "'$value' is not a time in the format HH[MM[SS[.S[S[S[S]]]]]][+/-ZZZZ].";
        }

        if (!self::isTime($parts[1], $parts[2] ?? '', $parts[3] ?? '')) {
            return "'$value' is not a time of the 24-hour clock.";
        }

        return self::isTimeZone($parts[4] ?? '')
            ? null
            : "'$value' has an invalid time zone offset.";
    }

    /**
     * Check the parts of a time: hour 00 to 23, minute and second 00 to 59.
     *
     * @param string $hour Two digits.
     * @param string $minute Two digits, or empty.
     * @param string $second Two digits, or empty.
     *
     * @return bool
     */
    public static function isTime(string $hour, string $minute, string $second): bool
    {
        return (int) $hour <= 23
            && ($minute === '' || (int) $minute <= 59)
            && ($second === '' || (int) $second <= 59);
    }

    /**
     * Check a time zone offset: +/-HHMM, hour 00 to 23, minute 00 to 59.
     *
     * @param string $offset Sign and four digits, or empty.
     *
     * @return bool
     */
    public static function isTimeZone(string $offset): bool
    {
        return $offset === ''
            || ((int) substr($offset, 1, 2) <= 23 && (int) substr($offset, 3, 2) <= 59);
    }
}
