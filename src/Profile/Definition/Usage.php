<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

/**
 * Usage of an element in a profile (HL7 v2 conformance usage codes).
 */
enum Usage: string
{
    case Required = 'R';
    case RequiredOrEmpty = 'RE';
    case Optional = 'O';
    case Conditional = 'C';
    case ConditionalOrEmpty = 'CE';
    case NotSupported = 'X';
    case Backward = 'B';
    case Withdrawn = 'W';
}
