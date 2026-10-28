<?php

declare(strict_types=1);

/**
 * Create a JSON profile directory from a JSON schema directory.
 *
 * The profile is a resolved copy of the schema (format described in profiles/README.md):
 * - restricted to the selected messages, and to the segments, fields and datatypes they use;
 * - usages computed;
 * - table names normalized (0010);
 * - keys in camelCase.
 *
 * The script replaces the generated files of the profile directory and keeps rules.json.
 * A table cited by a field or a component but absent from hl7tables.json is reported as a warning,
 * and so is a usage that is not consistent with its cardinality (see occursWarning()).
 *
 * Usage:
 * php 06-create-json-profile.php \
 *     --schema-dir=<directory> \
 *     --profile-dir=<directory> \
 *     [--types=<type,...>] \
 *     [--ignore-events=<event,...>]
 *
 * Examples:
 * php 06-create-json-profile.php \
 *     --schema-dir=../schemas/json-2.5 \
 *     --profile-dir=../profiles-next/json-2.5 \
 *     --types=ACK,ADT,SIU
 *
 * php 06-create-json-profile.php \
 *     --schema-dir=../schemas/json-2.5-IHEPAMFR \
 *     --profile-dir=../profiles-next/json-2.5-IHEPAMFR
 */

$options = getopt('', [
    'schema-dir:',
    'profile-dir:',
    'types:',
    'ignore-events:',
]);

$schemaDir = $options['schema-dir'] ?? null;
$profileDir = $options['profile-dir'] ?? null;
$types = $options['types'] ?? null;
$ignoreEvents = $options['ignore-events'] ?? '';

if (
    !is_string($schemaDir) || $schemaDir === ''
    || !is_string($profileDir) || $profileDir === ''
    || ($types !== null && !is_string($types))
    || !is_string($ignoreEvents)
) {
    fwrite(
        STDERR,
        "Usage: php 06-create-json-profile.php --schema-dir=<directory> --profile-dir=<directory>"
        . " [--types=<type,...>] [--ignore-events=<event,...>]\n"
    );
    exit(1);
}

if (!is_dir($schemaDir)) {
    throw new RuntimeException(
        "Directory not found: {$schemaDir}"
    );
}

if (is_dir($profileDir) && realpath($schemaDir) === realpath($profileDir)) {
    throw new RuntimeException(
        'Schema and profile directories must be different.'
    );
}

const FORMAT_VERSION = '1';
const GENERATED_FILES = [
    'profile.json',
    'messageType.json',
    'eventDesc.json',
    'segments.json',
    'fields.json',
    'dataTypes.json',
    'hl7tables.json',
    'hl7tables-with-description.json',
];

// Pseudo-codes describing an empty field: kept for display, not valid values.
// - table 0078 "null"
// - table 0356 "<null>"
// HL7 2.5, 2.5.3: an omitted field is not the same as "".
const PSEUDO_CODES = ['null', '<null>'];

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
 * Split a comma-separated option.
 *
 * @param string $value Option value (e.g. ACK,ADT).
 *
 * @return list<string>
 */
function splitList(string $value): array
{
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));
}

/**
 * Read a string value of a schema entry.
 *
 * @param array<mixed> $entry Schema entry.
 * @param string $key Key.
 * @param string $where Entry, for the error message.
 *
 * @return string
 *
 * @throws RuntimeException If the value is missing or is not a string.
 */
function stringOf(array $entry, string $key, string $where): string
{
    if (!isset($entry[$key]) || !is_string($entry[$key])) {
        throw new RuntimeException("{$where}: missing or invalid '{$key}'");
    }

    return $entry[$key];
}

/**
 * Read an object of a schema.
 *
 * @param array<mixed> $schema Schema.
 * @param string $key Key.
 * @param string $where Schema, for the error message.
 *
 * @return array<mixed>
 *
 * @throws RuntimeException If the key is not defined.
 */
function entryOf(array $schema, string $key, string $where): array
{
    if (!isset($schema[$key]) || !is_array($schema[$key])) {
        throw new RuntimeException("{$where}: '{$key}' is not defined");
    }

    return $schema[$key];
}

/**
 * Convert usage and cardinality.
 *
 * minOccurs and maxOccurs become min and max (unbounded → *). An absent usage is computed:
 * [0..0] → X, min >= 1 → R, otherwise O.
 *
 * @param array<mixed> $entry Schema entry with minOccurs, maxOccurs and optional usage.
 * @param string $where Entry, for the error message.
 *
 * @return array{usage: string, min: string, max: string}
 *
 * @throws RuntimeException If the cardinality is missing.
 */
function convertOccurs(array $entry, string $where): array
{
    $min = stringOf($entry, 'minOccurs', $where);
    $max = stringOf($entry, 'maxOccurs', $where);
    $max = $max === 'unbounded' ? '*' : $max;

    if (isset($entry['usage'])) {
        $usage = stringOf($entry, 'usage', $where);
    } elseif ($min === '0' && $max === '0') {
        $usage = 'X';
    } elseif ((int) $min >= 1) {
        $usage = 'R';
    } else {
        $usage = 'O';
    }

    $occurs = ['usage' => $usage, 'min' => $min, 'max' => $max];
    $warning = occursWarning($occurs);

    if ($warning !== null) {
        warn("{$where}: {$warning}");
    }

    return $occurs;
}

/**
 * Check usage and cardinality together.
 *
 * R: min >= 1. RE, O, CE, B: min 0. C: min 0 or 1, applied only when the condition is true.
 * X, W: [0..0]. Except for X and W, max >= 1 and >= min.
 *
 * @param array{usage: string, min: string, max: string} $occurs Usage and cardinality.
 *
 * @return string|null Why they are not consistent, or null.
 */
function occursWarning(array $occurs): ?string
{
    ['usage' => $usage, 'min' => $min, 'max' => $max] = $occurs;

    if (!ctype_digit($min) || ($max !== '*' && !ctype_digit($max))) {
        return "invalid cardinality [{$min}..{$max}]";
    }

    $minimum = (int) $min;
    $maximum = $max === '*' ? PHP_INT_MAX : (int) $max;

    $consistent = match ($usage) {
        'R' => $minimum >= 1 && $maximum >= $minimum,
        'RE', 'O', 'CE', 'B' => $minimum === 0 && $maximum >= 1,
        'C' => $minimum <= 1 && $maximum >= 1,
        'X', 'W' => $minimum === 0 && $maximum === 0,
        default => false,
    };

    return $consistent ? null : "usage {$usage} is not consistent with [{$min}..{$max}]";
}

/**
 * Record a warning, reported at the end of the script.
 *
 * @param string|null $message Warning to record; null returns the recorded warnings.
 *
 * @return list<string> Recorded warnings.
 */
function warn(?string $message = null): array
{
    static $warnings = [];

    if ($message !== null && !in_array($message, $warnings, true)) {
        $warnings[] = $message;
    }

    return $warnings;
}

/**
 * Normalize a table name (HL70010 → 0010).
 *
 * @param string $table Table name of the schema.
 *
 * @return string
 */
function normalizeTable(string $table): string
{
    return str_starts_with($table, 'HL7') ? substr($table, 3) : $table;
}

/**
 * Join the sources of an entry.
 *
 * @param list<mixed> $sources Sources, in order (absent sources are null).
 *
 * @return array{source?: string}
 */
function sourceOf(array $sources): array
{
    $sources = array_values(array_unique(array_filter($sources, 'is_string')));

    return $sources === [] ? [] : ['source' => implode(', ', $sources)];
}

/**
 * Convert a message structure.
 *
 * The root group comes first. Field definitions of a segment element are converted too.
 *
 * @param array<string, mixed> $structure Message structure of the schema, by group name.
 * @param string $root Root group (message structure name).
 * @param array<string, mixed> $segments Segment schemas.
 * @param string $where Message, for the error message.
 * @param array<string, true> $usedSegments Segments used by the messages (updated).
 *
 * @return array<string, mixed> Message structure of the profile.
 *
 * @throws RuntimeException On an undefined group or segment.
 */
function convertStructure(array $structure, string $root, array $segments, string $where, array &$usedSegments): array
{
    entryOf($structure, $root, $where);
    $converted = [];

    foreach ([$root => $structure[$root]] + $structure as $name => $group) {
        $group = entryOf($structure, $name, $where);
        $elements = [];

        foreach (entryOf($group, 'elements', "{$where} {$name}") as $element) {
            if (!is_array($element)) {
                throw new RuntimeException("{$where} {$name}: invalid element");
            }

            if (isset($element['segment'])) {
                $segment = stringOf($element, 'segment', "{$where} {$name}");
                entryOf($segments, $segment, "{$where}: segments.json");
                $usedSegments[$segment] = true;
                $convertedElement = ['segment' => $segment];
            } else {
                $child = stringOf($element, 'group', "{$where} {$name}");
                entryOf($structure, $child, $where);
                $convertedElement = ['group' => $child];
            }

            $convertedElement += convertOccurs($element, "{$where} {$name}");
            $convertedElement += sourceOf([$element['source'] ?? null]);

            if (isset($element['fields'])) {
                $fields = [];

                foreach (entryOf($element, 'fields', $where) as $field => $definition) {
                    $definition = entryOf($element['fields'], (string) $field, $where);
                    $fields[$field] = convertOccurs($definition, "{$where} {$field}") + sourceOf([$definition['source'] ?? null]);
                }

                $convertedElement['fields'] = $fields;
            }

            $elements[] = $convertedElement;
        }

        $converted[$name] = ['elements' => $elements];

        if (($group['type'] ?? null) === 'choice') {
            $converted[$name]['choice'] = true;
        }
    }

    return $converted;
}



// Load schemas
$profileSource = loadJson($schemaDir . '/profile.json');
$messageType   = loadJson($schemaDir . '/messageType.json');
$eventDesc     = loadJson($schemaDir . '/eventDesc.json');
$segments      = loadJson($schemaDir . '/segments.json');
$fields        = loadJson($schemaDir . '/fields.json');
$dataTypes     = loadJson($schemaDir . '/dataTypes.json');
$hl7tables     = loadJson($schemaDir . '/hl7tables.json');
$documents     = is_file($schemaDir . '/constraints.json')
    ? entryOf(loadJson($schemaDir . '/constraints.json'), 'documents', 'constraints.json')
    : [];

foreach (array_keys($profileSource) as $key) {
    if (!in_array($key, ['name', 'hl7Version', 'base'], true)) {
        throw new RuntimeException("profile.json: unknown key '{$key}'");
    }
}

$selectedTypes = $types === null ? array_keys($messageType) : splitList($types);
$ignoredEvents = splitList($ignoreEvents);

foreach ($selectedTypes as $type) {
    if (!isset($messageType[$type])) {
        throw new RuntimeException("--types: unknown message type '{$type}'");
    }
}

// Messages
$profileMessageType = [];
$profileEventDesc = [];
$structures = [];
$usedSegments = [];

foreach ($selectedTypes as $type) {
    foreach (entryOf($messageType, $type, 'messageType.json') as $event => $value) {
        if (in_array($event, $ignoredEvents, true)) {
            continue;
        }

        $where = "messageType.json: {$type}.{$event}";
        $value = is_string($value) ? $value : throw new RuntimeException("{$where}: string expected");

        // A derived schema names the structure file of the message (ADT-A04-ADT_A01),
        // an HL7 schema names the message structure (ADT_A01).
        if (preg_match('/^' . $type . '-' . $event . '-([A-Z0-9_]+)$/', $value, $matches) === 1) {
            $structureName = $matches[1];
        } elseif (preg_match('/^[A-Z0-9_]+$/', $value) === 1) {
            $structureName = $value;
        } else {
            throw new RuntimeException("{$where}: invalid value '{$value}'");
        }

        $profileMessageType[$type][$event] = $structureName;
        $profileEventDesc[$type][$event] = stringOf(entryOf($eventDesc, $type, 'eventDesc.json'), (string) $event, "eventDesc.json: {$type}");
        $structures["{$type}-{$event}-{$structureName}"] = convertStructure(
            loadJson("{$schemaDir}/structures/{$value}.json"),
            $structureName,
            $segments,
            "{$type}^{$event}",
            $usedSegments
        );
    }
}

// Segments and fields
ksort($usedSegments);
$profileSegments = [];
$profileFields = [];
$usedDataTypes = [];
$citedTables = [];

foreach (array_keys($usedSegments) as $name) {
    $segment = entryOf($segments, $name, 'segments.json');
    $segmentFields = [];

    foreach (entryOf($segment, 'fields', "segments.json: {$name}") as $field) {
        if (!is_array($field)) {
            throw new RuntimeException("segments.json: {$name}: invalid field");
        }

        $id = stringOf($field, 'field', "segments.json: {$name}");
        $segmentFields[] = ['field' => $id] + convertOccurs($field, "segments.json: {$id}") + sourceOf([$field['source'] ?? null]);

        $definition = entryOf($fields, $id, 'fields.json');
        $dataType = stringOf($definition, 'Type', "fields.json: {$id}");
        $table = normalizeTable(stringOf($definition, 'Table', "fields.json: {$id}"));
        $item = stringOf($definition, 'Item', "fields.json: {$id}");

        $profileFields[$id] = [
            'item' => $item === '' ? '' : str_pad($item, 5, '0', STR_PAD_LEFT),
            'dataType' => $dataType,
            'maxLength' => stringOf($definition, 'maxLength', "fields.json: {$id}"),
            'table' => $table,
            'longName' => stringOf($definition, 'LongName', "fields.json: {$id}"),
            'chapter' => stringOf($definition, 'Chapter', "fields.json: {$id}"),
        ] + sourceOf([$definition['source'] ?? null]);

        entryOf($dataTypes, $dataType, "fields.json: {$id}: dataTypes.json");
        $usedDataTypes[$dataType] = true;

        if ($table !== '') {
            $citedTables[$table][] = $id;
        }
    }

    $profileSegments[$name] = [
        'longName' => stringOf($segment, 'LongName', "segments.json: {$name}"),
        'chapter' => stringOf($segment, 'Chapter', "segments.json: {$name}"),
    ] + sourceOf([$segment['source'] ?? null]) + ['fields' => $segmentFields];
}

uksort($profileFields, 'strnatcmp');

// Datatypes, followed from the field datatypes to the component datatypes
$profileDataTypes = [];
$queue = array_keys($usedDataTypes);

while ($queue !== []) {
    $name = array_shift($queue);

    if (isset($profileDataTypes[$name])) {
        continue;
    }

    $dataType = entryOf($dataTypes, $name, 'dataTypes.json');

    if (!isset($dataType['components'])) {
        $profileDataTypes[$name] = ['primitive' => true];
        continue;
    }

    $components = [];

    foreach (entryOf($dataType, 'components', "dataTypes.json: {$name}") as $component) {
        if (!is_array($component)) {
            throw new RuntimeException("dataTypes.json: {$name}: invalid component");
        }

        $id = stringOf($component, 'dataType', "dataTypes.json: {$name}");
        $definition = entryOf($dataTypes, $id, 'dataTypes.json');
        $componentType = stringOf($definition, 'Type', "dataTypes.json: {$id}");
        $table = normalizeTable(stringOf($definition, 'Table', "dataTypes.json: {$id}"));
        entryOf($dataTypes, $componentType, "dataTypes.json: {$id}");

        $components[] = [
            'component' => $id,
            'dataType' => $componentType,
        ] + convertOccurs($component, "dataTypes.json: {$id}") + [
            'maxLength' => stringOf($definition, 'maxLength', "dataTypes.json: {$id}"),
            'table' => $table,
            'longName' => stringOf($definition, 'LongName', "dataTypes.json: {$id}"),
        ] + sourceOf([$component['source'] ?? null, $definition['source'] ?? null]);

        if ($table !== '') {
            $citedTables[$table][] = $id;
        }

        $queue[] = $componentType;
    }

    $profileDataTypes[$name] = ['components' => $components];
}

ksort($profileDataTypes);

// Tables: every table of the source is kept
$profileTables = [];

foreach ($hl7tables as $id => $table) {
    $table = entryOf($hl7tables, (string) $id, 'hl7tables.json');
    $codes = [];

    foreach (entryOf($table, 'codes', "hl7tables.json: {$id}") as $code) {
        $value = is_array($code) ? stringOf($code, 'code', "hl7tables.json: {$id}") : '';

        if (!in_array($value, PSEUDO_CODES, true) && !in_array($value, $codes, true)) {
            $codes[] = $value;
        }
    }

    $profileTables[$id] = array_merge(array_diff_key($table, ['codes' => true]), ['codes' => $codes]);
}

$missingTables = array_diff_key($citedTables, $hl7tables);
ksort($missingTables);

// Write the profile
if (!is_dir($profileDir . '/structures') && !mkdir($profileDir . '/structures', 0777, true) && !is_dir($profileDir . '/structures')) {
    throw new RuntimeException(
        "Unable to create directory: {$profileDir}/structures"
    );
}

foreach (array_merge(
    array_map(fn (string $file): string => "{$profileDir}/{$file}", GENERATED_FILES),
    glob($profileDir . '/structures/*.json') ?: []
) as $filename) {
    if (is_file($filename) && !unlink($filename)) {
        throw new RuntimeException("Unable to remove file: {$filename}");
    }
}

saveJson($profileDir . '/profile.json', [
    'formatVersion' => FORMAT_VERSION,
    'hl7Version' => stringOf($profileSource, 'hl7Version', 'profile.json'),
    'name' => stringOf($profileSource, 'name', 'profile.json'),
    'base' => stringOf($profileSource, 'base', 'profile.json'),
    'generatedFrom' => 'schemas/' . basename((string) realpath($schemaDir)),
    'documents' => (object) $documents,
]);

foreach ($structures as $filename => $structure) {
    saveJson("{$profileDir}/structures/{$filename}.json", $structure);
}

saveJson($profileDir . '/messageType.json', $profileMessageType);
saveJson($profileDir . '/eventDesc.json', $profileEventDesc);
saveJson($profileDir . '/segments.json', $profileSegments);
saveJson($profileDir . '/fields.json', $profileFields);
saveJson($profileDir . '/dataTypes.json', $profileDataTypes);
saveJson($profileDir . '/hl7tables.json', $profileTables);
saveJson($profileDir . '/hl7tables-with-description.json', $hl7tables);

echo count($structures) . ' messages, ' . count($profileSegments) . ' segments, ' . count($profileFields) . ' fields, '
    . count($profileDataTypes) . ' datatypes, ' . count($profileTables) . " tables.\n";

foreach ($missingTables as $table => $citedBy) {
    fwrite(STDERR, "Warning: table {$table} is not in hl7tables.json (cited by " . implode(', ', $citedBy) . ")\n");
}

foreach (warn() as $warning) {
    fwrite(STDERR, "Warning: {$warning}\n");
}

echo "Done.\n";
