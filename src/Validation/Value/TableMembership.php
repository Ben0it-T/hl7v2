<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

use HL7v2\Profile\Definition\TableDefinition;

/**
 * Membership of a value in an HL7 table: the value is one of its codes,
 * compared as written (codes are case-sensitive: "f" is not the code "F").
 *
 * Table 0396 (coding system) holds patterns besides its codes. Source:
 * HL7 v2.5 section 2.17.4 (table 0396) and section 2.A.6.3 (CE.3):
 * - "HL7nnnn", "where nnnn is the HL7 table number": HL7 and 4 digits;
 * - "ISOnnnn", "where nnnn is the ISO table number": ISO and at least one
 *   digit (ISO639 is the language table);
 * - "IBTnnnn", "the variable suffix (nnnn) identifies a specific table
 *   within ISBT 128": IBT and at least one digit;
 * - "99zzz", "where z is an alphanumeric character": 99 and at least one
 *   letter or digit;
 * - "99zzz or L": the code L, and the pattern 99zzz.
 * A pattern is not a code: "HL7nnnn" itself is not in the table. The patterns
 * of the other tables (0070, 0203, 0335) are compared as written.
 */
final readonly class TableMembership
{
    /**
     * Table whose patterns are read.
     */
    private const CODING_SYSTEM_TABLE = '0396';

    /**
     * Patterns of table 0396: the regular expression and the literal codes
     * that each entry stands for.
     *
     * @var array<string, array{pattern: string, codes: list<string>}>
     */
    private const CODING_SYSTEM_PATTERNS = [
        'HL7nnnn'    => ['pattern' => '/^HL7\d{4}$/', 'codes' => []],
        'ISOnnnn'    => ['pattern' => '/^ISO\d+$/', 'codes' => []],
        'IBTnnnn'    => ['pattern' => '/^IBT\d+$/', 'codes' => []],
        '99zzz'      => ['pattern' => '/^99[A-Za-z0-9]+$/', 'codes' => []],
        '99zzz or L' => ['pattern' => '/^99[A-Za-z0-9]+$/', 'codes' => ['L']],
    ];

    /**
     * Check whether a value belongs to a table.
     *
     * @param TableDefinition $table A table with codes.
     * @param string $value Decoded value.
     *
     * @return bool
     */
    public function contains(TableDefinition $table, string $value): bool
    {
        if ($table->id !== self::CODING_SYSTEM_TABLE) {
            return in_array($value, $table->codes, true);
        }

        foreach ($table->codes as $code) {
            $entry = self::CODING_SYSTEM_PATTERNS[$code] ?? null;
            if ($entry === null) {
                if ($code === $value) {
                    return true;
                }
                continue;
            }
            if (in_array($value, $entry['codes'], true) || preg_match($entry['pattern'], $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
