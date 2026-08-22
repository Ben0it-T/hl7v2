<?php
declare(strict_types=1);

namespace HL7v2\Validation\Datatype;

use HL7v2\Validation\DatatypeValidatorInterface;

final class FTValidator implements DatatypeValidatorInterface
{
    private string $errorMessage = '';

    public function getDatatype(): string
    {
        return 'FT';
    }

    public function validate(string $value): bool
    {
        $this->errorMessage = '';

        // TODO:
        // Validate characters according to MSH-18 Character Set.

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

}
