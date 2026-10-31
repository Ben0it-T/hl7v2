<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Severity;

/**
 * Validation rules of a profile (rules.json): the severity of each finding
 * type, the closed tables and the tables accepting local codes.
 *
 * The validator applies no rule of its own: every rule comes from this file,
 * which must be complete.
 *
 * Example:
 *     {
 *         "source": "HL7 v2: usage, cardinality, data types, lengths and tables",
 *         "comment": "...",
 *         "severities": { "requiredAbsent": "Error", ..., "parseIssue": "Error" },
 *         "closedTables": [],
 *         "localCodeTables": ["0003", "0076", "0354"]
 *     }
 */
final readonly class Rules
{
    private const KEYS = ['source', 'comment', 'severities', 'closedTables', 'localCodeTables'];

    /**
     * @param array<string, Severity> $severities Severity by finding type value.
     * @param list<string> $closedTables
     * @param list<string> $localCodeTables
     */
    private function __construct(
        private array $severities,
        private array $closedTables,
        private array $localCodeTables,
        private string $source,
        private string $comment
    ) {
        //
    }

    /**
     * Create the rules from the content of a rules.json file.
     *
     * @param array<mixed, mixed> $data Decoded rules.json.
     * @param string $file File name, for the error messages.
     *
     * @return Rules
     *
     * @throws HL7Exception On an unknown key, a missing or unknown finding
     *                      type, an unknown severity, or an invalid table list.
     */
    public static function fromArray(array $data, string $file): self
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), self::KEYS);
        if ($unknown !== []) {
            throw new HL7Exception(sprintf('%s: unknown key(s) %s.', $file, implode(', ', $unknown)));
        }

        $declared = $data['severities'] ?? null;
        if (!is_array($declared)) {
            throw new HL7Exception("$file: 'severities' is required.");
        }

        $severities = [];
        foreach (FindingType::cases() as $type) {
            if (!array_key_exists($type->value, $declared)) {
                throw new HL7Exception("$file: severity missing for '{$type->value}'.");
            }
            $severity = is_string($declared[$type->value]) ? Severity::tryFrom($declared[$type->value]) : null;
            if ($severity === null) {
                throw new HL7Exception("$file: unknown severity for '{$type->value}' (Error, Warning or Info).");
            }
            $severities[$type->value] = $severity;
        }

        $extra = array_diff(array_map('strval', array_keys($declared)), array_keys($severities));
        if ($extra !== []) {
            throw new HL7Exception(sprintf('%s: unknown finding type(s) %s.', $file, implode(', ', $extra)));
        }

        return new self(
            $severities,
            self::tableList($data, 'closedTables', $file),
            self::tableList($data, 'localCodeTables', $file),
            is_string($data['source'] ?? null) ? $data['source'] : '',
            is_string($data['comment'] ?? null) ? $data['comment'] : ''
        );
    }

    /**
     * Get the severity of a finding type.
     *
     * @param FindingType $type
     *
     * @return Severity
     */
    public function severity(FindingType $type): Severity
    {
        return $this->severities[$type->value];
    }

    /**
     * Check whether a table is closed: a value outside it is a tableClosed finding.
     *
     * @param string $table Table id (0001, IHE-FRANCE-ZBE-4...).
     *
     * @return bool
     */
    public function isClosedTable(string $table): bool
    {
        return in_array($table, $this->closedTables, true);
    }

    /**
     * Check whether a table accepts local codes (Z...).
     *
     * @param string $table Table id (0003, 0076, 0354...).
     *
     * @return bool
     */
    public function acceptsLocalCodes(string $table): bool
    {
        return in_array($table, $this->localCodeTables, true);
    }

    /**
     * Get the source of the rules.
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get the comment of the rules.
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @return list<string>
     *
     * @throws HL7Exception
     */
    private static function tableList(array $data, string $key, string $file): array
    {
        $list = $data[$key] ?? null;
        if (!is_array($list) || !array_is_list($list)) {
            throw new HL7Exception("$file: '$key' must be a list of table ids.");
        }
        foreach ($list as $table) {
            if (!is_string($table) || $table === '') {
                throw new HL7Exception("$file: '$key' must be a list of table ids.");
            }
        }

        return $list;
    }
}
