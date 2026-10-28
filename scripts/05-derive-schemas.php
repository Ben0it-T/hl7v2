<?php

declare(strict_types=1);

/**
 * Generate a derived JSON schema (e.g. IHE PAM FR) from a base JSON schema (e.g. HL7 v2.5).
 *
 * The profile directory holds the sources of the derived schema, maintained by hand:
 * - messageType.json, eventDesc.json: messages of the derived profile;
 * - extensions.json: segments and fields added by the specification (Z segments);
 * - constraints.json: differences with the base schema, each with its source;
 * - deviations.json: accepted deviations (checked here, read by the profile audit);
 * - hl7tables.json: tables (not read by this script).
 *
 * The script writes segments.json, fields.json, dataTypes.json and structures/ in the
 * profile directory. It never writes a source. The format of the sources is described
 * in .notes/constraints-format.md.
 *
 * Usage:
 * php 05-derive-schemas.php \
 *     --base-dir=<directory> \
 *     --profile-dir=<directory>
 *
 * Example:
 * php 05-derive-schemas.php \
 *     --base-dir=../schemas/json-2.5 \
 *     --profile-dir=../schemas/json-2.5-IHEPAMFR
 */

$options = getopt('', [
    'base-dir:',
    'profile-dir:',
]);

$baseDir = $options['base-dir'] ?? null;
$profileDir = $options['profile-dir'] ?? null;

if (!is_string($baseDir) || $baseDir === '' || !is_string($profileDir) || $profileDir === '') {
    fwrite(
        STDERR,
        "Usage: php 05-derive-schemas.php --base-dir=<directory> --profile-dir=<directory>\n"
    );
    exit(1);
}

foreach ([$baseDir, $profileDir] as $directory) {
    if (!is_dir($directory)) {
        throw new RuntimeException(
            "Directory not found: {$directory}"
        );
    }
}

if (realpath($baseDir) === realpath($profileDir)) {
    throw new RuntimeException(
        'Base and profile directories must be different.'
    );
}

const USAGES = ['R', 'RE', 'O', 'C', 'CE', 'X'];
const DEVIATION_KINDS = ['segmentAdded'];

/**
 * Load JSON file.
 *
 * @param string $filename JSON filename.
 *
 * @return array<string, mixed>
 *
 * @throws RuntimeException If the file does not exist, cannot be read or is not a valid JSON object.
 */
function loadJson(string $filename): array
{
    if (!is_file($filename)) {
        throw new RuntimeException(
            "File not found: {$filename}"
        );
    }

    $json = file_get_contents($filename);

    if ($json === false) {
        throw new RuntimeException(
            "Unable to read file: {$filename}"
        );
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            "Invalid JSON file: {$filename}"
        );
    }

    return $data;
}

/**
 * Save JSON file.
 *
 * @param string $filename JSON filename.
 * @param array<mixed> $data Data to save.
 *
 * @return void
 *
 * @throws RuntimeException If the data cannot be encoded as JSON.
 */
function saveJson(string $filename, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException(
            "Unable to encode JSON: {$filename}"
        );
    }

    file_put_contents($filename, $json . "\n");
}

/**
 * Stop the build on an invalid source entry.
 *
 * @param string $where JSON path of the entry (e.g. constraints.json: segments.PID.fields.PID.7).
 * @param string $message Error message.
 *
 * @return never
 *
 * @throws RuntimeException Always.
 */
function fail(string $where, string $message): never
{
    throw new RuntimeException("{$where}: {$message}");
}

/**
 * Check that a value is a JSON object.
 *
 * @param mixed $value Value.
 * @param string $where JSON path of the value.
 *
 * @return array<string, mixed>
 *
 * @throws RuntimeException If the value is not an object.
 */
function objectAt(mixed $value, string $where): array
{
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        fail($where, 'object expected');
    }

    return $value;
}

/**
 * Check that a value is a JSON list.
 *
 * @param mixed $value Value.
 * @param string $where JSON path of the value.
 *
 * @return list<mixed>
 *
 * @throws RuntimeException If the value is not a list.
 */
function listAt(mixed $value, string $where): array
{
    if (!is_array($value) || !array_is_list($value)) {
        fail($where, 'list expected');
    }

    return $value;
}

/**
 * Check that a value is a string.
 *
 * @param mixed $value Value.
 * @param string $where JSON path of the value.
 *
 * @return string
 *
 * @throws RuntimeException If the value is not a string.
 */
function stringAt(mixed $value, string $where): string
{
    if (!is_string($value)) {
        fail($where, 'string expected');
    }

    return $value;
}

/**
 * Check the keys of an entry.
 *
 * @param array<string, mixed> $entry Entry.
 * @param list<string> $allowed Allowed keys.
 * @param list<string> $required Required keys.
 * @param string $where JSON path of the entry.
 *
 * @return void
 *
 * @throws RuntimeException On an unknown or a missing key.
 */
function checkKeys(array $entry, array $allowed, array $required, string $where): void
{
    foreach (array_keys($entry) as $key) {
        if (!in_array($key, $allowed, true)) {
            fail($where, "unknown key '{$key}'");
        }
    }

    foreach ($required as $key) {
        if (!array_key_exists($key, $entry)) {
            fail($where, "missing key '{$key}'");
        }
    }
}

/**
 * Check a source.
 *
 * A source is a document code declared in `documents`, a space, then a section (e.g. PAMFR 6.6.9).
 *
 * @param mixed $source Source.
 * @param array<string, mixed> $documents Documents, by code.
 * @param string $where JSON path of the entry.
 *
 * @return string
 *
 * @throws RuntimeException If the source is malformed or cites an unknown document.
 */
function checkSource(mixed $source, array $documents, string $where): string
{
    $source = stringAt($source, "{$where}.source");

    if (preg_match('/^(\S+) \S/', $source, $matches) !== 1) {
        fail("{$where}.source", "'{$source}' is not '<document> <section>'");
    }

    if (!isset($documents[$matches[1]])) {
        fail("{$where}.source", "unknown document '{$matches[1]}'");
    }

    return $source;
}

/**
 * Check usage and cardinality together.
 *
 * R: minOccurs >= 1. RE, O, CE: minOccurs 0. C: minOccurs 0 or 1, applied only when the
 * condition is true. X: [0..0]. Except for X, maxOccurs >= 1 and >= minOccurs.
 *
 * @param array<string, mixed> $entry Entry with usage, minOccurs and maxOccurs.
 * @param string $where JSON path of the entry.
 *
 * @return array{usage: string, minOccurs: string, maxOccurs: string}
 *
 * @throws RuntimeException If the values are not consistent.
 */
function checkOccurs(array $entry, string $where): array
{
    $usage = stringAt($entry['usage'] ?? null, "{$where}.usage");
    $minOccurs = stringAt($entry['minOccurs'] ?? null, "{$where}.minOccurs");
    $maxOccurs = stringAt($entry['maxOccurs'] ?? null, "{$where}.maxOccurs");

    if (!in_array($usage, USAGES, true)) {
        fail($where, "unknown usage '{$usage}'");
    }

    if (!ctype_digit($minOccurs) || ($maxOccurs !== 'unbounded' && !ctype_digit($maxOccurs))) {
        fail($where, "invalid cardinality [{$minOccurs}..{$maxOccurs}]");
    }

    $min = (int) $minOccurs;
    $max = $maxOccurs === 'unbounded' ? PHP_INT_MAX : (int) $maxOccurs;

    $consistent = match ($usage) {
        'R' => $min >= 1,
        'RE', 'O', 'CE' => $min === 0,
        'C' => $min <= 1,
        'X' => $min === 0 && $max === 0,
    };

    if ($usage !== 'X') {
        $consistent = $consistent && $max >= 1 && $max >= $min;
    }

    if (!$consistent) {
        fail($where, "usage {$usage} is not consistent with [{$minOccurs}..{$maxOccurs}]");
    }

    return ['usage' => $usage, 'minOccurs' => $minOccurs, 'maxOccurs' => $maxOccurs];
}

/**
 * Normalize a structure element.
 *
 * Key order: segment or group, minOccurs, maxOccurs, usage, source, fields.
 *
 * @param array<string, mixed> $element Structure element.
 *
 * @return array<string, mixed>
 */
function normalizeElement(array $element): array
{
    $order = ['segment', 'group', 'minOccurs', 'maxOccurs', 'usage', 'source', 'fields'];
    $normalized = [];

    foreach ($order as $key) {
        if (array_key_exists($key, $element)) {
            $normalized[$key] = $element[$key];
        }
    }

    return $normalized + $element;
}

/**
 * Add the extensions (Z segments and their fields).
 *
 * The `source` of each segment and field is the document of extensions.json and its Chapter.
 * `comment` is not copied.
 *
 * @param array<string, mixed> $segments Segment schemas (segments.json).
 * @param array<string, mixed> $fields Field schemas (fields.json).
 * @param array<string, mixed> $extensions Extensions (extensions.json).
 * @param array<string, mixed> $documents Documents, by code.
 *
 * @return array{0: array<string, mixed>, 1: array<string, mixed>} Segment and field schemas.
 *
 * @throws RuntimeException On an invalid entry, or a segment or field already in the base schema.
 */
function applyExtensions(array $segments, array $fields, array $extensions, array $documents): array
{
    $file = 'extensions.json';
    checkKeys($extensions, ['source', 'segments', 'fields'], ['source', 'segments', 'fields'], $file);

    $document = stringAt($extensions['source'], "{$file}: source");

    if (!isset($documents[$document])) {
        fail("{$file}: source", "unknown document '{$document}'");
    }

    foreach (objectAt($extensions['fields'], "{$file}: fields") as $id => $definition) {
        $where = "{$file}: fields.{$id}";
        $definition = objectAt($definition, $where);
        checkKeys($definition, ['Item', 'Type', 'Table', 'LongName', 'maxLength', 'Chapter', 'comment'], ['Type', 'Chapter'], $where);

        if (isset($fields[$id])) {
            fail($where, 'field already defined in the base schema');
        }

        unset($definition['comment']);
        $definition['source'] = $document . ' ' . stringAt($definition['Chapter'], "{$where}.Chapter");
        $fields[$id] = $definition;
    }

    foreach (objectAt($extensions['segments'], "{$file}: segments") as $name => $definition) {
        $where = "{$file}: segments.{$name}";
        $definition = objectAt($definition, $where);
        checkKeys($definition, ['fields', 'LongName', 'Chapter', 'comment'], ['fields', 'Chapter'], $where);

        if (isset($segments[$name])) {
            fail($where, 'segment already defined in the base schema');
        }

        foreach (listAt($definition['fields'], "{$where}.fields") as $index => $field) {
            $field = objectAt($field, "{$where}.fields[{$index}]");
            checkKeys($field, ['field', 'minOccurs', 'maxOccurs', 'usage'], ['field', 'minOccurs', 'maxOccurs', 'usage'], "{$where}.fields[{$index}]");
            checkOccurs($field, "{$where}.fields[{$index}]");

            if (!isset($fields[stringAt($field['field'], "{$where}.fields[{$index}].field")])) {
                fail("{$where}.fields[{$index}]", 'field not defined');
            }
        }

        unset($definition['comment']);
        $definition['source'] = $document . ' ' . stringAt($definition['Chapter'], "{$where}.Chapter");
        $segments[$name] = $definition;
    }

    return [$segments, $fields];
}

/**
 * Apply usage and cardinality constraints to a list of elements (segment fields or datatype components).
 *
 * @param list<mixed> $elements Elements of the segment or datatype.
 * @param string $idKey Identifier key of an element (field, dataType).
 * @param array<string, mixed> $constraints Constraints, by identifier.
 * @param array<string, mixed> $documents Documents, by code.
 * @param string $where JSON path of the constraints.
 *
 * @return list<mixed> Updated elements.
 *
 * @throws RuntimeException On an invalid constraint or an unknown identifier.
 */
function applyOccursConstraints(array $elements, string $idKey, array $constraints, array $documents, string $where): array
{
    foreach ($constraints as $id => $constraint) {
        $at = "{$where}.{$id}";
        $constraint = objectAt($constraint, $at);
        checkKeys($constraint, ['usage', 'minOccurs', 'maxOccurs', 'source', 'comment'], ['usage', 'minOccurs', 'maxOccurs', 'source'], $at);
        $occurs = checkOccurs($constraint, $at);
        $source = checkSource($constraint['source'], $documents, $at);
        $found = false;

        foreach ($elements as $index => $element) {
            if (!is_array($element) || ($element[$idKey] ?? null) !== $id) {
                continue;
            }

            $elements[$index] = array_merge($element, $occurs, ['source' => $source]);
            $found = true;
        }

        if (!$found) {
            fail($at, 'unknown identifier');
        }
    }

    return $elements;
}

/**
 * Apply definition constraints (Table, maxLength) to fields or datatype components.
 *
 * An entry with only a comment documents the definition without changing it.
 *
 * @param array<string, mixed> $definitions Definitions, by identifier (fields.json, dataTypes.json).
 * @param string $id Field or component identifier (e.g. IN1.2, CX.1).
 * @param mixed $constraint Constraint.
 * @param array<string, mixed> $documents Documents, by code.
 * @param string $where JSON path of the constraint.
 *
 * @return array<string, mixed> Updated definitions.
 *
 * @throws RuntimeException On an invalid constraint or an unknown identifier.
 */
function applyDefinitionConstraint(array $definitions, string $id, mixed $constraint, array $documents, string $where): array
{
    $constraint = objectAt($constraint, $where);
    checkKeys($constraint, ['Table', 'maxLength', 'source', 'comment'], [], $where);

    if (!isset($definitions[$id]) || !is_array($definitions[$id])) {
        fail($where, 'unknown identifier');
    }

    $changes = array_intersect_key($constraint, ['Table' => true, 'maxLength' => true]);

    if ($changes === []) {
        if (isset($constraint['source'])) {
            fail($where, 'source without a change');
        }

        return $definitions;
    }

    foreach ($changes as $key => $value) {
        $definitions[$id][$key] = stringAt($value, "{$where}.{$key}");
    }

    $definitions[$id]['source'] = checkSource($constraint['source'] ?? null, $documents, $where);

    return $definitions;
}

/**
 * Check the groups of segments.
 *
 * @param array<string, mixed> $groups Groups (constraints.json: groups).
 * @param array<string, mixed> $segments Segment schemas.
 * @param array<string, mixed> $documents Documents, by code.
 *
 * @return array<string, array{source: string, elements: list<array<string, mixed>>}>
 *
 * @throws RuntimeException On an invalid group.
 */
function checkGroups(array $groups, array $segments, array $documents): array
{
    $checked = [];

    foreach ($groups as $name => $group) {
        $where = "constraints.json: groups.{$name}";
        $group = objectAt($group, $where);
        checkKeys($group, ['source', 'comment', 'elements'], ['source', 'elements'], $where);
        $source = checkSource($group['source'], $documents, $where);
        $elements = [];

        foreach (listAt($group['elements'], "{$where}.elements") as $index => $element) {
            $at = "{$where}.elements[{$index}]";
            $element = objectAt($element, $at);
            checkKeys($element, ['segment', 'usage', 'minOccurs', 'maxOccurs'], ['segment', 'usage', 'minOccurs', 'maxOccurs'], $at);
            $segment = stringAt($element['segment'], "{$at}.segment");

            if (!isset($segments[$segment])) {
                fail($at, "unknown segment '{$segment}'");
            }

            $elements[] = ['segment' => $segment] + checkOccurs($element, $at);
        }

        $checked[$name] = ['source' => $source, 'elements' => $elements];
    }

    return $checked;
}

/**
 * Resolve a path in a message structure.
 *
 * A path starts at the root of the structure and goes down by group and segment names.
 * An index between brackets selects an occurrence, from 1 (e.g. ADT_A05/ROL[2]).
 *
 * @param array<string, mixed> $structure Message structure, by group name.
 * @param string $root Root of the structure.
 * @param string $path Path (e.g. ADT_A05/INSURANCE/IN3).
 * @param string $where JSON path of the operation.
 *
 * @return array{0: string, 1: int} Group name and element index.
 *
 * @throws RuntimeException If the path does not select exactly one element.
 */
function resolvePath(array $structure, string $root, string $path, string $where): array
{
    $parts = explode('/', $path);

    if (array_shift($parts) !== $root || $parts === []) {
        fail($where, "path '{$path}' does not start at the root '{$root}'");
    }

    $group = $root;
    $index = -1;

    foreach ($parts as $position => $part) {
        if (preg_match('/^([A-Z0-9_]+)(?:\[([1-9][0-9]*)\])?$/', $part, $matches) !== 1) {
            fail($where, "invalid path '{$path}'");
        }

        $elements = objectAt($structure[$group] ?? null, "{$where} ({$group})")['elements'] ?? [];
        $candidates = [];

        foreach (listAt($elements, "{$where} ({$group})") as $key => $element) {
            if (is_array($element) && (($element['segment'] ?? null) === $matches[1] || ($element['group'] ?? null) === $matches[1])) {
                $candidates[] = $key;
            }
        }

        $occurrence = isset($matches[2]) ? (int) $matches[2] : null;

        if ($candidates === [] || ($occurrence === null && count($candidates) > 1) || ($occurrence !== null && !isset($candidates[$occurrence - 1]))) {
            fail($where, "path '{$path}' does not select exactly one element");
        }

        $index = $candidates[($occurrence ?? 1) - 1];

        if ($position < count($parts) - 1) {
            $group = $matches[1];
        }
    }

    return [$group, $index];
}

/**
 * Apply the structure operations of a message.
 *
 * Operations are applied in order: update, insert (insert after), remove, replace.
 *
 * @param array<string, mixed> $structure Message structure, by group name.
 * @param string $root Root of the structure.
 * @param list<mixed> $operations Operations.
 * @param array<string, array{source: string, elements: list<array<string, mixed>>}> $groups Groups.
 * @param array<string, mixed> $segments Segment schemas.
 * @param array<string, mixed> $documents Documents, by code.
 * @param string $where JSON path of the operations.
 *
 * @return array{0: array<string, mixed>, 1: string} Updated structure and root.
 *
 * @throws RuntimeException On an invalid operation.
 */
function applyStructureOperations(array $structure, string $root, array $operations, array $groups, array $segments, array $documents, string $where): array
{
    foreach ($operations as $number => $operation) {
        $at = "{$where}[{$number}]";
        $operation = objectAt($operation, $at);

        if (isset($operation['update'])) {
            checkKeys($operation, ['update', 'usage', 'minOccurs', 'maxOccurs', 'source', 'comment'], ['usage', 'minOccurs', 'maxOccurs', 'source'], $at);
            [$group, $index] = resolvePath($structure, $root, stringAt($operation['update'], "{$at}.update"), $at);
            $element = objectAt(listAt(objectAt($structure[$group], $at)['elements'], $at)[$index], $at);
            $structure[$group]['elements'][$index] = array_merge(
                $element,
                checkOccurs($operation, $at),
                ['source' => checkSource($operation['source'], $documents, $at)]
            );
            continue;
        }

        if (isset($operation['insert'])) {
            checkKeys($operation, ['insert', 'after', 'only', 'source', 'comment'], ['after'], $at);
            $name = stringAt($operation['insert'], "{$at}.insert");

            if (!isset($groups[$name])) {
                fail($at, "unknown group '{$name}'");
            }

            $source = isset($operation['source']) ? checkSource($operation['source'], $documents, $at) : $groups[$name]['source'];
            $elements = $groups[$name]['elements'];

            if (isset($operation['only'])) {
                $only = listAt($operation['only'], "{$at}.only");
                $names = array_column($elements, 'segment');

                foreach ($only as $segment) {
                    if (!in_array($segment, $names, true)) {
                        fail("{$at}.only", "segment '" . stringAt($segment, "{$at}.only") . "' is not in group '{$name}'");
                    }
                }

                $elements = array_values(array_filter($elements, fn (array $element): bool => in_array($element['segment'], $only, true)));
            }

            $elements = array_map(fn (array $element): array => $element + ['source' => $source], $elements);
            [$group, $index] = resolvePath($structure, $root, stringAt($operation['after'], "{$at}.after"), $at);
            $list = listAt(objectAt($structure[$group], $at)['elements'], $at);
            array_splice($list, $index + 1, 0, $elements);
            $structure[$group]['elements'] = $list;
            continue;
        }

        if (isset($operation['remove'])) {
            checkKeys($operation, ['remove', 'source', 'comment'], ['source'], $at);
            checkSource($operation['source'], $documents, $at);
            [$group, $index] = resolvePath($structure, $root, stringAt($operation['remove'], "{$at}.remove"), $at);
            $list = listAt(objectAt($structure[$group], $at)['elements'], $at);
            array_splice($list, $index, 1);
            $structure[$group]['elements'] = $list;
            continue;
        }

        if (isset($operation['replace'])) {
            checkKeys($operation, ['replace', 'with', 'source', 'comment'], ['with', 'source'], $at);
            $source = checkSource($operation['source'], $documents, $at);

            if ($operation['replace'] !== $root) {
                fail($at, "replace '" . stringAt($operation['replace'], "{$at}.replace") . "' is not the root '{$root}'");
            }

            $with = objectAt($operation['with'], "{$at}.with");

            if (!isset($with[$root])) {
                fail("{$at}.with", "missing root '{$root}'");
            }

            $structure = [];

            foreach ($with as $group => $definition) {
                $definition = objectAt($definition, "{$at}.with.{$group}");
                checkKeys($definition, ['elements'], ['elements'], "{$at}.with.{$group}");
                $elements = [];

                foreach (listAt($definition['elements'], "{$at}.with.{$group}.elements") as $index => $element) {
                    $place = "{$at}.with.{$group}.elements[{$index}]";
                    $element = objectAt($element, $place);
                    checkKeys($element, ['segment', 'group', 'usage', 'minOccurs', 'maxOccurs'], ['usage', 'minOccurs', 'maxOccurs'], $place);
                    $occurs = checkOccurs($element, $place);

                    if (isset($element['segment']) === isset($element['group'])) {
                        fail($place, 'segment or group expected');
                    }

                    if (isset($element['segment']) && !isset($segments[$element['segment']])) {
                        fail($place, 'unknown segment');
                    }

                    if (isset($element['group']) && !isset($with[$element['group']])) {
                        fail($place, 'group not defined in with');
                    }

                    $kind = isset($element['segment']) ? 'segment' : 'group';
                    $elements[] = [$kind => $element[$kind]] + $occurs + ['source' => $source];
                }

                $structure[$group] = ['elements' => $elements];
            }

            continue;
        }

        fail($at, 'unknown operation');
    }

    return [$structure, $root];
}

/**
 * Collect the field definitions of each message, from `rules` and `messages`.
 *
 * @param array<string, mixed> $constraints Constraints (constraints.json).
 *
 * @return array<string, array<string, array{definition: mixed, where: string}>> Definitions, by message and key.
 *
 * @throws RuntimeException If a key is defined twice for the same message.
 */
function collectMessageFields(array $constraints): array
{
    $collected = [];
    $add = function (string $message, string $key, mixed $definition, string $where) use (&$collected): void {
        if (isset($collected[$message][$key])) {
            fail($where, "{$key} already defined for {$message} at {$collected[$message][$key]['where']}");
        }

        $collected[$message][$key] = ['definition' => $definition, 'where' => $where];
    };

    foreach (listAt($constraints['rules'] ?? [], 'constraints.json: rules') as $number => $rule) {
        $where = "constraints.json: rules[{$number}]";
        $rule = objectAt($rule, $where);
        checkKeys($rule, ['messages', 'fields'], ['messages', 'fields'], $where);

        foreach (listAt($rule['messages'], "{$where}.messages") as $message) {
            foreach (objectAt($rule['fields'], "{$where}.fields") as $key => $definition) {
                $add(stringAt($message, "{$where}.messages"), $key, $definition, "{$where}.fields.{$key}");
            }
        }
    }

    foreach (objectAt($constraints['messages'] ?? [], 'constraints.json: messages') as $message => $entry) {
        $entry = objectAt($entry, "constraints.json: messages.{$message}");

        foreach (objectAt($entry['fields'] ?? [], "constraints.json: messages.{$message}.fields") as $key => $definition) {
            $add($message, $key, $definition, "constraints.json: messages.{$message}.fields.{$key}");
        }
    }

    return $collected;
}

/**
 * Resolve the field definitions of a message, by segment.
 *
 * A segment key with `default` applies its definition to every field of the segment, except
 * those listed in `except`. A field's own definition wins over `default`.
 *
 * @param array<string, array{definition: mixed, where: string}> $entries Definitions of the message, by key.
 * @param array<string, mixed> $segments Segment schemas.
 * @param array<string, mixed> $documents Documents, by code.
 *
 * @return array<string, array<string, array<string, string>>> Field definitions, by segment and field.
 *
 * @throws RuntimeException On an invalid definition.
 */
function resolveMessageFields(array $entries, array $segments, array $documents): array
{
    $resolved = [];
    $fieldIds = function (string $segment, string $where) use ($segments): array {
        $definition = objectAt($segments[$segment] ?? null, $where);

        return array_map(fn (mixed $field): mixed => is_array($field) ? $field['field'] : null, listAt($definition['fields'], $where));
    };
    $definitionOf = function (mixed $definition, string $where) use ($documents): array {
        $definition = objectAt($definition, $where);
        checkKeys($definition, ['usage', 'minOccurs', 'maxOccurs', 'source', 'comment'], ['usage', 'minOccurs', 'maxOccurs', 'source'], $where);

        return checkOccurs($definition, $where) + ['source' => checkSource($definition['source'], $documents, $where)];
    };

    foreach ($entries as $key => ['definition' => $definition, 'where' => $where]) {
        if (!str_contains($key, '.')) {
            continue;
        }

        $segment = explode('.', $key)[0];

        if (!in_array($key, $fieldIds($segment, $where), true)) {
            fail($where, "unknown field '{$key}'");
        }

        $resolved[$segment][$key] = $definitionOf($definition, $where);
    }

    foreach ($entries as $segment => ['definition' => $definition, 'where' => $where]) {
        if (str_contains($segment, '.')) {
            continue;
        }

        $entry = objectAt($definition, $where);
        checkKeys($entry, ['default', 'except'], ['default'], $where);
        $default = $definitionOf($entry['default'], "{$where}.default");
        $ids = $fieldIds($segment, $where);
        $except = listAt($entry['except'] ?? [], "{$where}.except");

        foreach ($except as $id) {
            if (!in_array($id, $ids, true)) {
                fail("{$where}.except", "'" . stringAt($id, "{$where}.except") . "' is not a field of {$segment}");
            }

            if (isset($resolved[$segment][$id])) {
                fail("{$where}.except", "'{$id}' also has its own definition");
            }
        }

        foreach ($ids as $id) {
            if (is_string($id) && !in_array($id, $except, true) && !isset($resolved[$segment][$id])) {
                $resolved[$segment][$id] = $default;
            }
        }
    }

    foreach ($resolved as $segment => $fields) {
        uksort($fields, 'strnatcmp');
        $resolved[$segment] = $fields;
    }

    return $resolved;
}

/**
 * Add the field definitions of a message to every element of their segment.
 *
 * @param array<string, mixed> $structure Message structure, by group name.
 * @param array<string, array<string, array<string, string>>> $fields Field definitions, by segment and field.
 * @param string $where Message.
 *
 * @return array<string, mixed> Updated structure.
 *
 * @throws RuntimeException If a segment is not in the structure.
 */
function addMessageFields(array $structure, array $fields, string $where): array
{
    foreach ($fields as $segment => $definitions) {
        $found = false;

        foreach ($structure as $group => $definition) {
            foreach (listAt(objectAt($definition, $where)['elements'], $where) as $index => $element) {
                if (is_array($element) && ($element['segment'] ?? null) === $segment) {
                    $structure[$group]['elements'][$index]['fields'] = $definitions;
                    $found = true;
                }
            }
        }

        if (!$found) {
            fail($where, "segment {$segment} is not in the structure");
        }
    }

    return $structure;
}

/**
 * Check the accepted deviations.
 *
 * @param array<string, mixed> $deviations Accepted deviations (deviations.json).
 * @param array<string, array<string, mixed>> $structures Derived structures, by message.
 * @param array<string, string> $roots Root of each derived structure, by message.
 * @param array<string, mixed> $documents Documents, by code.
 *
 * @return void
 *
 * @throws RuntimeException On an invalid deviation.
 */
function checkDeviations(array $deviations, array $structures, array $roots, array $documents): void
{
    checkKeys($deviations, ['deviations'], ['deviations'], 'deviations.json');

    foreach (listAt($deviations['deviations'], 'deviations.json: deviations') as $number => $deviation) {
        $where = "deviations.json: deviations[{$number}]";
        $deviation = objectAt($deviation, $where);
        checkKeys($deviation, ['messages', 'path', 'kind', 'source', 'comment'], ['messages', 'path', 'kind', 'source'], $where);
        checkSource($deviation['source'], $documents, $where);

        if (!in_array($deviation['kind'], DEVIATION_KINDS, true)) {
            fail($where, 'unknown kind');
        }

        foreach (listAt($deviation['messages'], "{$where}.messages") as $message) {
            $message = stringAt($message, "{$where}.messages");

            if (!isset($structures[$message])) {
                fail($where, "unknown message '{$message}'");
            }

            resolvePath($structures[$message], $roots[$message], stringAt($deviation['path'], "{$where}.path"), "{$where} ({$message})");
        }
    }
}



// Load schemas and sources
$segmentsSchemas  = loadJson($baseDir . '/segments.json');
$fieldsSchemas    = loadJson($baseDir . '/fields.json');
$dataTypesSchemas = loadJson($baseDir . '/dataTypes.json');
$messageType      = loadJson($profileDir . '/messageType.json');
$eventDesc        = loadJson($profileDir . '/eventDesc.json');
$extensions       = loadJson($profileDir . '/extensions.json');
$constraints      = loadJson($profileDir . '/constraints.json');
$deviations       = loadJson($profileDir . '/deviations.json');

checkKeys(
    $constraints,
    ['documents', 'groups', 'dataTypes', 'segments', 'fields', 'rules', 'messages'],
    ['documents'],
    'constraints.json'
);

$documents = objectAt($constraints['documents'], 'constraints.json: documents');

foreach ($documents as $code => $title) {
    stringAt($title, "constraints.json: documents.{$code}");
}

// Extensions
[$segmentsSchemas, $fieldsSchemas] = applyExtensions($segmentsSchemas, $fieldsSchemas, $extensions, $documents);
echo "Extensions: done.\n";

// Datatypes
foreach (objectAt($constraints['dataTypes'] ?? [], 'constraints.json: dataTypes') as $id => $constraint) {
    $where = "constraints.json: dataTypes.{$id}";

    if (str_contains($id, '.')) {
        $dataTypesSchemas = applyDefinitionConstraint($dataTypesSchemas, $id, $constraint, $documents, $where);
        continue;
    }

    $constraint = objectAt($constraint, $where);
    checkKeys($constraint, ['components', 'comment'], ['components'], $where);
    $components = objectAt($dataTypesSchemas[$id] ?? null, $where)['components'] ?? null;
    $dataTypesSchemas[$id]['components'] = applyOccursConstraints(
        listAt($components, $where),
        'dataType',
        objectAt($constraint['components'], "{$where}.components"),
        $documents,
        "{$where}.components"
    );
}
echo "Datatypes: done.\n";

// Segments and fields
foreach (objectAt($constraints['segments'] ?? [], 'constraints.json: segments') as $name => $constraint) {
    $where = "constraints.json: segments.{$name}";
    $constraint = objectAt($constraint, $where);
    checkKeys($constraint, ['fields'], ['fields'], $where);
    $segmentFields = objectAt($segmentsSchemas[$name] ?? null, $where)['fields'] ?? null;
    $segmentsSchemas[$name]['fields'] = applyOccursConstraints(
        listAt($segmentFields, $where),
        'field',
        objectAt($constraint['fields'], "{$where}.fields"),
        $documents,
        "{$where}.fields"
    );
}

foreach (objectAt($constraints['fields'] ?? [], 'constraints.json: fields') as $id => $constraint) {
    $fieldsSchemas = applyDefinitionConstraint($fieldsSchemas, $id, $constraint, $documents, "constraints.json: fields.{$id}");
}
echo "Segments and fields: done.\n";

// Messages
$groups = checkGroups(objectAt($constraints['groups'] ?? [], 'constraints.json: groups'), $segmentsSchemas, $documents);
$messageFields = collectMessageFields($constraints);
$messageEntries = objectAt($constraints['messages'] ?? [], 'constraints.json: messages');
$structures = [];
$roots = [];
$files = [];

foreach ($messageType as $type => $events) {
    foreach (objectAt($events, "messageType.json: {$type}") as $event => $value) {
        $message = "{$type}^{$event}";
        $where = "messageType.json: {$type}.{$event}";
        $value = stringAt($value, $where);

        if (preg_match('/^([A-Z0-9]+)-([A-Z0-9]+)-([A-Z0-9_]+)$/', $value, $matches) !== 1 || $matches[1] !== $type || $matches[2] !== $event) {
            fail($where, "'{$value}' is not '{$type}-{$event}-<structure>'");
        }

        if (!isset(objectAt($eventDesc[$type] ?? null, "eventDesc.json: {$type}")[$event])) {
            fail("eventDesc.json: {$type}", "missing event {$event}");
        }

        $structure = loadJson("{$baseDir}/structures/{$matches[3]}.json");
        $root = $matches[3];
        $entry = objectAt($messageEntries[$message] ?? [], "constraints.json: messages.{$message}");
        checkKeys($entry, ['structure', 'fields', 'comment'], [], "constraints.json: messages.{$message}");

        [$structure, $root] = applyStructureOperations(
            $structure,
            $root,
            listAt($entry['structure'] ?? [], "constraints.json: messages.{$message}.structure"),
            $groups,
            $segmentsSchemas,
            $documents,
            "constraints.json: messages.{$message}.structure"
        );

        $structure = addMessageFields(
            $structure,
            resolveMessageFields($messageFields[$message] ?? [], $segmentsSchemas, $documents),
            $message
        );

        foreach ($structure as $group => $definition) {
            $structure[$group]['elements'] = array_map(
                fn (mixed $element): array => normalizeElement(objectAt($element, $message)),
                listAt(objectAt($definition, $message)['elements'], $message)
            );
        }

        $structures[$message] = $structure;
        $roots[$message] = $root;
        $files[$message] = $value;
    }
}

foreach (array_keys($messageEntries + $messageFields) as $message) {
    if (!isset($structures[$message])) {
        fail('constraints.json', "message '{$message}' is not in messageType.json");
    }
}

foreach ($eventDesc as $type => $events) {
    foreach (array_keys(objectAt($events, "eventDesc.json: {$type}")) as $event) {
        if (!isset($structures["{$type}^{$event}"])) {
            fail("eventDesc.json: {$type}", "event {$event} is not in messageType.json");
        }
    }
}

checkDeviations($deviations, $structures, $roots, $documents);
echo "Message structures: done.\n";

// Write the derived schema
if (!is_dir($profileDir . '/structures') && !mkdir($profileDir . '/structures', 0777, true)) {
    throw new RuntimeException("Unable to create directory: {$profileDir}/structures");
}

foreach (glob($profileDir . '/structures/*.json') ?: [] as $filename) {
    if (!unlink($filename)) {
        throw new RuntimeException("Unable to remove file: {$filename}");
    }
}

foreach ($structures as $message => $structure) {
    saveJson("{$profileDir}/structures/{$files[$message]}.json", $structure);
}

ksort($segmentsSchemas);
ksort($fieldsSchemas);
ksort($dataTypesSchemas);

saveJson($profileDir . '/segments.json', $segmentsSchemas);
saveJson($profileDir . '/fields.json', $fieldsSchemas);
saveJson($profileDir . '/dataTypes.json', $dataTypesSchemas);

echo "Done.\n";
