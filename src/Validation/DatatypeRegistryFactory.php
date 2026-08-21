<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Validation\DatatypeValidatorRegistry;
use HL7v2\Validation\Datatype\DTValidator;
use HL7v2\Validation\Datatype\DTMValidator;

final class DatatypeRegistryFactory
{
    public static function create(): DatatypeValidatorRegistry
    {
        $registry = new DatatypeValidatorRegistry();

        $registry->register(new DTValidator());
        $registry->register(new DTMValidator());

        return $registry;
    }
}
