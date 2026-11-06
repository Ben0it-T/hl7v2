<?php
declare(strict_types=1);

namespace HL7v2\Validation\Content;

/**
 * The table a leaf (a primitive value) is checked against, and where it
 * comes from:
 * - no table and no coding system: nothing to check;
 * - a table, without coding system: the table of the element in the profile;
 * - a table and a coding system: the table designated by the coding system
 *   of a CE (CE.3 for CE.1, CE.6 for CE.4);
 * - a coding system without table: the coding system is not a table of the
 *   profile (LN, ISO 3166 alpha-3...), the value is not checked.
 */
final readonly class LeafTable
{
    /**
     * @param string|null $id Table id.
     * @param string|null $codingSystem Coding system of the CE, as received.
     */
    public function __construct(
        public ?string $id,
        public ?string $codingSystem = null
    ) {
        //
    }

    /**
     * No table: the leaf is not checked against a table.
     *
     * @return LeafTable
     */
    public static function none(): self
    {
        return new self(null);
    }
}
