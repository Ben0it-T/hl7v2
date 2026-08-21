<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class SIValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'SI';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition: A non-negative integer in the form of a NM field.
        //             The uses of this data type are defined in the chapters defining the segments and messages in which it appears.
        // Maximum Length: 4. This allows for a number between 0 and 9999 to be specified.

        if (!preg_match('/^\d{1,4}$/',$value)) {
            $this->errorMessage = "invalid SI value '$value'.";

            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

}
