<?php
declare(strict_types=1);

namespace HL7v2\Profile;

class HL7Tables
{
    /**
     * HL7 table  definitions loaded from JSON.
     *
     * @var array<mixed,mixed>
     */
    private array $tables;

    /**
     * Create HL7 tables from raw JSON definition.
     *
     * @param array<mixed,mixed> $tables
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    /**
     * Get HL7 table definitions.
     *
     * @return array<mixed,mixed>
     */
    public function getTables(): array
    {
        return $this->tables;
    }
}
