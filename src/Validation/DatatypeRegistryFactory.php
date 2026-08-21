<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Validation\DatatypeValidatorRegistry;
use HL7v2\Validation\Datatype\DTValidator;
use HL7v2\Validation\Datatype\DTMValidator;
use HL7v2\Validation\Datatype\TMValidator;

final class DatatypeRegistryFactory
{
    public static function create(): DatatypeValidatorRegistry
    {
        $registry = new DatatypeValidatorRegistry();

        $registry->register(new DTValidator());
        $registry->register(new DTMValidator());
        $registry->register(new TMValidator());

        return $registry;
    }
}
