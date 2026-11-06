<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

/**
 * Type of an HL7 table: its values are defined by HL7, or only suggested by
 * HL7 and defined by the user (the site, or a profile).
 */
enum TableType: string
{
    case HL7 = 'HL7';
    case User = 'User';
}
