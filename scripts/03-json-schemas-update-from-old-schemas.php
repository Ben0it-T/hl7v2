<?php

declare(strict_types=1);

/**
 * Update JSON schemas from legacy JSON schemas.
 *
 * Only from HL7 v2.3.1, v2.4, v2.5, v2.5.1 messaging schemas to Sun_HL7v2xsd.
 *
 * Usage:
 * php 03-json-schemas-update-from-old-schemas.php \
 *     --source-dir=<directory> \
 *     --target-dir=<directory>
 *
 * Example:
 * php 03-json-schemas-update-from-old-schemas.php \
 *     --source-dir=../schemas/json-2.5-org \
 *     --target-dir=../schemas/json-2.5-sun
 */

$options = getopt('', [
    'source-dir:',
    'target-dir:',
]);

$sourceDir = $options['source-dir'] ?? null;
$targetDir = $options['target-dir'] ?? null;

if (!is_string($sourceDir) || $sourceDir === '') {
    throw new RuntimeException(
        'Missing --source-dir option.'
    );
}

if (!is_string($targetDir) || $targetDir === '') {
    throw new RuntimeException(
        'Missing --target-dir option.'
    );
}

if (!is_dir($sourceDir)) {
    throw new RuntimeException(
        "Directory not found: {$sourceDir}"
    );
}

if (!is_dir($targetDir)) {
    throw new RuntimeException(
        "Directory not found: {$targetDir}"
    );
}

if ($sourceDir === $targetDir) {
    throw new RuntimeException(
        'Source and target directories must be different.'
    );
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
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException(
            "Unable to encode JSON: {$filename}"
        );
    }

    file_put_contents($filename, $json);
}



//
// DATATYPES
//

echo "Updating datatypes...\n";

$dataSource = loadJson($sourceDir . '/dataTypes/dataTypes.json');
$dataTarget = loadJson($targetDir . '/dataTypes/dataTypes.json');

$hasChanged = false;
$changes = '';

foreach ($dataSource as $dtKey => $dtVal) {
    if (isset($dtVal['components']) && is_array($dtVal['components'])) {
        // components
        foreach ($dtVal['components'] as $component) {
            $dataType  = $component['dataType'];
            $minOccurs = $component['minOccurs'];
            $maxOccurs = $component['maxOccurs'];

            if (!isset($dataTarget[$dtKey]['components'])) {
                continue;
            }

            for ($i = 0; $i < count($dataTarget[$dtKey]['components']); $i++) {
                if ($dataTarget[$dtKey]['components'][$i]['dataType'] !== $dataType) {
                    continue;
                }

                // minOccurs
                if ($dataTarget[$dtKey]['components'][$i]['minOccurs'] !== $minOccurs) {
                    $hasChanged = true;
                    $changes .= sprintf("%s: minOccurs (%s -> %s)\n", $dataType, $dataTarget[$dtKey]['components'][$i]['minOccurs'], $minOccurs);
                    $dataTarget[$dtKey]['components'][$i]['minOccurs'] = $minOccurs;
                }

                // maxOccurs
                if ($dataTarget[$dtKey]['components'][$i]['maxOccurs'] !== $maxOccurs) {
                    $hasChanged = true;
                    $changes .= sprintf("%s: maxOccurs (%s -> %s)\n", $dataType, $dataTarget[$dtKey]['components'][$i]['maxOccurs'], $maxOccurs);
                    $dataTarget[$dtKey]['components'][$i]['maxOccurs'] = $maxOccurs;
                }

                break;
            }
        }
    } else {
        // datatypes
        foreach ($dtVal as $key => $val) {
            if (isset($dataTarget[$dtKey]) && $val !== '' && $dataTarget[$dtKey][$key] !== $val) {
                $hasChanged = true;
                $changes .= sprintf("%s: %s (%s -> %s)\n", $dtKey, $key, $dataTarget[$dtKey][$key], $val);
                $dataTarget[$dtKey][$key] = $val;
            }
        }
    }
}

if ($hasChanged) {
    echo $changes . "\n";
    saveJson($targetDir . '/dataTypes/dataTypes.json', $dataTarget);
}



//
// FIELDS
//

echo "Updating fields...\n";

$dataSource = loadJson($sourceDir . '/fields/fields.json');
$dataTarget = loadJson($targetDir . '/fields/fields.json');

$hasChanged = false;
$changes = '';

foreach ($dataSource as $fieldKey => $fieldVal) {
    foreach ($fieldVal as $key => $val) {
        if ($val !== '' && $dataTarget[$fieldKey][$key] !== $val) {
            $hasChanged = true;
            $changes .= sprintf("%s: %s (%s -> %s)\n", $fieldKey, $key, $dataTarget[$fieldKey][$key], $val);
            $dataTarget[$fieldKey][$key] = $val;
        }
    }
}

if ($hasChanged) {
    echo $changes . "\n";
    saveJson($targetDir . '/fields/fields.json', $dataTarget);
}



//
// SEGMENTS
//

echo "Updating segments...\n";

$dataSource = loadJson($sourceDir . '/segments/segments.json');
$dataTarget = loadJson($targetDir . '/segments/segments.json');

$hasChanged = false;
$changes = '';

foreach ($dataSource as $segKey => $segVal) {
    if (!isset($segVal['fields'])) {
        continue;
    }

    foreach ($segVal['fields'] as $field) {
        $fieldName = $field['field'];
        $minOccurs = $field['minOccurs'];
        $maxOccurs = $field['maxOccurs'];

        if (!isset($dataTarget[$segKey]['fields'])) {
            continue;
        }

        for ($i = 0; $i < count($dataTarget[$segKey]['fields']); $i++) {
            if ($dataTarget[$segKey]['fields'][$i]['field'] !== $fieldName) {
                continue;
            }

            if ($dataTarget[$segKey]['fields'][$i]['minOccurs'] !== $minOccurs) {
                $hasChanged = true;
                $changes .= sprintf("%s: minOccurs (%s -> %s)\n", $fieldName, $dataTarget[$segKey]['fields'][$i]['minOccurs'], $minOccurs);
                $dataTarget[$segKey]['fields'][$i]['minOccurs'] = $minOccurs;
            }

            if ($dataTarget[$segKey]['fields'][$i]['maxOccurs'] !== $maxOccurs) {
                $hasChanged = true;
                $changes .= sprintf("%s: maxOccurs (%s -> %s)\n", $fieldName, $dataTarget[$segKey]['fields'][$i]['maxOccurs'], $maxOccurs);
                $dataTarget[$segKey]['fields'][$i]['maxOccurs'] = $maxOccurs;
            }

            break;
        }
    }
}

if ($hasChanged) {
    echo $changes . "\n";
    saveJson($targetDir . '/segments/segments.json', $dataTarget);
}



//
// STRUCTURES
//

echo "Updating structures...\n";

$files = scandir($sourceDir . '/structures', SCANDIR_SORT_ASCENDING);

if ($files === false) {
    throw new RuntimeException(
        "Unable to read directory: {$sourceDir}/structures"
    );
}

foreach ($files as $file ) {
    if (!is_file($sourceDir . '/structures/' . $file)) {
        continue;
    }

    if (!is_file($targetDir . '/structures/' . $file)) {
        continue;
    }

    $dataSource = loadJson($sourceDir . '/structures/' . $file);
    $dataTarget = loadJson($targetDir . '/structures/' . $file);

    $hasChanged = false;
    $changes = '';

    foreach ($dataSource as $key => $group) {

        if (!isset($group['elements'])) {
            continue;
        }

        foreach ($group['elements'] as $element) {
            $segType   = (isset($element['segment'])) ? 'segment' : 'group';
            $segVal    = $element[$segType];
            $minOccurs = $element['minOccurs'];
            $maxOccurs = $element['maxOccurs'];

            if (!isset($dataTarget[$key]["elements"])) {
                continue;
            }

            for ($i=0; $i < count($dataTarget[$key]['elements']); $i++) {
                $theType = (isset($dataTarget[$key]['elements'][$i]['segment'])) ? 'segment' : 'group';
                $theVal = $dataTarget[$key]['elements'][$i][$theType];

                if ($segVal !== $theVal) {
                    continue;
                }

                if ($dataTarget[$key]['elements'][$i]['minOccurs'] !== $minOccurs) {
                    $hasChanged = true;
                    $changes .= sprintf("%s: minOccurs (%s -> %s)\n", $segVal, $dataTarget[$key]['elements'][$i]['minOccurs'], $minOccurs);
                    $dataTarget[$key]['elements'][$i]['minOccurs'] = $minOccurs;
                }

                if ($dataTarget[$key]['elements'][$i]['maxOccurs'] !== $maxOccurs) {
                    $hasChanged = true;
                    $changes .= sprintf("%s: maxOccurs (%s -> %s)\n", $segVal, $dataTarget[$key]['elements'][$i]['maxOccurs'], $maxOccurs);
                    $dataTarget[$key]['elements'][$i]['maxOccurs'] = $maxOccurs;
                }

                break;
            }

        }
    }

    if ($hasChanged) {
        echo "- $file : updated.\n";
        echo $changes . "\n";
        saveJson($targetDir . '/structures/' . $file, $dataTarget);
    }

}


echo "Done.\n";
