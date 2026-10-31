<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

/**
 * Why a segment of the message found no place in the message structure.
 */
enum UnplacedReason: string
{
    /** Unknown to the message structure (Q214): Not defined segment. */
    case NotDefined = 'notDefined';

    /** Known to the message structure, but not expected here: Not expected segment. */
    case NotExpected = 'notExpected';
}
