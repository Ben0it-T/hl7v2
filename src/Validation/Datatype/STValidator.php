<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class STValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'ST';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // Definition: String data is left justified with trailing blanks optional.
        //             Any displayable (printable) ASCII characters (hexadecimal values between 20 and 7E, inclusive, or ASCII decimal values between 32 and 126),
        //             except the defined escape characters and defined delimiter characters.

        // Alternate character set note: ST - string data may also be used to express other character sets.

        // Maximum Length: 199

        // TODO:
        // Validate characters according to MSH-18 Character Set.

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

}
