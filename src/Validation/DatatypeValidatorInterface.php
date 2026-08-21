<?php
declare(strict_types=1);

namespace HL7v2\Validation;

interface DatatypeValidatorInterface
{
    public function getDatatype(): string;

    public function validate(string $value): bool;

    public function getErrorMessage(): string;
}
