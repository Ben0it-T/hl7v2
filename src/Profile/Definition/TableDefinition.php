<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\TableType;

/**
 * Definition of an HL7 table (hl7tables.json): its id, its type and its codes.
 *
 * A table without codes is a table whose values are not given by the
 * profile: a value cannot be checked against it.
 */
final readonly class TableDefinition
{
    /**
     * @param string $id Table id (0001, IHE-FRANCE-ZBE-4...).
     * @param list<string> $codes Codes, as written in the table.
     */
    public function __construct(
        public string $id,
        public string $name,
        public TableType $type,
        public array $codes
    ) {
        //
    }
}
