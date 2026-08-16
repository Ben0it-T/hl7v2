<?php

declare(strict_types=1);

/**
 * Update JSON schemas.
 *
 * From HL7 2.5 IHE PAM to HL7 2.5 IHE PAM FR 2.11.2.
 *
 * Usage:
 * php 05-update-schemas-to-ihe-pam-fr.php \
 *     --input-dir=<directory> \
 *     --output-dir=<directory>
 *
 * Example:
 * php 05-update-schemas-to-ihe-pam-fr.php \
 *     --input-dir=../schemas/json-2.5 \
 *     --output-dir=../schemas/json-2.5-IHEPAMFR
 */

$options = getopt('', [
    'input-dir:',
    'output-dir:',
]);

$inputDir = $options['input-dir'] ?? null;
$outputDir = $options['output-dir'] ?? null;

if (!is_string($inputDir) || $inputDir === '') {
    throw new RuntimeException(
        'Missing --input-dir option.'
    );
}

if (!is_string($outputDir) || $outputDir === '') {
    throw new RuntimeException(
        'Missing --output-dir option.'
    );
}

if (!is_dir($inputDir)) {
    throw new RuntimeException(
        "Directory not found: {$inputDir}"
    );
}

if ($inputDir === $outputDir) {
    throw new RuntimeException(
        'Input and output directories must be different.'
    );
}

/**
 * Load JSON.
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
 * Create directory.
 *
 */
function createDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException(
            "Unable to create directory: {$directory}"
        );
    }
}

/**
 * Save JSON.
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

/**
 * Insert a segment after another segment
 *
 * @param array $elements
 * @param string $segment
 * @param array $newElements
 *
 * @return array $elements
 */
function insertAfterSegment(array $elements, string $segment, array $newElements): array {
    foreach ($elements as $index => $element) {
        if (($element["segment"] ?? null) === $segment) {
            array_splice($elements, $index + 1, 0, $newElements);
            return $elements;
        }
    }

    throw new RuntimeException("Segment '$segment' not found");
}

/**
 * Insert a segment before another segment
 *
 * @param array $elements
 * @param string $segment
 * @param array $newElements
 *
 * @return array $elements
 */
function insertBeforeSegment(array $elements, string $segment, array $newElements): array {
    foreach ($elements as $index => $element) {
        if (($element["segment"] ?? null) === $segment) {
            array_splice($elements, $index, 0, $newElements);
            return $elements;
        }
    }

    throw new RuntimeException("Segment '$segment' not found");
}

/**
 * Create rules
 *
 * @param array $fields
 * @param array $attributes
 *
 * @return array
 */
function rules(array $fields, array $attributes): array
{
    return array_fill_keys($fields, $attributes);
}

/**
 * Apply segment rules
 *
 * @param array<string,mixed> $schemas
 * @param string $segmentName
 * @param array<string,array<string,string>> $rules
 *
 * @return array<string,mixed>
 */
function applySegmentRules(array $schemas, string $segmentName, array $rules): array
{
    if (!isset($schemas[$segmentName]['fields'])) {
        return $schemas;
    }

    foreach ($schemas[$segmentName]['fields'] as $key => $field) {
        $fieldName = $field['field'] ?? null;

        if ($fieldName === null || !isset($rules[$fieldName])) {
            continue;
        }

        $schemas[$segmentName]['fields'][$key] = array_merge(
            $schemas[$segmentName]['fields'][$key],
            $rules[$fieldName]
        );
    }

    return $schemas;
}

/**
 * Apply data type rules
 *
 * @param array<string,mixed> $schemas
 * @param string $dataType
 * @param array<string,mixed> $rules
 *
 * @return array<string,mixed>
 */
function applyDataTypeRules(array $schemas, string $dataType, array $rules): array
{
    if (!isset($schemas[$dataType]['components'])) {
        return $schemas;
    }

    foreach ($schemas[$dataType]['components'] as $key => $element) {
        $identifier = $element['dataType'] ?? null;

        if ($identifier === null || !isset($rules[$identifier])) {
            continue;
        }

        $schemas[$dataType]['components'][$key] = array_merge(
            $schemas[$dataType]['components'][$key],
            $rules[$identifier]
        );
    }

    return $schemas;
}

/**
 * Apply ITI rules
 *
 *
 * @param array<string,mixed> $msgStruct
 * @param string $eventName
 * @param array<string,mixed> $itiRules
 *
 * @return array<string,mixed>
 */
function applyItiRules(array $msgStruct, string $eventName, array $itiRules): array
{
    if (!isset($itiRules[$eventName])) {
        return $msgStruct;
    }

    $rule = $itiRules[$eventName];

    /*
     * Complete structure replacement
     */
    if (isset($rule['replaceStructure'])) {
        return $rule['replaceStructure'];
    }

    /*
     * Element updates
     */

    if (isset($rule['elementUpdates'])) {
        foreach ($rule['elementUpdates'] as $update) {
            $structure = $update['structure'];
            $type = $update['type']; // segment, group

            if (!isset($msgStruct[$structure])) {
                continue;
            }

            if (!isset($msgStruct[$structure]['elements'])) {
                continue;
            }

            foreach ($msgStruct[$structure]['elements'] as $key => $element) {
                if (!isset($element[$type])) {
                    continue;
                }

                if ($element[$type] !== $update['name']) {
                    continue;
                }

                $msgStruct[$structure]['elements'][$key] =
                    mergeElementUpdate(
                        $msgStruct[$structure]['elements'][$key],
                        $update
                    );

            }
        }

    }

    /*
     * Occurrence updates
     */

    if (isset($rule['occurrenceUpdates'])) {
        foreach ($rule['occurrenceUpdates'] as $update) {
            $structure = $update['structure'];
            $count = 0;

            if (!isset($msgStruct[$structure])) {
                continue;
            }

            if (!isset($msgStruct[$structure]['elements'])) {
                continue;
            }

            foreach ($msgStruct[$structure]['elements'] as $key => $element) {
                if (($element['segment'] ?? null) !== $update['segment']) {
                    continue;
                }

                $count++;

                if ($count !== $update['occurrence']) {
                    continue;
                }

                $msgStruct[$structure]['elements'][$key] =
                    mergeElementUpdate(
                        $msgStruct[$structure]['elements'][$key],
                        $update
                    );

                break;
            }
        }
    }


    /*
     * Insertions
     */

    if (isset($rule['insertions'])) {
        foreach ($rule['insertions'] as $insertion) {
            $structure = $insertion['structure'];

            if (!isset($msgStruct[$structure])) {
                continue;
            }

            if (!isset($msgStruct[$structure]['elements'])) {
                continue;
            }

            $msgStruct[$structure]['elements'] =
                insertAfterSegment(
                    $msgStruct[$structure]['elements'],
                    $insertion['after'],
                    $insertion['segments']
                );
        }
    }

    return $msgStruct;
}


/**
 * Merge element update.
 *
 * @param array<string,mixed> $element
 * @param array<string,mixed> $update
 *
 * @return array<string,mixed>
 */
function mergeElementUpdate(array $element, array $update): array
{
    return array_merge(
        $element,
        [
            'minOccurs' => $update['minOccurs'],
            'maxOccurs' => $update['maxOccurs'],
            'Usage'     => $update['Usage'],
        ]
    );
}



// Load json schemas
$messageType       = loadJson($inputDir . "/messageType.json");
$eventDesc         = loadJson($inputDir . "/eventDesc.json");
$segmentsSchemas   = loadJson($inputDir . "/segments/segments.json");
$fieldsSchemas     = loadJson($inputDir . "/fields/fields.json");
$dataTypesSchemas  = loadJson($inputDir . "/dataTypes/dataTypes.json");
$structuresSchemas = [];

// Create output directories
createDirectory($outputDir . "/dataTypes");
createDirectory($outputDir . "/fields");
createDirectory($outputDir . "/segments");
createDirectory($outputDir . "/structures");



/**
 * Create Z-segments
 * -------------------------------------
 * ZBE : Action sur un mouvement (Movement segment)
 * ZFA : Statut DMP du patient
 * ZFP : Situation professionnelle
 * ZFV : Complément d'information sur la venue
 * ZFM : Mouvement PMSI
 * ZFD : Complément démographique
 * ZFS : Mode légal de soins en psychiatrie
 */

//
// ZBE : Action sur un mouvement - Movement segment
//

// Segment
$ZBEsegment = [
    "ZBE" => [
        "fields" => [
            ["field" => "ZBE.1", "minOccurs" => "1", "maxOccurs" => "unbounded", "Usage" => "R"],
            ["field" => "ZBE.2", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZBE.3", "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZBE.4", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZBE.5", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZBE.6", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"],
            ["field" => "ZBE.7", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"],
            ["field" => "ZBE.8", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"],
            ["field" => "ZBE.9", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
        ],
        "LongName" => "Movement segment",
        "Chapter" => ""
    ]
];

// Fields
$ZBEfields = [
    "ZBE.1"  => ["Item" => "", "Type" => "EI", "Table" => "", "LongName" => "Movement ID", "maxLength" => "427", "Chapter" => ""],
    "ZBE.2"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Start of Movement Date/Time", "maxLength" => "26", "Chapter" => ""],
    "ZBE.3"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "End of Movement Date/Time", "maxLength" => "26", "Chapter" => ""],
    "ZBE.4"  => ["Item" => "", "Type" => "ID", "Table" => "IHE-FRANCE-ZBE-4", "LongName" => "Action on the Movement", "maxLength" => "6", "Chapter" => ""],
    "ZBE.5"  => ["Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Indicator Historical movement", "maxLength" => "1", "Chapter" => ""],
    "ZBE.6"  => ["Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Original trigger event code", "maxLength" => "3", "Chapter" => ""],
    "ZBE.7"  => ["Item" => "", "Type" => "XON", "Table" => "", "LongName" => "Ward of medical responsibility in the period starting with this movement", "maxLength" => "250", "Chapter" => ""],
    "ZBE.8"  => ["Item" => "", "Type" => "XON", "Table" => "", "LongName" => "Ward of care responsibility in the period starting with this movement", "maxLength" => "250", "Chapter" => ""],
    "ZBE.9"  => ["Item" => "", "Type" => "CWE", "Table" => "IHE-FRANCE-ZBE-9", "LongName" => "Nature of this movement", "maxLength" => "3", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZBEsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZBEfields);

echo "Create Z-segments: done.\n";

//
// ZFA : Statut DMP du patient
//

// Segment
$ZFAsegment = [
    "ZFA" => [
        "fields" => [
            ["field" => "ZFA.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.3",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.4",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFA.5",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFA.6",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFA.7",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFA.8",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFA.9",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.10", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.11", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFA.12", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
        ],
        "LongName" => "Statut DMP",
        "Chapter" => ""
    ]
];

// Fields
$ZFAfields = [
    "ZFA.1"  => ["Item" => "", "Type" => "ID", "Table" => "IHE-FRANCE-ZFA-1", "LongName" => "Statut du DMP du patient", "maxLength" => "20", "Chapter" => ""],
    "ZFA.2"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil du statut du DMP", "maxLength" => "26", "Chapter" => ""],
    "ZFA.3"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fermeture du DMP du patient", "maxLength" => "26", "Chapter" => ""],
    "ZFA.4"  => ["Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Autorisation d'accès valide au DMP du patient pour l'établissement", "maxLength" => "1", "Chapter" => ""],
    "ZFA.5"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'état de l'autorisation d'accès au DMP du patient pour l'établissement", "maxLength" => "26", "Chapter" => ""],
    "ZFA.6"  => ["Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Opposition du patient à l'accès en mode bris de glace", "maxLength" => "1", "Chapter" => ""],
    "ZFA.7"  => ["Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Opposition du patient à l'accès en mode centre de régulation", "maxLength" => "1", "Chapter" => ""],
    "ZFA.8"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'état des oppositions du patient", "maxLength" => "26", "Chapter" => ""],
    "ZFA.9"  => ["Item" => "", "Type" => "CWE", "Table" => "IHE-FRANCE-ZFA-9", "LongName" => "Information et opposition à l'alimentation du DMP", "maxLength" => "3", "Chapter" => ""],
    "ZFA.10" => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'information et opposition à l'alimentation", "maxLength" => "26", "Chapter" => ""],
    "ZFA.11" => ["Item" => "", "Type" => "CWE", "Table" => "IHE-FRANCE-ZFA-11", "LongName" => "Information et consentement à la consultation du DMP", "maxLength" => "3", "Chapter" => ""],
    "ZFA.12" => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'information et opposition à la consultation", "maxLength" => "26", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFAsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFAfields);

//
// ZFP : Situation professionnelle
//

// Segment
$ZFPsegment = [
    "ZFP" => [
        "fields" => [
            ["field" => "ZFP.1", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFP.2", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
        ],
        "LongName" => "Situation professionnelle",
        "Chapter" => ""
    ]
];
// Fields
$ZFPfields = [
    "ZFP.1"  => ["Item" => "", "Type" => "ID", "Table" => "IHE-FRANCE-ZFP-1", "LongName" => "Activité socio-professionnelle (nomenclature INSEE)", "maxLength" => "1", "Chapter" => ""],
    "ZFP.2"  => ["Item" => "", "Type" => "ID", "Table" => "IHE-FRANCE-ZFP-2", "LongName" => "Catégorie socio-professionnelle (nomenclature INSEE)", "maxLength" => "2", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFPsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFPfields);

//
// ZFV : Complément d'information sur la venue
//

// Segment
$ZFVsegment = [
    "ZFV" => [
        "fields" => [
            ["field" => "ZFV.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.3",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFV.4",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.5",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.6",  "minOccurs" => "0", "maxOccurs" => "2", "Usage" => "O"],
            ["field" => "ZFV.7",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.8",  "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"],
            ["field" => "ZFV.9",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFV.10", "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"],
            ["field" => "ZFV.11", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
        ],
        "LongName" => "Complément d'information sur la venue",
        "Chapter" => ""
    ]
];

// Fields
$ZFVfields = [
    "ZFV.1"  => ["Item" => "", "Type" => "DLD", "Table" => "HL70113", "LongName" => "Etablissement de provenance et date de dernier séjour dans cet établissement", "maxLength" => "47", "Chapter" => ""],
    "ZFV.2"  => ["Item" => "", "Type" => "CE", "Table" => "HL70430", "LongName" => "Mode de transport de sortie", "maxLength" => "250", "Chapter" => ""],
    "ZFV.3"  => ["Item" => "", "Type" => "IS", "Table" => "", "LongName" => "Type de préadmission", "maxLength" => "2", "Chapter" => ""],
    "ZFV.4"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de début de placement (psy)", "maxLength" => "26", "Chapter" => ""],
    "ZFV.5"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fin de placement (psy)", "maxLength" => "26", "Chapter" => ""],
    "ZFV.6"  => ["Item" => "", "Type" => "XAD", "Table" => "IHE-FRANCE-ZFV-6", "LongName" => "Adresse de l'établissement de provenance ou de destination", "maxLength" => "250", "Chapter" => ""],
    "ZFV.7"  => ["Item" => "", "Type" => "CX", "Table" => "", "LongName" => "NDA de l'établissement de provenance", "maxLength" => "250", "Chapter" => ""],
    "ZFV.8"  => ["Item" => "", "Type" => "CX", "Table" => "", "LongName" => "Numéro d'archives", "maxLength" => "250", "Chapter" => ""],
    "ZFV.9"  => ["Item" => "", "Type" => "IS", "Table" => "", "LongName" => "Mode de sortie personnalisé", "maxLength" => "6", "Chapter" => ""],
    "ZFV.10" => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFV-10", "LongName" => "Code RIM-P du mode légal de soin transmis dans le PV2-3 (Obsolète)", "maxLength" => "2", "Chapter" => ""],
    "ZFV.11" => ["Item" => "", "Type" => "CE", "Table" => "IHE-FRANCE-ZFV-11", "LongName" => "Prise en charge durant le transport", "maxLength" => "250", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFVsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFVfields);

//
// ZFM : Mouvement PMSI
//

// Segment
$ZFMsegment = [
    "ZFM" => [
        "fields" => [
            ["field" => "ZFM.1", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFM.2", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFM.3", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFM.4", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFM.5", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
        ],
        "LongName" => "Mouvement PMSI",
        "Chapter" => ""
    ]
];

// Fields
$ZFMfields = [
    "ZFM.1"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFM-1", "LongName" => "Mode d'entrée PMSI", "maxLength" => "1", "Chapter" => ""],
    "ZFM.2"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFM-2", "LongName" => "Mode de sortie PMSI", "maxLength" => "1", "Chapter" => ""],
    "ZFM.3"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFM-3-4", "LongName" => "Mode de provenance PMSI", "maxLength" => "1", "Chapter" => ""],
    "ZFM.4"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFM-3-4", "LongName" => "Mode de destination PMSI", "maxLength" => "1", "Chapter" => ""],
    "ZFM.5"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFM-5", "LongName" => "Passage par une structure des Urgences (PMSI)", "maxLength" => "1", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFMsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFMfields);

//
// ZFD : Complément démographique
//

// Segment
$ZFDsegment = [
    "ZFD" => [
        "fields" => [
            ["field" => "ZFD.1", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFD.2", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFD.3", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFD.4", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFD.5", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFD.6", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFD.7", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFD.8", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
        ],
        "LongName" => "Complément démographique",
        "Chapter" => ""
    ]
];

// Fields
// PAM FR 2.11.2 specifies maxLength=8, but the datatype
// components (2+2+4) require a serialized HL7 length of 10
// including component separators (^).
$ZFDfields = [
    "ZFD.1"  => ["Item" => "", "Type" => "NA", "Table" => "", "LongName" => "Date Lunaire", "maxLength" => "10", "Chapter" => ""],
    "ZFD.2"  => ["Item" => "", "Type" => "NM", "Table" => "", "LongName" => "Nombre de semaines de gestation", "maxLength" => "16", "Chapter" => ""],
    "ZFD.3"  => ["Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Consentement SMS", "maxLength" => "1", "Chapter" => ""],
    "ZFD.4"  => ["Item" => "", "Type" => "IS", "Table" => "HL70136", "LongName" => "Indicateur de date de naissance corrigée", "maxLength" => "1", "Chapter" => ""],
    "ZFD.5"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFD-5", "LongName" => "Mode d'obtention de l'identité", "maxLength" => "8", "Chapter" => ""],
    "ZFD.6"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date d'interrogation du téléservice INSi", "maxLength" => "26", "Chapter" => ""],
    "ZFD.7"  => ["Item" => "", "Type" => "IS", "Table" => "IHE-FRANCE-ZFD-7", "LongName" => "Type de justificatif d'identité", "maxLength" => "16", "Chapter" => ""],
    "ZFD.8"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fin de validité du document", "maxLength" => "26", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFDsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFDfields);

//
// ZFS : Mode légal de soins en psychiatrie
//

// Segment
$ZFSsegment = [
    "ZFS" => [
        "fields" => [
            ["field" => "ZFS.1", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZFS.2", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZFS.3", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZFS.4", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"],
            ["field" => "ZFS.5", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZFS.6", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
            ["field" => "ZFS.7", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
            ["field" => "ZFS.8", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
        ],
        "LongName" => "Mode légal de soins en psychiatrie",
        "Chapter" => ""
    ]
];

// Fields
$ZFSfields = [
    "ZFS.1"  => ["Item" => "", "Type" => "SI", "Table" => "", "LongName" => "Set ID - ZFS", "maxLength" => "4", "Chapter" => ""],
    "ZFS.2"  => ["Item" => "", "Type" => "EI", "Table" => "", "LongName" => "Identifiant du mode légal de soin", "maxLength" => "427", "Chapter" => ""],
    "ZFS.3"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date et heure du début du mode légal de soin", "maxLength" => "26", "Chapter" => ""],
    "ZFS.4"  => ["Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date et heure de la fin du mode légal de soin", "maxLength" => "26", "Chapter" => ""],
    "ZFS.5"  => ["Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Action du mode légal de soin", "maxLength" => "6", "Chapter" => ""],
    "ZFS.6"  => ["Item" => "", "Type" => "CWE", "Table" => "IHE-FRANCE-ZFS-6", "LongName" => "Mode légal de soins", "maxLength" => "250", "Chapter" => ""],
    "ZFS.7"  => ["Item" => "", "Type" => "CNE", "Table" => "IHE-FRANCE-ZFS-7", "LongName" => "Code RIM-P du mode légal de soin", "maxLength" => "2", "Chapter" => ""],
    "ZFS.8"  => ["Item" => "", "Type" => "FT", "Table" => "", "LongName" => "Commentaire", "maxLength" => "65536", "Chapter" => ""],
];

$segmentsSchemas = array_merge($segmentsSchemas, $ZFSsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFSfields);





/**
 * Update segments & fields
 * -------------------------------------
 *
 *
 */

//
// MSH segment
//

$rules = array_merge(
    rules(
        ['MSH.1','MSH.2','MSH.3','MSH.4','MSH.5','MSH.6','MSH.7','MSH.9','MSH.10','MSH.11','MSH.12'],
        ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R']
    ),
    rules(
        ['MSH.8','MSH.14','MSH.20'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    rules(
        ['MSH.17','MSH.19'],
        ['Usage' => 'RE']
    ),
    [
        'MSH.18' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'C'],
        'MSH.21' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O'],
    ]

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'MSH', $rules);

// 
// EVN segment
//

$rules = [
    'EVN.1' => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    'EVN.3' => ['Usage' => 'C'],
    'EVN.6' => ['Usage' => 'C'],
    'EVN.7' => ['Usage' => 'RE'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'EVN', $rules);

//
// PID segment
//

$rules = array_merge(
    // Forbidden fields (Race, Religion, Ethnic Group)
    rules(
        ['PID.10','PID.17','PID.22'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    rules(
        ['PID.2','PID.4','PID.9','PID.12','PID.19','PID.20','PID.28'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    rules(
        ['PID.7','PID.8','PID.11','PID.18','PID.25','PID.33','PID.35','PID.36'],
        ['Usage' => 'C']
    ),
    [
        'PID.31' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'CE'], // IHE-CP-ITI-FR-2015-110
        'PID.32' => ['minOccurs' => '1', 'maxOccurs' => 'unbounded', 'Usage' => 'R'],
        'PID.38' => ['minOccurs' => '0', 'maxOccurs' => '2', 'Usage' => 'O'],
    ]

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'PID', $rules);

//
// ROL segment
//

$rules = [
    'ROL.1'  => ['Usage' => 'C'],
    'ROL.9'  => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O'],
    'ROL.11' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'ROL', $rules);

//
// NK1 segment
//

$rules = array_merge(
    rules(
        // Forbidden fields (Religion, Ethnic Group, Race)
        ['NK1.25','NK1.28','NK1.35'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    [
        'NK1.33' => ['minOccurs' => '1', 'maxOccurs' => 'unbounded', 'Usage' => 'R'],
    ]

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'NK1', $rules);

$fieldsSchemas["NK1.11"]["Table"] = "HL70327,HL70328";

//
// PV1 segment
//

$rules = array_merge(
    rules(
        ['PV1.3','PV1.5','PV1.6','PV1.11','PV1.19','PV1.42'],
        ['Usage' => 'C']
    ),
    rules(
        ['PV1.9','PV1.40','PV1.52'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    [
        'PV1.45' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O'],
    ]

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'PV1', $rules);

$fieldsSchemas["PV1.9"]["Table"] = "";
$fieldsSchemas["PV1.52"]["Table"] = "";

//
// PV2 segment
//

$rules = [
    'PV2.1'  => ['Usage' => 'C'],
    'PV2.3'  => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    'PV2.18' => ['Usage' => 'RE'],
    'PV2.47' => ['Usage' => 'C'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'PV2', $rules);

$fieldsSchemas["PV2.3"]["Table"] = ""; // IHE PAM FR 2.11.2 : Usage X

//
// ACC segment
//

$rules = [
    'ACC.1' => ['Usage' => 'RE'],
    'ACC.2' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    'ACC.4' => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'ACC', $rules);

$fieldsSchemas["ACC.4"]["Table"] = "";

//
// IN1 segment
//

$rules = array_merge(
    [
        'IN1.3' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    ],
    rules(
        ['IN1.12','IN1.13','IN1.15','IN1.16','IN1.19','IN1.20','IN1.31','IN1.35','IN1.45','IN1.49'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE']
    ),
    [
        'IN1.17' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
        'IN1.36' => ['Usage' => 'C'],
    ]

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'IN1', $rules);

$fieldsSchemas["IN1.2"]["Table"] = "HL70072";
$fieldsSchemas["IN1.35"]["maxLength"] = "20";

//
// IN2 segment
//

$rules = [
    'IN2.63' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'IN2', $rules);

//
// IN3 segment
//

$rules = [
    'IN3.5' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'IN3', $rules);

$fieldsSchemas["IN3.5"]["Table"] = "HL70148";

//
// GT1 segment
//

$rules = array_merge(
    rules(
        ['GT1.4','GT1.8','GT1.9','GT1.33','GT1.34','GT1.35','GT1.38','GT1.39','GT1.40','GT1.41','GT1.42','GT1.44','GT1.52','GT1.55'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
    ),
    rules(
        ['GT1.29','GT1.51'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O']
    ),

);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'GT1', $rules);

//
// OBX segment
//

$rules = array_merge(
    rules(
        ['OBX.1','OBX.2','OBX.16'],
        ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R']
    ),
    [
        'OBX.5'  => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'C'],
        'OBX.6'  => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'C'],
        'OBX.14' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
    ],
    rules(
        ['OBX.8','OBX.10','OBX.17','OBX.18'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'O']
    ),
);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'OBX', $rules);

//
// AL1
//

$rules = [
    'AL1.6' => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
];

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'AL1', $rules);

//
// MRG
//

$rules = rules(
    ['MRG.2','MRG.4','MRG.5','MRG.6'],
    ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
);

$segmentsSchemas = applySegmentRules($segmentsSchemas, 'MRG', $rules);

echo "Update segments: done.\n";



/**
 * Update data types
 * -------------------------------------
 *
 *
 */

//
// CX
//

$rules = [
    'CX.4' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    'CX.5' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
    'CX.7' => ['Usage' => 'C'],
];

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'CX', $rules);
$dataTypesSchemas["CX.1"]["maxLength"] = "128";

//
// EI
//

$rules = array_merge(
    [
        'EI.1' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    ],
    rules(
        ['EI.2','EI.3','EI.4'],
        ['Usage' => 'C']
    ),
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'EI', $rules);
$dataTypesSchemas["EI.1"]["maxLength"] = "128";

//
// HD
//

$rules = array_merge(
    [
        'HD.1' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    ],
    rules(
        ['HD.2','HD.3'],
        ['Usage' => 'C']
    ),
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'HD', $rules);

//
// PL
//

$rules = [
    'PL.6' => ['Usage' => 'C'],
];

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'PL', $rules);

//
// TS
//

$rules = [
    'TS.2' => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
];

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'TS', $rules);

//
// VID
//

$rules = rules(
    ['VID.1','VID.2','VID.3'],
    ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R']
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'VID', $rules);

//
// XAD
//

$rules = [
    'XAD.12' => ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
];

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'XAD', $rules);

//
// SAD
//

$rules = rules(
    ['SAD.2','SAD.3'],
    ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X']
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'SAD', $rules);

//
// XCN
//

$rules = array_merge(
    rules(
        ['XCN.1','XCN.2','XCN.3','XCN.9'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
    ),
    rules(
        ['XCN.5','XCN.7','XCN.8','XCN.11','XCN.12','XCN.15','XCN.16','XCN.17','XCN.18','XCN.19','XCN.20','XCN.21','XCN.22','XCN.23'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    ),
    rules(
        ['XCN.10','XCN.13'],
        ['Usage' => 'C']
    ),
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'XCN', $rules);

$dataTypesSchemas["XCN.1"]["maxLength"] = "199";

//
// XON
//

$rules = array_merge(
    rules(
        ['XON.1','XON.6','XON.7','XON.10'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
    ),
    rules(
        ['XON.2','XON.3','XON.4','XON.5','XON.8','XON.9'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    )
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'XON', $rules);

$dataTypesSchemas["XON.10"]["maxLength"] = "64";

//
// XPN
//

$rules = array_merge(
    [
        'XPN.1' => ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'RE'],
    ],
    rules(
        ['XPN.2','XPN.3'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'C'],
    ),
    rules(
        ['XPN.4','XPN.6','XPN.8','XPN.9','XPN.10','XPN.11','XPN.12','XPN.13','XPN.14'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    ),
    [
        'XPN.7' => ['minOccurs' => '1', 'maxOccurs' => '1', 'Usage' => 'R'],
    ],
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'XPN', $rules);

$dataTypesSchemas["XPN.2"]["maxLength"] = "194";
$dataTypesSchemas["XPN.3"]["maxLength"] = "194";

//
// XTN
//

$rules = array_merge(
    rules(
        ['XTN.1','XTN.5','XTN.6','XTN.7','XTN.8','XTN.10','XTN.11'],
        ['minOccurs' => '0', 'maxOccurs' => '0', 'Usage' => 'X'],
    ),
    rules(
        ['XTN.4','XTN.12'],
        ['minOccurs' => '0', 'maxOccurs' => '1', 'Usage' => 'C'],
    ),
);

$dataTypesSchemas = applyDataTypeRules($dataTypesSchemas, 'XTN', $rules);

echo "Update data types: done.\n";



/**
 * Update structures
 * -------------------------------------
 *
 * Fix: IHE PAM FR 2.11.2 Z-segments usage.
 *
 * IHE-CP-ITI-FR-2016-126
 * IHE-CP-ITI-FR-2016-127
 *   Changed all RE Z* segments to O
 *   Empty Z* segments SHALL NOT be transmitted.
 *
 * ITI-30
 *   The precedence order used by this implementation is:
 *   1. IHE PAM FR 2.11.2 specification
 *   2. Integrated Change Proposals (CP)
 *
 *   A28/A31 :
 *     - ZFA : Usage O
 *     - ZFD : Usage O
 *     - ZFS : Usage O
 *
 *    A40/A47 :
 *     - ZFA/ZFD/ZFS not present
 *
 * ITI-31
 *   Special case: ZBE
 *     - PAM FR 2.6 already states:
 *         "The ZBE segment is required for events:
 *          A01, A02, A03, A04, A05, A06, A07, A11, A12, A13,
 *          A14, A15, A16, A21, A22, A25, A26, A27, A38,
 *          A52, A53, A54, A55 and Z99."
 *
 *     - PAM FR 2.11.2 additionally specifies:
 *         - ADT_A05 structure:
 *           ZBE Movement segment = R [1..1]
 *
 *         - Historic Movement option:
 *           Usage = R
 *
 *     - Consequently: ZBE = R
 */

$messageStructures = [];
$messageDesc = [];
$ADTevents = [
    "A01","A02","A03","A04","A05","A06","A07","A09",
    "A10","A11","A12","A13","A14","A15","A16",
    "A21","A22","A25","A26","A27","A28",
    "A31","A32","A33","A38",
    "A40","A44","A47","A49",
    "A52","A53","A54","A55",
    "Z99"
];

$SIUevents = [
    "S12","S14","S15","S26"
];

// Create event desc.
foreach ($eventDesc as $type => $event) {
    $messageDesc[$type] = [];
    foreach ($event as $eventName => $desc) {
        if ($type === "ADT" && ! in_array($eventName, $ADTevents, true)) {
            continue;
        }

        if ($type === "SIU" && ! in_array($eventName, $SIUevents, true)) {
            continue;
        }

        $messageDesc[$type][$eventName] = $desc;
    }
}

echo "Create event desc.: done.\n";

// Create message structures
foreach ($messageType as $type => $event) {
    $messageStructures[$type] = array();
    foreach ($event as $eventName => $strucureId) {
        if ($type === "ADT" && ! in_array($eventName, $ADTevents, true)) {
            continue;
        }

        if ($type === "SIU" && ! in_array($eventName, $SIUevents, true)) {
            continue;
        }
        
        if (file_exists($inputDir . "/structures/" . $strucureId . ".json")) {
            $structureName = "$type-$eventName-$strucureId";
            $messageStructures[$type][$eventName] = $structureName;
            copy($inputDir . "/structures/" . $strucureId . ".json", $outputDir . "/structures/" . $structureName . ".json");
        }
    }
}
echo "Create message structures: done.\n";

// Update message structures

// IHE-CP-ITI-FR-2016-126
// IHE-CP-ITI-FR-2016-127
// ZFA/ZFP/ZFV/ZFM/ZFD/ZFS usages changed from RE to O.
// Empty Z* segments SHALL NOT be transmitted.

$iti30Zsegments = [
    ["segment" => "ZFA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFD", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFS", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"],
];

$iti31Zsegments = [
    ["segment" => "ZBE", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
    ["segment" => "ZFA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFP", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFV", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFM", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFD", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
    ["segment" => "ZFS", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"],
];

$PDA = [
    ["segment" => "PDA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
];

$iti30Rules = [

    'A28' => [
        'elementUpdates' => [
            [
                'structure' => 'ADT_A05',
                'type' => 'segment',
                'name' => 'NK1',
                'minOccurs' => '0',
                'maxOccurs' => 'unbounded',
                'Usage' => 'RE',
            ],

            [
                'structure' => 'ADT_A05',
                'type' => 'segment',
                'name' => 'PV2',
                'minOccurs' => '0',
                'maxOccurs' => '0',
                'Usage' => 'X',
            ],

            [
                'structure' => 'INSURANCE',
                'type' => 'segment',
                'name' => 'IN3',
                'minOccurs' => '0',
                'maxOccurs' => '1',
                'Usage' => 'O',
            ],
        ],

        'occurrenceUpdates' => [
            [
                'structure' => 'ADT_A05',
                'segment' => 'ROL',
                'occurrence' => 2,
                'minOccurs' => '0',
                'maxOccurs' => '0',
                'Usage' => 'X',
            ]
        ],

        'insertions' => [
            [
                'structure' => 'ADT_A05',
                'after'     => 'PV2',
                'segments'  => $iti30Zsegments,
            ]
        ],
    ],

    'A31' => [
        'elementUpdates' => [
            [
                'structure' => 'ADT_A05',
                'type' => 'segment',
                'name' => 'NK1',
                'minOccurs' => '0',
                'maxOccurs' => 'unbounded',
                'Usage' => 'RE',
            ],

            [
                'structure' => 'ADT_A05',
                'type' => 'segment',
                'name' => 'PV2',
                'minOccurs' => '0',
                'maxOccurs' => '0',
                'Usage' => 'X',
            ],

            [
                'structure' => 'INSURANCE',
                'type' => 'segment',
                'name' => 'IN3',
                'minOccurs' => '0',
                'maxOccurs' => '1',
                'Usage' => 'O',
            ],
        ],

        'occurrenceUpdates' => [
            [
                'structure' => 'ADT_A05',
                'segment' => 'ROL',
                'occurrence' => 2,
                'minOccurs' => '0',
                'maxOccurs' => '0',
                'Usage' => 'X',
            ]
        ],

        'insertions' => [
            [
                'structure' => 'ADT_A05',
                'after'     => 'PV2',
                'segments'  => $iti30Zsegments,
            ]
        ],
    ],

    'A40' => [

        'elementUpdates' => [
            [
                'structure' => 'ADT_A39',
                'type' => 'group',
                'name' => 'PATIENT',
                'minOccurs' => '1',
                'maxOccurs' => '1',
                'Usage' => 'R',
            ],

            [
                'structure' => 'PATIENT',
                'type' => 'segment',
                'name' => 'PV1',
                'minOccurs' => '0',
                'maxOccurs' => '0',
                'Usage' => 'X',
            ]
        ]
    ],

    'A47' => [
        'replaceStructure' => [
            'PATIENT' => [
                'elements' => [
                    ["segment" => "PID", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
                    ["segment" => "PD1", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"],
                    ["segment" => "MRG", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
                ]
            ],
            'ADT_A30' => [
                'elements' => [
                    ["segment" => "MSH", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
                    ["segment" => "SFT", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"],
                    ["segment" => "EVN", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
                    ["group" => "PATIENT", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"],
                ]
            ]
        ]
    ],
];


$iti31Rules = [];


$iti31Rules['A01'] = [
    'elementUpdates' => [
        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'occurrenceUpdates' => [
        [
            'structure' => 'ADT_A01',
            'segment' => 'ROL',
            'occurrence' => 1,
            'minOccurs' => '0',
            'maxOccurs' => 'unbounded',
            'Usage' => 'RE',
        ]
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A01',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A02'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A02',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A03'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A03',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A03',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A04'] = [
    'elementUpdates' => [
        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'occurrenceUpdates' => [
        [
            'structure' => 'ADT_A01',
            'segment' => 'ROL',
            'occurrence' => 1,
            'minOccurs' => '0',
            'maxOccurs' => 'unbounded',
            'Usage' => 'RE',
        ]
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A01',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];


$iti31Rules['A05'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A05',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A05',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ],

        [
            'structure' => 'ADT_A05',
            'after'     => 'UB2',
            'segments'  => $PDA,
        ]
    ],
];

$iti31Rules['A06'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A06',
            'type' => 'segment',
            'name' => 'MRG',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'C',
        ],

        [
            'structure' => 'ADT_A06',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A06',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A07'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A06',
            'type' => 'segment',
            'name' => 'MRG',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'C',
        ],

        [
            'structure' => 'ADT_A06',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A06',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A09'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A09',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A10'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A09',
            'type' => 'segment',
            'name' => 'DG1',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A09',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A11'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A09',
            'type' => 'segment',
            'name' => 'DG1',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A09',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A12'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A12',
            'type' => 'segment',
            'name' => 'DG1',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A12',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A13'] = [
    'elementUpdates' => [
        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A01',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A14'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A05',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '0',
            'Usage' => 'X',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A05',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ],

        [
            'structure' => 'ADT_A05',
            'after'     => 'UB2',
            'segments'  => $PDA,
        ]
    ],
];

$iti31Rules['A15'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A15',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A16'] = [
    'elementUpdates' => [
        [
            'structure' => 'ADT_A16',
            'type' => 'segment',
            'name' => 'PV2',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'RE',
        ],

        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A16',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A21'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A22'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A25'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A26'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A27'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A32'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A33'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A21',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A38'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A38',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A52'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A52',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A53'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A52',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A54'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A54',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['A55'] = [
    'insertions' => [
        [
            'structure' => 'ADT_A52',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

$iti31Rules['Z99'] = [
    'elementUpdates' => [
        [
            'structure' => 'INSURANCE',
            'type' => 'segment',
            'name' => 'IN3',
            'minOccurs' => '0',
            'maxOccurs' => '1',
            'Usage' => 'O',
        ],
    ],

    'insertions' => [
        [
            'structure' => 'ADT_A01',
            'after'     => 'PV2',
            'segments'  => $iti31Zsegments,
        ]
    ],
];

foreach ($messageStructures as $type => $event) {
    foreach ($event as $eventName => $strucureId) {
        if (!file_exists($outputDir . "/structures/" . $strucureId . ".json")) {
            continue;
        }

        $msgStruct = loadJson($outputDir . "/structures/" . $strucureId . ".json");

        //
        // ITI-30
        //
        $msgStruct = applyItiRules(
            $msgStruct,
            $eventName,
            $iti30Rules
        );

        //
        // ITI-31
        //
        $msgStruct = applyItiRules(
            $msgStruct,
            $eventName,
            $iti31Rules
        );

        saveJson($outputDir . "/structures/" . $strucureId . ".json", $msgStruct);

    }
}

echo "Update message structures: done.\n";



ksort($segmentsSchemas);
ksort($fieldsSchemas);
ksort($dataTypesSchemas);

saveJson($outputDir . "/segments/segments.json",   $segmentsSchemas);
saveJson($outputDir . "/fields/fields.json",       $fieldsSchemas);
saveJson($outputDir . "/dataTypes/dataTypes.json", $dataTypesSchemas);
saveJson($outputDir . "/messageType.json",         $messageStructures);
saveJson($outputDir . "/eventDesc.json",           $messageDesc);

echo "Done.\n";
