<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class DTMValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'DTM';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition: Specifies a point in time using a 24-hour clock notation.
        // Maximum Length: 24
        // Format: YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ]
        //                                                  HHMM

        if (!preg_match('/^\d{4}(\d{2}(\d{2}(\d{2}(\d{2}(\d{2}(\.\d{1,4})?)?)?)?)?)?([+-]\d{4})?$/',$value)) {
            // `YYYY`, `YYYYMM`, `YYYYMMDD` ... `YYYYMMDDHHMMSS.SSSS+ZZZZ`
            $this->errorMessage ='invalid DTM format.';

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
            $this->errorMessage = "invalid DTM value '$value'.";
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
        $format = match (strlen($parts['datetime'])) {
            4  => 'Y',
            6  => 'Ym',
            8  => 'Ymd',
            10 => 'YmdH',
            12 => 'YmdHi',
            14 => 'YmdHis',
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
        $normalized = $parts['datetime'];

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
     *     datetime: string,
     *     fraction: string,
     *     timezone: string
     * }
     */
    private function parse(string $value): array
    {
        $pattern =
            '/^
            (?<datetime>\d{4,14})
            (?<fraction>\.\d{1,4})?
            (?<timezone>[+-]\d{4})?
            $/x';

        preg_match($pattern, $value, $matches);

        return [
            'datetime' => $matches['datetime'] ?? '',
            'fraction' => $matches['fraction'] ?? '',
            'timezone' => $matches['timezone'] ?? '',
        ];
    }
}
