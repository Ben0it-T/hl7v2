<?php

declare(strict_types=1);

/**
 * Update JSON schemas from Appendix A.
 *
 * HL7 Messaging Standard Version 2.x : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 *
 * Updates:
 * - Field metadata
 * - Segment metadata
 *
 * Usage:
 * php 04-json-schemas-update-from-appendix-a.php \
 *     --appendix-dir=<directory> \
 *     --json-dir=<directory>
 *
 * Example:
 * php 04-json-schemas-update-from-appendix-a.php \
 *     --appendix-dir=../schemas/appendixA-2.5 \
 *     --json-dir=../schemas/json-2.5-sun
 */

$options = getopt('', [
    'appendix-dir:',
    'json-dir:',
]);

$appendixDir = $options['appendix-dir'] ?? null;
$jsonDir = $options['json-dir'] ?? null;

if (!is_string($appendixDir) || $appendixDir === '') {
    throw new RuntimeException(
        'Missing --appendix-dir option.'
    );
}

if (!is_string($jsonDir) || $jsonDir === '') {
    throw new RuntimeException(
        'Missing --json-dir option.'
    );
}

if (!is_dir($appendixDir)) {
    throw new RuntimeException(
        "Directory not found: {$appendixDir}"
    );
}

if (!is_dir($jsonDir)) {
    throw new RuntimeException(
        "Directory not found: {$jsonDir}"
    );
}

/**
 * Load CSV file.
 *
 * @return array<int, array<int, string|null>>
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

    while (($row = fgetcsv($handle, 1000, ';', '"')) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

/**
 * Load JSON
 *
 * @return array<string, mixed>
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
 * Save JSON
 *
 * @param array<string, mixed> $data
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



//
// DATA ELEMENT NAMES (FIELDS)
//

echo "Updating fields from Appendix A...\n";

// load Appendix A data element names (fields)
$rows = loadCsv($appendixDir . '/data-element-names.csv');
$colSeg = array_column($rows, 2);
$colSeq = array_column($rows, 3);
array_multisort($colSeg, SORT_ASC, $colSeq, SORT_ASC, $rows);

// load json fields shemas
$dataFields = loadJson($jsonDir . '/fields/fields.json');

$count = 0;

foreach ($rows as $row) {
    if (count($row) < 9) {
        continue;
    }

    list($description, $item, $segmt, $seq, $len, $dt, $rep, $table, $chapter) = $row;

    if ($segmt === 'Seg' && $seq === 'Seq#') {
        continue;
    }

    $fieldName = trim($segmt) . '.' . trim($seq);

    if (!isset($dataFields[$fieldName])) {
        echo "Info: field {$fieldName} not found.\n";
        continue;
    }

    $count++;

    // Update if needed
    if (trim($dt) !== '' && strtolower(trim(substr($dt, 0, 5))) !== 'varie' && $dataFields[$fieldName]['Type'] !== trim($dt)) {
        $dataFields[$fieldName]['Type'] = trim($dt);
    }

    if (trim($item) !== '' && $dataFields[$fieldName]['Item'] !== trim($item)) {
        $dataFields[$fieldName]['Item'] = sprintf('%05d', (int) trim($item));
    }

    $tableId = sprintf('%04d', (int) trim($table));
    if (trim($table) !== '' && $dataFields[$fieldName]['Table'] !== 'HL7' . $tableId) {
        $dataFields[$fieldName]['Table'] = 'HL7' . $tableId;
    }

    if (trim($len) !== '' && $dataFields[$fieldName]['maxLength'] !== trim($len)) {
        $dataFields[$fieldName]['maxLength'] = trim($len);
    }

    // Add chapter
    $dataFields[$fieldName]['Chapter'] = trim($chapter);
}

saveJson($jsonDir . '/fields/fields.json', $dataFields);
echo "- Fields: {$count}\n";



//
// SEGMENTS
//

echo "Updating segments from Appendix A...\n";

// load Appendix A segments
$rows = loadCsv($appendixDir . '/segments.csv');

// load json fields shemas
$dataSegments = loadJson($jsonDir . '/segments/segments.json');

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

saveJson($jsonDir . '/segments/segments.json', $dataSegments);
echo "- Segments: {$count}\n";

echo "Done.\n";
