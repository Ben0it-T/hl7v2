<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\ComponentDefinition;
use HL7v2\Profile\Definition\DataTypeDefinition;
use HL7v2\Profile\Definition\FieldDefinition;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentField;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\Usage;

/**
 * Reads the JSON files of a profile directory (ADR 0004) into definitions.
 *
 * The format is checked strictly, with the file and the place in it: unknown
 * or missing key, unknown usage, invalid cardinality or length, broken
 * reference, element out of order, group not defined, used twice or never
 * used, unsupported format version. The content itself (R with min 0...)
 * is checked by the pipeline (scripts 05 and 06), not here.
 *
 * @internal Used by ProfileDirectory.
 */
final class DefinitionReader
{
    public const FORMAT_VERSION = '1';

    /**
     * @param array<mixed, mixed> $data Decoded profile.json.
     *
     * @return array{name: string, hl7Version: string, base: string, documents: array<string, string>}
     *
     * @throws HL7Exception
     */
    public static function profileInfo(array $data, string $file): array
    {
        self::checkKeys($data, ['formatVersion', 'hl7Version', 'name', 'base', 'generatedFrom', 'documents'], [], $file);

        $version = self::string($data, 'formatVersion', $file);
        if ($version !== self::FORMAT_VERSION) {
            throw new HL7Exception(sprintf(
                "%s: format version '%s' is not supported (expected '%s').",
                $file,
                $version,
                self::FORMAT_VERSION
            ));
        }

        $documents = [];
        foreach (self::entry($data['documents'], "$file: documents") as $id => $title) {
            if (!is_string($title)) {
                throw new HL7Exception("$file: documents.$id must be a string.");
            }
            $documents[(string) $id] = $title;
        }

        return [
            'name'       => self::string($data, 'name', $file),
            'hl7Version' => self::string($data, 'hl7Version', $file),
            'base'       => self::string($data, 'base', $file),
            'documents'  => $documents,
        ];
    }

    /**
     * @param array<mixed, mixed> $data Decoded messageType.json.
     *
     * @return array<string, array<string, string>> Structure by message code and trigger event.
     *
     * @throws HL7Exception
     */
    public static function messageTypes(array $data, string $file): array
    {
        $messageTypes = [];
        foreach ($data as $code => $events) {
            foreach (self::entry($events, "$file: $code") as $event => $structure) {
                if (!is_string($structure) || $structure === '') {
                    throw new HL7Exception("$file: $code^$event must be a structure name.");
                }
                $messageTypes[(string) $code][(string) $event] = $structure;
            }
        }

        return $messageTypes;
    }

    /**
     * @param array<mixed, mixed> $data Decoded dataTypes.json.
     *
     * @return array<string, DataTypeDefinition>
     *
     * @throws HL7Exception
     */
    public static function dataTypes(array $data, string $file): array
    {
        $dataTypes = [];
        foreach ($data as $name => $entry) {
            $name = (string) $name;
            $entry = self::entry($entry, "$file: $name");

            if ($entry === ['primitive' => true]) {
                $dataTypes[$name] = new DataTypeDefinition($name, []);
                continue;
            }

            self::checkKeys($entry, ['components'], [], "$file: $name");

            $components = [];
            foreach (self::list($entry, 'components', "$file: $name") as $index => $component) {
                $position = $index + 1;
                $id = "$name.$position";
                $where = "$file: $id";
                $component = self::entry($component, $where);

                self::checkKeys($component, ['component', 'dataType', 'usage', 'min', 'max', 'maxLength', 'table', 'longName'], ['source'], $where);
                self::checkId($component, 'component', $id, $where);

                $components[] = new ComponentDefinition(
                    $id,
                    $position,
                    self::string($component, 'dataType', $where),
                    self::usage($component, $where),
                    self::cardinality($component, $where),
                    self::maxLength($component, $where),
                    self::table($component, $where),
                    self::string($component, 'longName', $where),
                    self::source($component, $where)
                );
            }

            $dataTypes[$name] = new DataTypeDefinition($name, $components);
        }

        foreach ($dataTypes as $dataType) {
            foreach ($dataType->components as $component) {
                if (!isset($dataTypes[$component->dataType])) {
                    throw new HL7Exception("$file: {$component->id}: unknown data type '{$component->dataType}'.");
                }
            }
        }

        return $dataTypes;
    }

    /**
     * @param array<mixed, mixed> $data Decoded fields.json.
     * @param array<string, DataTypeDefinition> $dataTypes
     *
     * @return array<string, FieldDefinition>
     *
     * @throws HL7Exception
     */
    public static function fields(array $data, array $dataTypes, string $file): array
    {
        $fields = [];
        foreach ($data as $id => $entry) {
            $id = (string) $id;
            $where = "$file: $id";
            $entry = self::entry($entry, $where);

            self::checkKeys($entry, ['item', 'dataType', 'maxLength', 'table', 'longName', 'chapter'], ['source'], $where);

            $dataType = self::string($entry, 'dataType', $where);
            if (!isset($dataTypes[$dataType])) {
                throw new HL7Exception("$where: unknown data type '$dataType'.");
            }

            $fields[$id] = new FieldDefinition(
                $id,
                self::string($entry, 'item', $where),
                $dataType,
                self::maxLength($entry, $where),
                self::table($entry, $where),
                self::string($entry, 'longName', $where),
                self::string($entry, 'chapter', $where),
                self::source($entry, $where)
            );
        }

        return $fields;
    }

    /**
     * @param array<mixed, mixed> $data Decoded segments.json.
     * @param array<string, FieldDefinition> $fields
     *
     * @return array<string, SegmentDefinition>
     *
     * @throws HL7Exception
     */
    public static function segments(array $data, array $fields, string $file): array
    {
        $segments = [];
        foreach ($data as $name => $entry) {
            $name = (string) $name;
            $entry = self::entry($entry, "$file: $name");

            self::checkKeys($entry, ['longName', 'chapter', 'fields'], ['source'], "$file: $name");

            $segmentFields = [];
            foreach (self::list($entry, 'fields', "$file: $name") as $index => $field) {
                $position = $index + 1;
                $id = "$name.$position";
                $where = "$file: $id";
                $field = self::entry($field, $where);

                self::checkKeys($field, ['field', 'usage', 'min', 'max'], ['source'], $where);
                self::checkId($field, 'field', $id, $where);

                $segmentFields[] = new SegmentField(
                    $position,
                    self::usage($field, $where),
                    self::cardinality($field, $where),
                    $fields[$id] ?? throw new HL7Exception("$where: not defined in fields.json."),
                    self::source($field, $where)
                );
            }

            $segments[$name] = new SegmentDefinition(
                $name,
                self::string($entry, 'longName', "$file: $name"),
                self::string($entry, 'chapter', "$file: $name"),
                $segmentFields,
                self::source($entry, "$file: $name")
            );
        }

        return $segments;
    }

    /**
     * Build the message structure of a structures/<message>.json file.
     *
     * @param array<mixed, mixed> $data Decoded structure file: groups by name,
     *                                  the structure itself first.
     * @param array<string, SegmentDefinition> $segments
     *
     * @throws HL7Exception
     */
    public static function structure(array $data, string $structure, array $segments, string $file): GroupNode
    {
        if (array_key_first($data) !== $structure) {
            throw new HL7Exception("$file: the first group must be the structure '$structure'.");
        }

        $used = [];
        $root = self::group($data, $structure, Usage::Required, new Cardinality(1, 1), null, $segments, $file, $used);

        $unused = array_diff(array_map('strval', array_keys($data)), array_keys($used));
        if ($unused !== []) {
            throw new HL7Exception(sprintf('%s: group(s) never used: %s.', $file, implode(', ', $unused)));
        }

        return $root;
    }

    /**
     * @param array<mixed, mixed> $data
     * @param array<string, SegmentDefinition> $segments
     * @param array<string, true> $used Groups already built: a group is used
     *                                  once, which also rules out cycles.
     *
     * @throws HL7Exception
     */
    private static function group(
        array $data,
        string $name,
        Usage $usage,
        Cardinality $cardinality,
        ?string $source,
        array $segments,
        string $file,
        array &$used
    ): GroupNode {
        $where = "$file: $name";
        if (isset($used[$name])) {
            throw new HL7Exception("$where: group used more than once.");
        }
        $used[$name] = true;

        if (!isset($data[$name])) {
            throw new HL7Exception("$where: group not defined.");
        }
        $entry = self::entry($data[$name], $where);
        self::checkKeys($entry, ['elements'], ['choice'], $where);
        if (array_key_exists('choice', $entry) && $entry['choice'] !== true) {
            throw new HL7Exception("$where: 'choice' must be true when present.");
        }

        $children = [];
        foreach (self::list($entry, 'elements', $where) as $index => $element) {
            $elementWhere = "$where, element " . ($index + 1);
            $element = self::entry($element, $elementWhere);

            if (array_key_exists('group', $element)) {
                self::checkKeys($element, ['group', 'usage', 'min', 'max'], ['source'], $elementWhere);
                $children[] = self::group(
                    $data,
                    self::string($element, 'group', $elementWhere),
                    self::usage($element, $elementWhere),
                    self::cardinality($element, $elementWhere),
                    self::source($element, $elementWhere),
                    $segments,
                    $file,
                    $used
                );
                continue;
            }

            self::checkKeys($element, ['segment', 'usage', 'min', 'max'], ['source', 'fields'], $elementWhere);

            $segmentName = self::string($element, 'segment', $elementWhere);
            $definition = $segments[$segmentName]
                ?? throw new HL7Exception("$elementWhere: segment '$segmentName' not defined in segments.json.");
            if (array_key_exists('fields', $element)) {
                $definition = self::overrideFields($definition, $element['fields'], "$where/$segmentName");
            }

            $children[] = new SegmentNode(
                self::usage($element, $elementWhere),
                self::cardinality($element, $elementWhere),
                $definition,
                self::source($element, $elementWhere)
            );
        }

        return new GroupNode($name, $usage, $cardinality, $children, array_key_exists('choice', $entry), $source);
    }

    /**
     * Apply the field overrides of a structure element (usage, cardinality
     * and their source) to a segment definition.
     *
     * @throws HL7Exception
     */
    private static function overrideFields(SegmentDefinition $definition, mixed $overrides, string $where): SegmentDefinition
    {
        if (!is_array($overrides) || $overrides === [] || array_is_list($overrides)) {
            throw new HL7Exception("$where: 'fields' must be an object of field overrides.");
        }

        $prefix = $definition->name . '.';
        $byPosition = [];
        foreach ($overrides as $id => $override) {
            $id = (string) $id;
            $overrideWhere = "$where: $id";

            $position = str_starts_with($id, $prefix) ? substr($id, strlen($prefix)) : '';
            $field = ctype_digit($position) ? $definition->field((int) $position) : null;
            if ($field === null) {
                throw new HL7Exception("$overrideWhere: not a field of segment {$definition->name}.");
            }

            $override = self::entry($override, $overrideWhere);
            self::checkKeys($override, ['usage', 'min', 'max', 'source'], [], $overrideWhere);

            $byPosition[$field->position] = new SegmentField(
                $field->position,
                self::usage($override, $overrideWhere),
                self::cardinality($override, $overrideWhere),
                $field->definition,
                self::source($override, $overrideWhere)
            );
        }

        return $definition->withFields($byPosition);
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string> $required
     * @param list<string> $optional
     *
     * @throws HL7Exception
     */
    private static function checkKeys(array $data, array $required, array $optional, string $where): void
    {
        $keys = array_map('strval', array_keys($data));

        $unknown = array_diff($keys, $required, $optional);
        if ($unknown !== []) {
            throw new HL7Exception(sprintf('%s: unknown key(s) %s.', $where, implode(', ', $unknown)));
        }

        $missing = array_diff($required, $keys);
        if ($missing !== []) {
            throw new HL7Exception(sprintf('%s: missing key(s) %s.', $where, implode(', ', $missing)));
        }
    }

    /**
     * Check that an element is at its place: field PID.3 is the third of PID.
     *
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function checkId(array $data, string $key, string $expected, string $where): void
    {
        if (self::string($data, $key, $where) !== $expected) {
            throw new HL7Exception("$where: '$key' must be '$expected'.");
        }
    }

    /**
     * @return array<mixed, mixed>
     *
     * @throws HL7Exception
     */
    private static function entry(mixed $entry, string $where): array
    {
        if (!is_array($entry)) {
            throw new HL7Exception("$where: must be an object.");
        }

        return $entry;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @return list<mixed>
     *
     * @throws HL7Exception
     */
    private static function list(array $data, string $key, string $where): array
    {
        $list = $data[$key] ?? null;
        if (!is_array($list) || $list === [] || !array_is_list($list)) {
            throw new HL7Exception("$where: '$key' must be a non-empty list.");
        }

        return $list;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function string(array $data, string $key, string $where): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new HL7Exception("$where: '$key' must be a string.");
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function usage(array $data, string $where): Usage
    {
        $usage = self::string($data, 'usage', $where);

        return Usage::tryFrom($usage) ?? throw new HL7Exception("$where: unknown usage '$usage'.");
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function cardinality(array $data, string $where): Cardinality
    {
        $min = self::string($data, 'min', $where);
        $max = self::string($data, 'max', $where);

        return Cardinality::parse($min, $max) ?? throw new HL7Exception("$where: invalid cardinality [$min..$max].");
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function maxLength(array $data, string $where): ?int
    {
        $length = self::string($data, 'maxLength', $where);
        if ($length === '') {
            return null;
        }
        if (!ctype_digit($length)) {
            throw new HL7Exception("$where: 'maxLength' must be empty or a number.");
        }

        return (int) $length;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function table(array $data, string $where): ?string
    {
        $table = self::string($data, 'table', $where);

        return $table === '' ? null : $table;
    }

    /**
     * @param array<mixed, mixed> $data
     *
     * @throws HL7Exception
     */
    private static function source(array $data, string $where): ?string
    {
        if (!array_key_exists('source', $data)) {
            return null;
        }
        if (!is_string($data['source']) || $data['source'] === '') {
            throw new HL7Exception("$where: 'source' must be a non-empty string.");
        }

        return $data['source'];
    }
}
