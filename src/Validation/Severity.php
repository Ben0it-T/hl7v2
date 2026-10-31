<?php
declare(strict_types=1);

namespace HL7v2\Validation;

/**
 * Severity of a finding. Only errors make a message invalid.
 *
 * The values are the ones used in rules.json.
 */
enum Severity: string
{
    case Error   = 'Error';
    case Warning = 'Warning';
    case Info    = 'Info';
}
