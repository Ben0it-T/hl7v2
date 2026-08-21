<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Validation\DatatypeValidatorInterface;

final class DatatypeValidatorRegistry
{
    /**
     * @var array<string, DatatypeValidatorInterface>
     */
    private array $validators = [];

    public function register(DatatypeValidatorInterface $validator): void
    {
        $this->validators[strtoupper($validator->getDatatype())] = $validator;
    }

    public function get(string $datatype): ?DatatypeValidatorInterface
    {
        return $this->validators[strtoupper($datatype)] ?? null;
    }
}
