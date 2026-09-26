<?php

declare(strict_types=1);

/**
 * Update JSON schemas from Appendix
 *
 * HL7 Messaging Standard Version 2.x : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 *
 * Updates:
 * - Field metadata
 * - Segment metadata
 *
 * Usage:
 * php 04-json-schemas-update-from-appendix.php \
 *     --appendix-dir=<directory> \
 *     --json-dir=<directory>
 *
 * Example:
 * php 04-json-schemas-update-from-appendix.php \
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

// load data element names (fields)
$rows = loadCsv($appendixDir . '/data-element-names.csv');
$colSeg = array_column($rows, 0);
$colSeq = array_column($rows, 1);
array_multisort($colSeg, SORT_ASC, $colSeq, SORT_ASC, $rows);

// load json shemas
$dataFields = loadJson($jsonDir . '/fields/fields.json');
$dataSegments = loadJson($jsonDir . '/segments/segments.json');
$dataTypes = loadJson($jsonDir . '/dataTypes/dataTypes.json');

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
    if (trim($dt) !== '' && strtolower(trim(substr($dt, 0, 5))) !== 'varie' && $dataFields[$fieldName]['Type'] !== trim($dt)) {
        $dataFields[$fieldName]['Type'] = trim($dt);
    }

    if (trim($item) !== '' && $dataFields[$fieldName]['Item'] !== trim($item)) {
        $dataFields[$fieldName]['Item'] = sprintf('%05d', (int) trim($item));
    }

    $tableId = sprintf('%04d', (int) trim($tbl));
    if (trim($tbl) !== '' && $dataFields[$fieldName]['Table'] !== 'HL7' . $tableId) {
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

            if ($opt === "W") {
                $dataSegments[$seg]['fields'][$idx]['minOccurs'] = "0";
                $dataSegments[$seg]['fields'][$idx]['maxOccurs'] = "0";
            }

            break;
        }
    }

}

saveJson($jsonDir . '/fields/fields.json', $dataFields);
echo "- Fields: {$count}\n";



//
// SEGMENTS
//

echo "Updating segments from Appendix A...\n";

// load segments
$rows = loadCsv($appendixDir . '/segments.csv');

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



//
// DATATYPES
//

echo "Updating dataTypes from Appendix...\n";

// load dataTypes
$rows = loadCsv($appendixDir . '/datatypes.csv');

foreach ($rows as $row) {
    if (count($row) < 9) {
        continue;
    }

    list($cmp, $seq, $pos, $len, $dt, $opt, $tbl, $name, $chapter) = $row;

    if ($cmp === 'CMP' && $seq === 'SEQ' && $pos === 'POS') {
        continue;
    }

    if (isset($dataTypes[$pos])) {

        if (trim($dt) !== '' && strtolower(trim(substr($dt, 0, 5))) !== 'varie' && $dataTypes[$pos]['Type'] !== trim($dt)) {
            $dataTypes[$pos]['Type'] = trim($dt);
        }

        $tableId = sprintf('%04d', (int) trim($tbl));
        if (trim($tbl) !== '' && $dataTypes[$pos]['Table'] !== 'HL7' . $tableId) {
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

            break;
        }


    }

}

saveJson($jsonDir . '/dataTypes/dataTypes.json', $dataTypes);
echo "- dataTypes: done\n";

echo "Done.\n";



