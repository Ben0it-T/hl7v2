<?php

declare(strict_types=1);

/**
 * Create XML profiles (HL7v2xConformanceProfile) from a JSON profile directory.
 *
 * The JSON profile is the compiled profile written by 06 (format described in profiles/README.md).
 * The script writes one XML profile per message (<type>-<event>-<structure>.xml) and replaces
 * the XML files of the output directory. Field definitions of a structure element (message-specific)
 * take precedence over segments.json. The `source` keys are not written.
 *
 * The 2002 profile schema (HL7MessageProfileSchema.xsd) does not allow some JSON values:
 * - usage B is written O, usage W is written X;
 * - an element [0..0] is written Min="0" Max="1" (its usage X says it must not be sent);
 * - an empty Length or ItemNo is not written.
 * With --xsd, each XML profile is validated against the schema before it is written.
 *
 * Usage:
 * php 07-create-xml-profile.php \
 *     --profile-dir=<directory> \
 *     --xml-dir=<directory> \
 *     [--xsd=<file>]
 *
 * Example:
 * php 07-create-xml-profile.php \
 *     --profile-dir=../profiles-next/json-2.5-IHEPAMFR \
 *     --xml-dir=../profiles-next/xml-2.5-IHEPAMFR \
 *     --xsd=../schemas/hl7v2xsd/profile/HL7MessageProfileSchema.xsd
 */

$options = getopt('', [
    'profile-dir:',
    'xml-dir:',
    'xsd:',
]);

$profileDir = $options['profile-dir'] ?? null;
$xmlDir = $options['xml-dir'] ?? null;
$xsd = $options['xsd'] ?? null;

if (
    !is_string($profileDir) || $profileDir === ''
    || !is_string($xmlDir) || $xmlDir === ''
    || ($xsd !== null && (!is_string($xsd) || $xsd === ''))
) {
    fwrite(
        STDERR,
        "Usage: php 07-create-xml-profile.php --profile-dir=<directory> --xml-dir=<directory> [--xsd=<file>]\n"
    );
    exit(1);
}

if (!is_dir($profileDir)) {
    throw new RuntimeException(
        "Directory not found: {$profileDir}"
    );
}

if ($xsd !== null && !is_file($xsd)) {
    throw new RuntimeException(
        "File not found: {$xsd}"
    );
}

if (is_dir($xmlDir) && realpath($profileDir) === realpath($xmlDir)) {
    throw new RuntimeException(
        'Profile and XML directories must be different.'
    );
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
 * Read a string value of a profile entry.
 *
 * @param array<mixed> $entry Profile entry.
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
 * Read an object or a list of a profile.
 *
 * @param array<mixed> $profile Profile part.
 * @param string $key Key.
 * @param string $where Profile part, for the error message.
 *
 * @return array<mixed>
 *
 * @throws RuntimeException If the key is not defined.
 */
function entryOf(array $profile, string $key, string $where): array
{
    if (!isset($profile[$key]) || !is_array($profile[$key])) {
        throw new RuntimeException("{$where}: '{$key}' is not defined");
    }

    return $profile[$key];
}

/**
 * Convert usage and cardinality to the values allowed by the profile schema.
 *
 * B → O, W → X, and a maximum of 0 → 1 (the usage X says the element must not be sent).
 *
 * @param array<mixed> $entry Profile entry with usage, min and max.
 * @param string $where Entry, for the error message.
 *
 * @return array{Usage: string, Min: string, Max: string}
 *
 * @throws RuntimeException If a value is missing.
 */
function xmlOccurs(array $entry, string $where): array
{
    $usage = stringOf($entry, 'usage', $where);
    $max = stringOf($entry, 'max', $where);

    return [
        'Usage' => ['B' => 'O', 'W' => 'X'][$usage] ?? $usage,
        'Min' => stringOf($entry, 'min', $where),
        'Max' => $max === '0' ? '1' : $max,
    ];
}

/**
 * Add attributes to a node, in order.
 *
 * @param DOMElement $node Node.
 * @param array<string, string> $attributes Attributes, by name.
 *
 * @return DOMElement
 */
function withAttributes(DOMElement $node, array $attributes): DOMElement
{
    foreach ($attributes as $name => $value) {
        $node->setAttribute($name, $value);
    }

    return $node;
}

/**
 * XML profile writer for one JSON profile directory.
 */
final class XmlProfileWriter
{
    /**
     * Create a writer.
     *
     * @param array<string, mixed> $segments Segments (segments.json).
     * @param array<string, mixed> $fields Fields (fields.json).
     * @param array<string, mixed> $dataTypes Datatypes (dataTypes.json).
     */
    public function __construct(
        private readonly array $segments,
        private readonly array $fields,
        private readonly array $dataTypes,
    ) {
    }

    /**
     * Add the elements of a group (segments and groups).
     *
     * @param DOMDocument $document XML document.
     * @param DOMElement $parent Parent node (HL7v2xStaticDef or SegGroup).
     * @param array<string, mixed> $structure Message structure, by group name.
     * @param string $group Group name.
     * @param string $where Message, for the error message.
     *
     * @return void
     *
     * @throws RuntimeException On an undefined group, segment, field or datatype.
     */
    public function addElements(DOMDocument $document, DOMElement $parent, array $structure, string $group, string $where): void
    {
        foreach (entryOf(entryOf($structure, $group, $where), 'elements', "{$where} {$group}") as $element) {
            if (!is_array($element)) {
                throw new RuntimeException("{$where} {$group}: invalid element");
            }

            $occurs = xmlOccurs($element, "{$where} {$group}");

            if (isset($element['group'])) {
                $name = stringOf($element, 'group', "{$where} {$group}");
                $node = withAttributes($document->createElement('SegGroup'), ['Name' => $name, 'LongName' => $name] + $occurs);

                if (($structure[$name]['choice'] ?? false) === true) {
                    $node->setAttribute('kind', 'choice');
                }

                $parent->appendChild($node);
                $this->addElements($document, $node, $structure, $name, $where);
                continue;
            }

            $name = stringOf($element, 'segment', "{$where} {$group}");
            $segment = entryOf($this->segments, $name, 'segments.json');
            $node = withAttributes(
                $document->createElement('Segment'),
                ['Name' => $name, 'LongName' => stringOf($segment, 'longName', "segments.json: {$name}")] + $occurs
            );
            $parent->appendChild($node);
            $overrides = isset($element['fields']) ? entryOf($element, 'fields', "{$where} {$name}") : [];

            foreach (entryOf($segment, 'fields', "segments.json: {$name}") as $field) {
                if (!is_array($field)) {
                    throw new RuntimeException("segments.json: {$name}: invalid field");
                }

                $id = stringOf($field, 'field', "segments.json: {$name}");
                $occurrence = isset($overrides[$id]) && is_array($overrides[$id]) ? $overrides[$id] : $field;
                $node->appendChild($this->field($document, $id, $occurrence));
            }
        }
    }

    /**
     * Create a field node.
     *
     * @param DOMDocument $document XML document.
     * @param string $id Field identifier (e.g. PID.3).
     * @param array<mixed> $occurrence Usage and cardinality of the field in the message.
     *
     * @return DOMElement
     *
     * @throws RuntimeException On an undefined field or datatype.
     */
    private function field(DOMDocument $document, string $id, array $occurrence): DOMElement
    {
        $definition = entryOf($this->fields, $id, 'fields.json');
        $where = "fields.json: {$id}";
        $dataType = stringOf($definition, 'dataType', $where);
        $table = stringOf($definition, 'table', $where);
        $length = stringOf($definition, 'maxLength', $where);
        $item = stringOf($definition, 'item', $where);

        $attributes = ['Name' => stringOf($definition, 'longName', $where)]
            + xmlOccurs($occurrence, $id)
            + ['Datatype' => $dataType];

        if ($length !== '') {
            $attributes['Length'] = $length;
        }

        if ($table !== '') {
            $attributes['Table'] = $table;
        }

        if ($item !== '') {
            $attributes['ItemNo'] = $item;
        }
        $node = withAttributes($document->createElement('Field'), $attributes);
        $node->appendChild($document->createElement('Reference'))->appendChild($document->createTextNode(stringOf($definition, 'chapter', $where)));

        foreach ($this->components($dataType) as $component) {
            $node->appendChild($this->component($document, 'Component', $component, true));
        }

        return $node;
    }

    /**
     * Get the components of a datatype.
     *
     * @param string $dataType Datatype name.
     *
     * @return list<array<mixed>> Components (none for a primitive datatype).
     *
     * @throws RuntimeException On an undefined datatype.
     */
    private function components(string $dataType): array
    {
        $definition = entryOf($this->dataTypes, $dataType, 'dataTypes.json');

        if (!isset($definition['components'])) {
            return [];
        }

        $components = [];

        foreach (entryOf($definition, 'components', "dataTypes.json: {$dataType}") as $component) {
            if (!is_array($component)) {
                throw new RuntimeException("dataTypes.json: {$dataType}: invalid component");
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * Create a component or sub-component node.
     *
     * Sub-components are written one level down only.
     *
     * @param DOMDocument $document XML document.
     * @param string $tag Node name (Component, SubComponent).
     * @param array<mixed> $component Component of the profile.
     * @param bool $withSubComponents Add the sub-components.
     *
     * @return DOMElement
     *
     * @throws RuntimeException On an undefined datatype.
     */
    private function component(DOMDocument $document, string $tag, array $component, bool $withSubComponents): DOMElement
    {
        $id = stringOf($component, 'component', 'dataTypes.json');
        $dataType = stringOf($component, 'dataType', "dataTypes.json: {$id}");
        $table = stringOf($component, 'table', "dataTypes.json: {$id}");
        $length = stringOf($component, 'maxLength', "dataTypes.json: {$id}");

        $attributes = [
            'Name' => stringOf($component, 'longName', "dataTypes.json: {$id}"),
            'Usage' => xmlOccurs($component, "dataTypes.json: {$id}")['Usage'],
            'Datatype' => $dataType,
        ];

        if ($length !== '') {
            $attributes['Length'] = $length;
        }

        if ($table !== '') {
            $attributes['Table'] = $table;
        }

        $node = withAttributes($document->createElement($tag), $attributes);

        if ($withSubComponents) {
            foreach ($this->components($dataType) as $subComponent) {
                $node->appendChild($this->component($document, 'SubComponent', $subComponent, false));
            }
        }

        return $node;
    }
}



// Load the JSON profile
$profile     = loadJson($profileDir . '/profile.json');
$messageType = loadJson($profileDir . '/messageType.json');
$eventDesc   = loadJson($profileDir . '/eventDesc.json');
$writer      = new XmlProfileWriter(
    loadJson($profileDir . '/segments.json'),
    loadJson($profileDir . '/fields.json'),
    loadJson($profileDir . '/dataTypes.json'),
);
$hl7Version  = stringOf($profile, 'hl7Version', 'profile.json');

if (!is_dir($xmlDir) && !mkdir($xmlDir, 0777, true) && !is_dir($xmlDir)) {
    throw new RuntimeException(
        "Unable to create directory: {$xmlDir}"
    );
}

foreach (glob($xmlDir . '/*.xml') ?: [] as $filename) {
    if (!unlink($filename)) {
        throw new RuntimeException("Unable to remove file: {$filename}");
    }
}

$count = 0;

foreach ($messageType as $type => $events) {
    foreach (entryOf($messageType, $type, 'messageType.json') as $event => $structureName) {
        $event = (string) $event;
        $where = "{$type}^{$event}";
        $structureName = is_string($structureName) ? $structureName : throw new RuntimeException("messageType.json: {$where}: string expected");
        $description = stringOf(entryOf($eventDesc, $type, 'eventDesc.json'), $event, "eventDesc.json: {$type}");
        $structure = loadJson("{$profileDir}/structures/{$type}-{$event}-{$structureName}.json");

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->appendChild($document->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="http://gazelle.ihe.net/xsl/mp2htm.xsl"'));

        // Header values follow the Gazelle profiles (Q169).
        $root = $document->createElement('HL7v2xConformanceProfile');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation', 'http://gazelle.ihe.net/xsd/HL7MessageProfileSchema.xsd');
        $document->appendChild(withAttributes($root, [
            'HL7Version' => $hl7Version,
            'ProfileType' => 'Constrainable',
        ]));
        $root->appendChild(withAttributes($document->createElement('MetaData'), [
            'Name' => 'ITI',
            'OrgName' => 'IHE',
            'Version' => $hl7Version,
            'Status' => 'DRAFT',
            'Topics' => "confsig-IHE-{$hl7Version}-profile-accNE_accAL-Deferred",
        ]));
        $root->appendChild($document->createElement('ImpNote'))->appendChild($document->createTextNode($description));
        $root->appendChild($document->createElement('UseCase'));
        $root->appendChild($document->createElement('Encodings'))->appendChild($document->createElement('Encoding'))->appendChild($document->createTextNode('ER7'));
        $root->appendChild(withAttributes($document->createElement('DynamicDef'), [
            'AccAck' => 'NE',
            'AppAck' => 'AL',
            'MsgAckMode' => 'Deferred',
        ]));

        $staticDef = withAttributes($document->createElement('HL7v2xStaticDef'), [
            'MsgType' => $type,
            'EventType' => $event,
            'MsgStructID' => $structureName,
            'EventDesc' => $description,
            'Role' => 'Sender',
        ]);
        $root->appendChild($staticDef);
        $staticDef->appendChild(withAttributes($document->createElement('MetaData'), [
            'Name' => 'ITI',
            'OrgName' => 'IHE',
            'Version' => $hl7Version,
            'Status' => 'DRAFT',
            'Topics' => "confsig-IHE-{$hl7Version}-static-{$type}-{$event}-null-{$structureName}-{$hl7Version}-DRAFT-Sender",
        ]));

        $writer->addElements($document, $staticDef, $structure, $structureName, $where);

        $filename = "{$xmlDir}/{$type}-{$event}-{$structureName}.xml";

        if ($xsd !== null) {
            libxml_use_internal_errors(true);

            if (!$document->schemaValidate($xsd)) {
                $error = libxml_get_errors()[0] ?? null;
                throw new RuntimeException("{$filename}: " . ($error === null ? 'invalid' : trim($error->message) . " (line {$error->line})"));
            }
        }

        if ($document->save($filename) === false) {
            throw new RuntimeException("Unable to write file: {$filename}");
        }

        $count++;
    }
}

echo "{$count} XML profiles.\nDone.\n";
