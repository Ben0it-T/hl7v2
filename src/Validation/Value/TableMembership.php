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
 * A pattern is not a code: "HL7nnnn" itself is not in the table.
 *
 * Tables 0359 (diagnosis priority) and 0418 (procedure priority) end with an
 * open rank: "2 …", "For ranked secondary diagnoses" (HL7 v2.4 and v2.5,
 * section 6.5.2.15 and 6.5.4.14), "2 and higher" (HL7 v2.3.1). The code "2 ...",
 * "2 …" or "2 and higher" stands for every whole number from 2; it is not
 * a code itself.
 *
 * The patterns of the other tables (0070, 0203, 0335) are compared as written.
 */
final readonly class TableMembership
{
    /**
     * Table whose patterns are read.
     */
    private const CODING_SYSTEM_TABLE = '0396';

    /**
     * Tables whose last code is an open rank.
     */
    private const RANK_TABLES = ['0359', '0418'];

    /**
     * Open rank: a whole number followed by "...", "…" or "and higher".
     */
    private const OPEN_RANK_PATTERN = '/^(\d+) ?(?:\.\.\.|\x{2026}|and higher)$/u';

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
     * Check whether a coding system is a local one (table 0396): L, or 99
     * followed by letters or digits (99zzz).
     *
     * @param string $codingSystem Coding system, as received.
     *
     * @return bool
     */
    public function isLocalCodingSystem(string $codingSystem): bool
    {
        return $codingSystem === 'L' || preg_match(self::CODING_SYSTEM_PATTERNS['99zzz']['pattern'], $codingSystem) === 1;
    }

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
        if (in_array($table->id, self::RANK_TABLES, true)) {
            return $this->containsRank($table, $value);
        }

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

    /**
     * Check whether a value belongs to a table whose codes may end with an
     * open rank: the value is one of its codes, or a whole number from the
     * first rank of an open rank (the open rank itself is not a code).
     *
     * @param TableDefinition $table A table with codes.
     * @param string $value Decoded value.
     *
     * @return bool
     */
    private function containsRank(TableDefinition $table, string $value): bool
    {
        foreach ($table->codes as $code) {
            if (preg_match(self::OPEN_RANK_PATTERN, $code, $matches) !== 1) {
                if ($code === $value) {
                    return true;
                }
                continue;
            }
            if (preg_match('/^\d+$/', $value) === 1 && (int) $value >= (int) $matches[1]) {
                return true;
            }
        }

        return false;
    }
}
