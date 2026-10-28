<?php

declare(strict_types=1);

/**
 * Update JSON schemas from the standard extract, apply schema patches and publish.
 *
 * HL7 Messaging Standard Version 2.x : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 *
 * The standard extract is a set of CSV tables built by hand from the normative
 * HL7 PDFs (appendices and chapters), and cross-checked.
 *
 * Sources are not versioned and stay outside the repository:
 * the HL7 PDFs, the HL7 and Sun XSD schemas and the standard extract carry
 * the HL7 copyright notice (e.g. "Health Level Seven, Version 2.3 © 1997.
 * All rights reserved."). Obtain them from HL7. Keep a backup of the standard
 * extract: it is built by hand and exists nowhere else.
 * Only the derived JSON schemas are versioned.
 *
 * Updates (--json-dir):
 * - Field metadata (data-element-names.csv)
 * - Segment metadata (segments.csv)
 * - Datatype metadata (datatypes.csv)
 *
 * Rules:
 * - A 'varies' datatype is kept as 'varies'.
 * - Table 0 (or no table) means no table.
 * - Usage sets the cardinality it implies: R → minOccurs 1; O, B → minOccurs 0;
 *   X, W → [0..0] (fields; components: R, O, B, X).
 *
 * Publish (optional, --publish-dir):
 * - Applies <publish-dir>/patches.json, if any, to the updated schema.
 *   A patch is a partial schema (dataTypes, fields, segments) merged key by key;
 *   lists are replaced as a whole and a null value removes the key.
 * - Replaces the generated content of the published schema
 *   (dataTypes.json, fields.json, segments.json, structures/) with that of --json-dir.
 * - Hand-maintained files of the published schema
 *   (messageType.json, eventDesc.json, hl7tables.json, patches.json) are left untouched.
 *
 * Usage:
 * php 04-json-schemas-update-from-standard.php \
 *     --standard-dir=<directory> \
 *     --json-dir=<directory> \
 *     [--publish-dir=<directory>]
 *
 * Example:
 * php 04-json-schemas-update-from-standard.php \
 *     --standard-dir=../schemas/hl7v2std/2.5 \
 *     --json-dir=../schemas/json-2.5-sun \
 *     --publish-dir=../schemas/json-2.5
 */

$options = getopt('', [
    'standard-dir:',
    'json-dir:',
    'publish-dir:',
]);

$standardDir = $options['standard-dir'] ?? null;
$jsonDir = $options['json-dir'] ?? null;
$publishDir = $options['publish-dir'] ?? null;
$patches = [];

// getopt() drops an option given without a value (--publish-dir=).
if ($publishDir === null && preg_grep('/^--publish-dir(=|$)/', $argv) !== []) {
    $publishDir = '';
}

if (
    !is_string($standardDir) || $standardDir === ''
    || !is_string($jsonDir) || $jsonDir === ''
    || ($publishDir !== null && (!is_string($publishDir) || $publishDir === ''))
) {
    fwrite(
        STDERR,
        "Usage: php 04-json-schemas-update-from-standard.php --standard-dir=<directory> --json-dir=<directory> [--publish-dir=<directory>]\n"
    );
    exit(1);
}

if (!is_dir($standardDir)) {
    throw new RuntimeException(
        "Directory not found: {$standardDir}"
    );
}

if (!is_dir($jsonDir)) {
    throw new RuntimeException(
        "Directory not found: {$jsonDir}"
    );
}

// Generated content replaced on publish.
const GENERATED_FILES = ['dataTypes.json', 'fields.json', 'segments.json'];
const GENERATED_DIRECTORIES = ['structures'];

// Hand-maintained files expected in a published schema.
const PUBLISHED_SCHEMA_FILES = ['messageType.json', 'eventDesc.json'];

// Schema patch file of a published schema, and the schemas it may patch.
const PATCH_FILE = 'patches.json';
const PATCHABLE_SCHEMAS = ['dataTypes', 'fields', 'segments'];

if ($publishDir !== null) {
    if (!is_dir($publishDir)) {
        throw new RuntimeException(
            "Directory not found: {$publishDir}"
        );
    }

    if (realpath($publishDir) === realpath($jsonDir)) {
        throw new RuntimeException(
            'JSON and publish directories must be different.'
        );
    }

    foreach (PUBLISHED_SCHEMA_FILES as $file) {
        if (!is_file($publishDir . '/' . $file)) {
            throw new RuntimeException(
                "Not a published schema directory (missing {$file}): {$publishDir}"
            );
        }
    }

    foreach (GENERATED_FILES as $file) {
        if (!is_file($jsonDir . '/' . $file)) {
            throw new RuntimeException(
                "File not found: {$jsonDir}/{$file}"
            );
        }
    }

    foreach (GENERATED_DIRECTORIES as $directory) {
        if (!is_dir($jsonDir . '/' . $directory)) {
            throw new RuntimeException(
                "Directory not found: {$jsonDir}/{$directory}"
            );
        }
    }

    if (is_file($publishDir . '/' . PATCH_FILE)) {
        $patches = loadJson($publishDir . '/' . PATCH_FILE);

        foreach ($patches as $schemaName => $patch) {
            if (!in_array($schemaName, PATCHABLE_SCHEMAS, true) || !is_array($patch)) {
                throw new RuntimeException(
                    "Invalid schema patch: {$schemaName}"
                );
            }
        }
    }
}

/**
 * Load CSV file.
 *
 * Blank lines are skipped.
 *
 * @param string $filename CSV filename (';' separator, '"' enclosure).
 *
 * @return list<non-empty-list<string>>
 *
 * @throws RuntimeException If the file does not exist or cannot be opened.
 */
function loadCsv(string $filename): array
{
    if (!is_file($filename)) {
        throw new RuntimeException(
            "File not found: {$filename}"
        );
    }

    $rows = [];

    $handle = fopen($filename, 'r');

    if ($handle === false) {
        throw new RuntimeException(
            "Unable to open file: {$filename}"
        );
    }

    while (is_array($row = fgetcsv($handle, 1000, ';', '"'))) {
        // A blank line is read as [null].
        if (in_array(null, $row, true)) {
            continue;
        }

        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

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
 * @param array<string, mixed> $data Data to save.
 *
 * @return void
 *
 * @throws RuntimeException If the data cannot be encoded as JSON.
 */
function saveJson(string $filename, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException(
            "Unable to encode JSON: {$filename}"
        );
    }

    file_put_contents($filename, $json);
}

/**
 * Merge a schema patch into a schema.
 *
 * Objects are merged key by key, lists are replaced as a whole
 * and a null value removes the key.
 *
 * @param array<mixed> $schema Schema, or part of a schema.
 * @param array<mixed> $patch Patch to merge, with the same structure as $schema.
 *
 * @return array<mixed> Patched schema.
 */
function mergePatch(array $schema, array $patch): array
{
    foreach ($patch as $key => $value) {
        if ($value === null) {
            unset($schema[$key]);
            continue;
        }

        if (
            is_array($value) && !array_is_list($value)
            && isset($schema[$key]) && is_array($schema[$key]) && !array_is_list($schema[$key])
        ) {
            $schema[$key] = mergePatch($schema[$key], $value);
            continue;
        }

        $schema[$key] = $value;
    }

    return $schema;
}

/**
 * Remove directory recursively.
 *
 * Does nothing if the directory does not exist. Symbolic links are removed, not followed.
 *
 * @param string $directory Directory to remove.
 *
 * @return void
 *
 * @throws RuntimeException If a file or directory cannot be removed.
 */
function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $path = $item->getPathname();
        $removed = ($item->isDir() && !$item->isLink()) ? rmdir($path) : unlink($path);

        if (!$removed) {
            throw new RuntimeException(
                "Unable to remove: {$path}"
            );
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException(
            "Unable to remove directory: {$directory}"
        );
    }
}

/**
 * Copy directory recursively.
 *
 * Creates the target directory if it does not exist. Existing files are overwritten.
 *
 * @param string $source Source directory.
 * @param string $target Target directory.
 *
 * @return void
 *
 * @throws RuntimeException If a directory cannot be created or a file cannot be copied.
 */
function copyDirectory(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0777, true)) {
        throw new RuntimeException(
            "Unable to create directory: {$target}"
        );
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $path = $target . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($source) + 1);

        if ($item->isDir()) {
            if (!is_dir($path) && !mkdir($path, 0777, true)) {
                throw new RuntimeException(
                    "Unable to create directory: {$path}"
                );
            }
        } elseif (!copy($item->getPathname(), $path)) {
            throw new RuntimeException(
                "Unable to copy file: {$item->getPathname()}"
            );
        }
    }
}



//
// DATA ELEMENTS (FIELDS)
//

echo "Updating fields from standard extract...\n";

// load data element names (fields)
$rows = loadCsv($standardDir . '/data-element-names.csv');
$colSeg = array_column($rows, 0);
$colSeq = array_column($rows, 1);
array_multisort($colSeg, SORT_ASC, $colSeq, SORT_ASC, $rows);

// load json shemas
$dataFields = loadJson($jsonDir . '/fields.json');
$dataSegments = loadJson($jsonDir . '/segments.json');
$dataTypes = loadJson($jsonDir . '/dataTypes.json');

$count = 0;

foreach ($rows as $row) {
    if (count($row) < 11) {
        continue;
    }

    list($seg, $seq, $pos, $len, $dt, $opt, $rpt, $tbl, $item, $name, $chapter) = $row;

    if ($seg === 'SEG' && $seq === 'SEQ') {
        continue;
    }

    $fieldName = trim($seg) . '.' . trim($seq);

    if (!isset($dataFields[$fieldName])) {
        echo "Info: field {$fieldName} not found.\n";
        continue;
    }

    $count++;

    // Update fields
    if (strtolower(trim(substr($dt, 0, 5))) === 'varie') {
        $dataFields[$fieldName]['Type'] = 'varies';
    } elseif (trim($dt) !== '' && $dataFields[$fieldName]['Type'] !== trim($dt)) {
        $dataFields[$fieldName]['Type'] = trim($dt);
    }

    if (trim($item) !== '') {
        $dataFields[$fieldName]['Item'] = sprintf('%05d', (int) trim($item));
    }

    $tableId = sprintf('%04d', (int) trim($tbl));
    if ((int) trim($tbl) !== 0 && $dataFields[$fieldName]['Table'] !== 'HL7' . $tableId) {
        $dataFields[$fieldName]['Table'] = 'HL7' . $tableId;
    }

    if (trim($len) !== '' && $dataFields[$fieldName]['maxLength'] !== trim($len)) {
        $dataFields[$fieldName]['maxLength'] = trim($len);
    }

    if (strcmp(trim($name), $dataFields[$fieldName]['LongName']) !== 0) {
        $dataFields[$fieldName]['LongName'] = trim($name);
    }

    $dataFields[$fieldName]['Chapter'] = trim($chapter);

    // Update segment
    if (isset($dataSegments[$seg])) {
        foreach ($dataSegments[$seg]['fields'] as $idx => $field) {
            if ($field['field'] !== $fieldName) {
                continue;
            }

            // Repeat
            $maxOccurs = "";
            if ($rpt === '')  { $maxOccurs = "1"; }
            if ($rpt === 'Y') { $maxOccurs = "unbounded"; }
            if (substr($rpt, 0, 2) === "Y/") { $maxOccurs = substr($rpt, 2); }

            if ($dataSegments[$seg]['fields'][$idx]['maxOccurs'] !== $maxOccurs) {
                $dataSegments[$seg]['fields'][$idx]['maxOccurs'] = $maxOccurs;
            }

            // Usage
            $dataSegments[$seg]['fields'][$idx]['usage'] = $opt;

            if ($opt === "R") {
                $dataSegments[$seg]['fields'][$idx]['minOccurs'] = "1";
            }

            if ($opt === "O" || $opt === "B") {
                // Usage O (optional) and B (backward compatibility) are not required: minOccurs is 0
                // HL7 v2.5, 2.5.3.4 and 2.12.6.4.
                $dataSegments[$seg]['fields'][$idx]['minOccurs'] = "0";
            }

            if ($opt === "X" || $opt === "W") {
                // Usage X (not supported) and W (withdrawn) are never sent: [0..0]
                $dataSegments[$seg]['fields'][$idx]['minOccurs'] = "0";
                $dataSegments[$seg]['fields'][$idx]['maxOccurs'] = "0";
            }

            break;
        }
    }

}

saveJson($jsonDir . '/fields.json', $dataFields);
echo "- Fields: {$count}\n";



//
// SEGMENTS
//

echo "Updating segments from standard extract...\n";

// load segments
$rows = loadCsv($standardDir . '/segments.csv');

$count = 0;

foreach ($rows as $row) {
    if (count($row) < 3) {
        continue;
    }

    list($segment, $description, $chapter) = $row;

    if ($segment === 'Segment' && $description === 'Description') {
        continue;
    }

    $segmentName = trim($segment);

    if (!isset($dataSegments[$segmentName])) {
        echo "Info: segment {$segmentName} not found.\n";
        continue;
    }

    $count++;

    $dataSegments[$segmentName]['LongName'] = trim($description);
    $dataSegments[$segmentName]['Chapter'] = trim($chapter);
}

saveJson($jsonDir . '/segments.json', $dataSegments);
echo "- Segments: {$count}\n";



//
// DATATYPES
//

echo "Updating dataTypes from standard extract...\n";

// load dataTypes
$rows = loadCsv($standardDir . '/datatypes.csv');

foreach ($rows as $row) {
    if (count($row) < 9) {
        continue;
    }

    list($cmp, $seq, $pos, $len, $dt, $opt, $tbl, $name, $chapter) = $row;

    if ($cmp === 'CMP' && $seq === 'SEQ' && $pos === 'POS') {
        continue;
    }

    if (isset($dataTypes[$pos])) {

        if (strtolower(trim(substr($dt, 0, 5))) === 'varie') {
            $dataTypes[$pos]['Type'] = 'varies';
        } elseif (trim($dt) !== '' && $dataTypes[$pos]['Type'] !== trim($dt)) {
            $dataTypes[$pos]['Type'] = trim($dt);
        }

        $tableId = sprintf('%04d', (int) trim($tbl));
        if ((int) trim($tbl) !== 0 && $dataTypes[$pos]['Table'] !== 'HL7' . $tableId) {
            $dataTypes[$pos]['Table'] = 'HL7' . $tableId;
        }

        if (trim($len) !== '' && $dataTypes[$pos]['maxLength'] !== trim($len)) {
            $dataTypes[$pos]['maxLength'] = trim($len);
        }

        if (strcmp(trim($name), $dataTypes[$pos]['LongName']) !== 0) {
            $dataTypes[$pos]['LongName'] = trim($name);
        }


        foreach ($dataTypes[$cmp]['components'] as $idx => $component) {
            if ($component['dataType'] !== $pos) {
                continue;
            }

            // Usage
            $dataTypes[$cmp]['components'][$idx]['usage'] = $opt;

            if ($opt === "R") {
                $dataTypes[$cmp]['components'][$idx]['minOccurs'] = "1";
            }

            if ($opt === "O" || $opt === "B") {
                // Usage O (optional) and B (backward compatibility) are not required: minOccurs is 0
                // HL7 v2.5, 2.5.3.4 and 2.12.6.4.
                $dataTypes[$cmp]['components'][$idx]['minOccurs'] = "0";
            }

            if ($opt === "X") {
                // Usage X (not supported) is never sent: [0..0]
                $dataTypes[$cmp]['components'][$idx]['minOccurs'] = "0";
                $dataTypes[$cmp]['components'][$idx]['maxOccurs'] = "0";
            }

            break;
        }


    }

}

saveJson($jsonDir . '/dataTypes.json', $dataTypes);
echo "- dataTypes: done\n";



//
// SCHEMA PATCHES
//

if ($patches !== []) {
    echo "Applying schema patches from {$publishDir}/" . PATCH_FILE . "...\n";

    foreach ($patches as $schemaName => $patch) {
        $filename = $jsonDir . '/' . $schemaName . '.json';
        $schema = mergePatch(loadJson($filename), $patch);
        ksort($schema);
        saveJson($filename, $schema);

        echo "- {$schemaName}: " . count($patch) . " entries\n";
    }
}



//
// PUBLISH
//

if ($publishDir !== null) {
    echo "Publishing JSON schemas to {$publishDir}...\n";

    foreach (GENERATED_FILES as $file) {
        if (!copy($jsonDir . '/' . $file, $publishDir . '/' . $file)) {
            throw new RuntimeException(
                "Unable to copy file: {$jsonDir}/{$file}"
            );
        }

        echo "- {$file}\n";
    }

    foreach (GENERATED_DIRECTORIES as $directory) {
        removeDirectory($publishDir . '/' . $directory);
        copyDirectory($jsonDir . '/' . $directory, $publishDir . '/' . $directory);
        echo "- {$directory}/\n";
    }

    echo "Review changes with: git diff {$publishDir}\n";
}

echo "Done.\n";
