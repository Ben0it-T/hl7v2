<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class NMValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'NM';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition: A number represented as a series of ASCII numeric characters consisting of an optional leading sign (+ or -), the digits and an optional decimal point.
        //             In the absence of a sign, the number is assumed to be positive.
        //             If there is no decimal point the number is assumed to be an integer.
        // Maximum Length: 16

        if (!preg_match('/^[+-]?\d+(\.\d+)?$/',$value)) {
            $this->errorMessage = "invalid NM value '$value'.";

            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

}
