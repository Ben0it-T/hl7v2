<?php

declare(strict_types=1);

/**
 * Generate JSON schemas from HL7 XSD schemas.
 *
 * HL7’s Version 2.x xsd schemas : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 * - segments.xsd
 * - fields.xsd
 * - datatypes.xsd
 * - structures ACK.xsd, ADT_XXX.xsd, SIU_XXX.xsd, ...
 *
 * Usage:
 * php 02-xsd-schemas-to-json-schemas.php \
 *     --input-dir=<directory> \
 *     --output-dir=<directory>
 *
 * Example:
 * php 02-xsd-schemas-to-json-schemas.php \
 *     --input-dir=../schemas/hl7v2org/2.5 \
 *     --output-dir=../schemas/json-2.5-org
 *
 * php 02-xsd-schemas-to-json-schemas.php \
 *     --input-dir=../schemas/hl7v2xsd/2.5 \
 *     --output-dir=../schemas/json-2.5-sun
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
 * Load XSD schema.
 *
 * @param string $inputDir Directory containing the XSD schemas.
 * @param string $filename XSD schema filename.
 *
 * @return DOMDocument
 *
 * @throws RuntimeException If the file does not exist or cannot be loaded.
 */
function loadXsdSchema(string $inputDir, string $filename): DOMDocument
{
    $path = $inputDir . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($path)) {
        throw new RuntimeException(
            "File not found: {$path}"
        );
    }

    $document = new DOMDocument();
    $document->preserveWhiteSpace = false;

    if (!$document->load($path)) {
        throw new RuntimeException(
            "Unable to load XSD schema: {$path}"
        );
    }

    return $document;
}

/**
 * Save JSON schema.
 *
 * Creates the directory if it does not exist.
 *
 * @param string $name Schema name, used as filename (<name>.json).
 * @param array<string, mixed> $data Schema data.
 * @param string $directory Output directory.
 *
 * @return void
 *
 * @throws RuntimeException If the data cannot be encoded as JSON.
 */
function saveJsonSchema(string $name, array $data, string $directory): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException(
            "Unable to encode {$name}.json"
        );
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . $name . '.json', $json);
}

/**
 * Run an XPath query and return the matching elements.
 *
 * Nodes that are not elements (attributes, text…) are ignored.
 *
 * @param DOMXPath $xpath XPath object of the XSD schema.
 * @param string $expression XPath expression.
 * @param DOMNode|null $contextNode Context node of the query.
 *
 * @return list<DOMElement>
 *
 * @throws RuntimeException If the XPath expression is invalid.
 */
function xpathElements(DOMXPath $xpath, string $expression, ?DOMNode $contextNode = null): array
{
    $result = $xpath->query($expression, $contextNode);

    if ($result === false) {
        throw new RuntimeException(
            "Invalid XPath query: {$expression}"
        );
    }

    $elements = [];

    foreach ($result as $node) {
        if ($node instanceof DOMElement) {
            $elements[] = $node;
        }
    }

    return $elements;
}

/**
 * Create a structure element (segment or group reference).
 *
 * A reference longer than 3 characters is a group (e.g. ADT_A01.PROCEDURE),
 * otherwise a segment (e.g. PID).
 *
 * @param DOMElement $element xsd:element with a ref attribute.
 *
 * @return array<string, string> Segment or group name, minOccurs and maxOccurs.
 */
function structureElement(DOMElement $element): array
{
    $ref = $element->getAttribute("ref");
    $refParts = explode(".", $ref);
    $theRef = (count($refParts) > 1) ? $refParts[1] : $ref;
    $type = (strlen($ref) > 3) ? "group" : "segment";

    return [
        $type => $theRef,
        "minOccurs" => $element->getAttribute("minOccurs"),
        "maxOccurs" => $element->getAttribute("maxOccurs"),
    ];
}


//
// SEGMENTS
// filename: segments.xsd
//

echo "Generating segments...\n";

$segments = [];
$segmentsXmlDoc = loadXsdSchema($inputDir, 'segments.xsd');
$xpath = new DOMXPath($segmentsXmlDoc);
$xpath->registerNamespace(
    'xsd',
    'http://www.w3.org/2001/XMLSchema'
);
//$contextNode = $segmentsXmlDoc->getElementsByTagName('xsd:schema')->item(0);
$contextNode = $segmentsXmlDoc->documentElement;

if ($contextNode === null) {
    throw new RuntimeException(
        'Unable to locate xsd:schema node in segments.xsd.'
    );
}

// xsd:element
$nodes = xpathElements($xpath, 'xsd:element[@name]', $contextNode);
$elements = [];
foreach ($nodes as $node) {
    $elements[$node->getAttribute('type')] = $node->getAttribute('name');
}

// xsd:complexType
$cnt = 0;
$nodes = xpathElements($xpath, 'xsd:complexType[@name]', $contextNode);
foreach ($nodes as $node) {
    $name = $elements[$node->getAttribute('name')] ?? null;

    if ($name === null || strlen($name) !== 3) {
        continue;
    }

    if (count($node->getElementsByTagName('sequence')) === 0) {
        continue;
    }

    $cnt++;
    $sequence = $node->getElementsByTagName('sequence')->item(0);

    if ($sequence === null) {
        continue;
    }

    $fields = [];

    foreach ($sequence->getElementsByTagName('element') as $element) {
        $fields[] = [
            'field'     => $element->getAttribute('ref'),
            'minOccurs' => $element->getAttribute('minOccurs'),
            'maxOccurs' => $element->getAttribute('maxOccurs'),
        ];
    }

    $segments[$name] = ['fields' => $fields,];
}

ksort($segments);
saveJsonSchema('segments', $segments, $outputDir);

echo "- Segments: {$cnt}\n";



//
// FIELDS
// filename: fields.xsd
//

echo "Generating fields...\n";

$fields = [];
$fieldsXmlDoc = loadXsdSchema($inputDir, 'fields.xsd');
$xpath = new DOMXPath($fieldsXmlDoc);
$xpath->registerNamespace(
    'xsd',
    'http://www.w3.org/2001/XMLSchema'
);
//$contextNode = $fieldsXmlDoc->getElementsByTagName('xsd:schema')->item(0);
$contextNode = $fieldsXmlDoc->documentElement;

if ($contextNode === null) {
    throw new RuntimeException(
        'Unable to locate xsd:schema node in fields.xsd.'
    );
}

// xsd:element
$nodes = xpathElements($xpath, 'xsd:element[@name]', $contextNode);
$elements = [];
foreach ($nodes as $node) {
    $elements[$node->getAttribute('type')] = $node->getAttribute('name');
}

// xsd:complexType
$nodes = xpathElements($xpath, 'xsd:complexType[@name]', $contextNode);
$attributes = [];
foreach ($nodes as $node) {
    $type = $node->getAttribute("name");
    $name = $elements[$type] ?? null;

    if ($name === null) {
        continue;
    }
    $attributeGroup = xpathElements($xpath, 'xsd:complexType[@name="'.$type.'"]//xsd:attributeGroup[@ref]', $contextNode);
    if ($attributeGroup !== []) {
        $attributes[$attributeGroup[0]->getAttribute("ref")] = $name;
    }
}

// xsd:attributeGroup
$cnt = 0;
$nodes = xpathElements($xpath, 'xsd:attributeGroup[@name]', $contextNode);
foreach ($nodes as $node) {
    $cnt++;
    $name = $attributes[$node->getAttribute("name")] ?? null;

    if ($name === null) {
        continue;
    }

    $data = [
        "Item"      => "",
        "Type"      => "",
        "Table"     => "",
        "LongName"  => "",
        "maxLength" => "",
    ];

    foreach ($node->getElementsByTagName('attribute') as $elmt) {
        $data[$elmt->getAttribute("name")] = $elmt->getAttribute("fixed");
    }
    $fields[$name] = $data;
}

ksort($fields);
saveJsonSchema("fields", $fields, $outputDir);

echo "- Fields: {$cnt}\n";



//
// DATATYPES & COMPONENTS
// filename: datatypes.xsd
//

echo "Generating datatypes...\n";

$dataTypes = [];
$datatypesXmlDoc = loadXsdSchema($inputDir, 'datatypes.xsd');
$xpath = new DOMXPath($datatypesXmlDoc);
$xpath->registerNamespace(
    'xsd',
    'http://www.w3.org/2001/XMLSchema'
);
//$contextNode = $datatypesXmlDoc->getElementsByTagName('xsd:schema')->item(0);
$contextNode = $datatypesXmlDoc->documentElement;
$cnt = 0;

if ($contextNode === null) {
    throw new RuntimeException(
        'Unable to locate xsd:schema node in datatypes.xsd.'
    );
}

// xsd:element
$nodes = xpathElements($xpath, 'xsd:element[@name]', $contextNode);
$elements = [];
foreach ($nodes as $node) {
    $elements[$node->getAttribute("type")] = $node->getAttribute("name");
}


// xsd:simpleType (primitives datatypes)
$nodes = xpathElements($xpath, 'xsd:simpleType[@name]', $contextNode);
foreach ($nodes as $node) {
    $cnt++;
    $dataTypes[$node->getAttribute("name")] = [
        "dataType" => "STRING",
    ];
}

// xsd:complexType (xsd:complexType name=)
$nodes = xpathElements($xpath, 'xsd:complexType[@name]', $contextNode);
$componentsRef = [];
foreach ($nodes as $node) {
    $name = $node->getAttribute("name");

    // primitive datatype extension escapeType/varies
    if (in_array($name, array("escapeType", "varies"), true)) {
        $cnt++;
        $dataTypes[$name] =  [
            "dataType" => "STRING",
        ];

        continue;
    }

    // primitive datatypes
    $primitiveDatatypes = xpathElements($xpath, 'xsd:complexType[@name="'.$name.'"]/xsd:sequence/xsd:element[@name]', $contextNode);
    if (count($primitiveDatatypes) === 1) {
        $cnt++;
        $dataTypes[$name] = [
            "dataType" => "STRING",
        ];

        continue;
    } elseif (count($primitiveDatatypes) > 1) {
        echo "Need to check <xsd:complexType name=\"{$name}\"...\n";
    }

    // composite datatypes
    $compositeDatatypes = xpathElements($xpath, 'xsd:complexType[@name="'.$name.'"]/xsd:sequence/xsd:element[@ref]', $contextNode);
    if ($compositeDatatypes !== []) {
        $cnt++;
        $components = [];
        foreach ($compositeDatatypes as $elmt) {
            $components[] = [
                "dataType"  => $elmt->getAttribute("ref"),
                "minOccurs" => $elmt->getAttribute("minOccurs"),
                "maxOccurs" => $elmt->getAttribute("maxOccurs"),
            ];
        }
        $dataTypes[$name] = ["components" => $components];
    }

    // components attributeGroup ref
    if (isset($elements[$name])) {
        $attributeGroup = xpathElements($xpath, 'xsd:complexType[@name="'.$name.'"]//xsd:attributeGroup[@ref]', $contextNode);
        if ($attributeGroup !== []) {
            $componentsRef[$attributeGroup[0]->getAttribute("ref")] = $name;
        }
    }
}

// xsd:attributeGroup
$nodes = xpathElements($xpath, 'xsd:attributeGroup[@name]', $contextNode);
foreach ($nodes as $node) {
    if (isset($componentsRef[$node->getAttribute("name")])) {
        $ref = $componentsRef[$node->getAttribute("name")];
        if (isset($elements[$ref])) {
            $cnt++;
            $name = $elements[$ref];
            $data = [
                "Type"      => "",
                "Table"     => "",
                "LongName"  => "",
                "maxLength" => "",
            ];
            foreach ($node->getElementsByTagName('attribute') as $elmt) {
                $data[$elmt->getAttribute("name")] = $elmt->getAttribute("fixed");
            }
            $dataTypes[$name] = $data;
        }
    }
}

ksort($dataTypes);
saveJsonSchema("dataTypes", $dataTypes, $outputDir);

echo "- Datatypes & Components: {$cnt}\n";



//
// STRUCTURES
// filenames: ACK.xsd, ADT_XXX.xsd, SIU_XXX.xsd
//

echo "Generating structures...\n";

$cnt = 0;
$files = scandir($inputDir, SCANDIR_SORT_ASCENDING);

if ($files === false) {
    throw new RuntimeException(
        "Unable to read directory: {$inputDir}"
    );
}

$ignore = ["batch.xsd", "datatypes.xsd", "fields.xsd", "messages.xsd", "segments.xsd"];
foreach ($files as $file) {
    $filename = $inputDir . DIRECTORY_SEPARATOR . $file;

    if (!is_file($filename)) {
        continue;
    }

    if (!str_ends_with($file, '.xsd')) {
        continue;
    }

    if (in_array($file, $ignore, true)) {
        continue;
    }


    $cnt++;
    $structureName = substr($file, 0, -4);
    $structureXmlDoc = loadXsdSchema($inputDir, $file);
    $xpath = new DOMXPath($structureXmlDoc);
    $xpath->registerNamespace(
        'xsd',
        'http://www.w3.org/2001/XMLSchema'
    );
    //$contextNode = $structureXmlDoc->getElementsByTagName('xsd:schema')->item(0);
    $contextNode = $structureXmlDoc->documentElement;

    // xsd:element
    $nodes = xpathElements($xpath, 'xsd:element[@name]', $contextNode);
    $elements = [];
    foreach ($nodes as $node) {
        $elements[$node->getAttribute("type")] = $node->getAttribute("name");
    }

    // xsd:complexType
    $nodes = xpathElements($xpath, 'xsd:complexType[@name]', $contextNode);
    $structureArray = [];
    foreach ($nodes as $node) {
        $fullname = $node->getAttribute("name");
        $name = $elements[$fullname] ?? null;

        if ($name === null) {
            continue;
        }

        $nameParts = explode(".", $name);
        $groupName = (count($nameParts) > 1) ? $nameParts[1] : $name;
        $groupElements = [];

        // sequence
        $sequence = xpathElements($xpath, 'xsd:complexType[@name="'.$fullname.'"]/xsd:sequence/xsd:element[@ref]', $contextNode);
        foreach ($sequence as $elmt) {
            $groupElements[] = structureElement($elmt);
        }

        // choice
        $choice = xpathElements($xpath, 'xsd:complexType[@name="'.$fullname.'"]/xsd:choice/xsd:element[@ref]', $contextNode);
        foreach ($choice as $elmt) {
            $groupElements[] = structureElement($elmt);
        }

        $structureArray[$groupName] = ["elements" => $groupElements];

        if ($choice !== []) {
            $structureArray[$groupName]["type"] = "choice";
        }
    }
    saveJsonSchema($structureName, $structureArray, $outputDir . "/structures");
}

echo "- Structures: {$cnt}\n";


echo "Done.\n";
