<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class DTValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'DT';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition: Specifies the century and year with optional precision to month and day.
        // Maximum Length: 8
        // Format: YYYY[MM[DD]]

        if (!preg_match('/^\d{4}(\d{2}(\d{2})?)?$/',$value)) {
            // `YYYY`, `YYYYMM`, `YYYYMMDD`
            $this->errorMessage ='invalid DT format.';

            return false;
        }

        if (strlen($value) >= 6) {
            $month = (int) substr($value, 4, 2);

            if ($month < 1 || $month > 12) {
                $this->errorMessage = "invalid DT value '$value'.";

                return false;
            }
        }

        if (strlen($value) === 8) {
            $year  = (int) substr($value, 0, 4);
            $month = (int) substr($value, 4, 2);
            $day   = (int) substr($value, 6, 2);

            if (!checkdate($month, $day, $year)) {
                $this->errorMessage = "invalid DT value '$value'.";
                return false;
            }
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
