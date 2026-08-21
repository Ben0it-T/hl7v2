<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class TMValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'TM';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition:  Specifies the hour of the day with optional minutes, seconds, fraction of second using a 24-hour clock notation and time zone.
        // Maximum Length: 16
        // Format: HH[MM[SS[.S[S[S[S]]]]]][+/-ZZZZ]
        //                                    HHMM

        if (!preg_match('/^\d{2}(\d{2}(\d{2}(\.\d{1,4})?)?)?([+-]\d{4})?$/',$value)) {
            // `HH`, `HHMM`, `HHMMSS` ... `HHMMSS.SSSS+ZZZZ`
            $this->errorMessage ='invalid TM format.';

            return false;
        }

        $format = $this->getFormat($value);
        $normalizedValue = $this->normalizeValue($value);
        $hasErrors = false;

        $date = \DateTimeImmutable::createFromFormat(
            '!' . $format,
            $normalizedValue
        );

        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (is_array($dateErrors)) {
           $hasErrors = $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0;
        }

        $parts = $this->parse($value);
        if ($parts['timezone'] !== '' && !$this->validateTimezoneOffset($parts['timezone'])) {
            $hasErrors = true;
        }

        if ($date === false || $hasErrors) {
            $this->errorMessage = "invalid TM value '$value'.";
            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    private function getFormat(string $value): string
    {
        $parts = $this->parse($value);
        $format = match (strlen($parts['time'])) {
            2  => 'H',
            4  => 'Hi',
            6  => 'His',
            default => '',
        };

        if ($parts['fraction'] !== '') {
            $format .= '.u';
        }

        if ($parts['timezone'] !== '') {
            $format .= 'O';
        }

        return $format;
    }

    private function normalizeValue(string $value): string
    {
        $parts = $this->parse($value);
        $normalized = $parts['time'];

        if ($parts['fraction'] !== '') {
            $parts['fraction'] = ltrim($parts['fraction'], '.');

            $normalized .= '.' . str_pad($parts['fraction'], 6, '0');
        }

        if ($parts['timezone'] !== '') {
            $normalized .= $parts['timezone'];
        }

        return $normalized;
    }

    private function validateTimezoneOffset(string $offset): bool
    {
        $hours = (int) substr($offset, 1, 2);
        $minutes = (int) substr($offset, 3, 2);

        return $hours <= 23 && $minutes <= 59;
    }

    /**
     * @return array{
     *     time: string,
     *     fraction: string,
     *     timezone: string
     * }
     */
    private function parse(string $value): array
    {
        $pattern =
            '/^
            (?<time>\d{2,6})
            (?<fraction>\.\d{1,4})?
            (?<timezone>[+-]\d{4})?
            $/x';

        preg_match($pattern, $value, $matches);

        return [
            'time'     => $matches['time'] ?? '',
            'fraction' => $matches['fraction'] ?? '',
            'timezone' => $matches['timezone'] ?? '',
        ];
    }

}
