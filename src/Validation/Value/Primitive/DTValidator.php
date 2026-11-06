<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value\Primitive;

use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * DT, date (HL7 v2.5 section 2.A.21): YYYY[MM[DD]], a date of the calendar.
 */
final readonly class DTValidator implements PrimitiveValidator
{
    public function check(string $value): ?string
    {
        if (preg_match('/^(\d{4})(?:(\d{2})(\d{2})?)?$/', $value, $parts) !== 1) {
            return "'$value' is not a date in the format YYYY[MM[DD]].";
        }

        return self::isDate($parts[1], $parts[2] ?? '', $parts[3] ?? '')
            ? null
            : "'$value' is not a date of the calendar.";
    }

    /**
     * Check the parts of a date: a month from 01 to 12, a day of that month.
     *
     * @param string $year Four digits.
     * @param string $month Two digits, or empty.
     * @param string $day Two digits, or empty.
     *
     * @return bool
     */
    public static function isDate(string $year, string $month, string $day): bool
    {
        if ($month === '') {
            return true;
        }
        if ((int) $month < 1 || (int) $month > 12) {
            return false;
        }

        return $day === '' || checkdate((int) $month, (int) $day, (int) $year);
    }
}
