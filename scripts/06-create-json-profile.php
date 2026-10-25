<?php
/**
 * Create JSON profiles from JSON schemas.
 *
 * Configuration (06-create-json-profile.config.php, created from 06-create-json-profile.config.dist.php if missing):
 * - inputDir: JSON schema directory (e.g. ../schemas/json-2.5)
 * - outputDir: profile directory (e.g. ../profiles/json-2.5)
 * - msgType: message types to generate (e.g. ['ACK', 'ADT', 'SIU'])
 * - ignoreEvents: trigger events to skip (e.g. ['A08'])
 * - fieldsConstraints: apply the IHE PAM FR conditional predicates
 * - indent: number of spaces per indentation level
 * - pretty: pretty print JSON
 *
 * Usage:
 * php 06-create-json-profile.php
 */

declare(strict_types=1);

class HL7jsonProfilesGenerator
{
    // JSON schema inputs
    public string $dataTypesFilename;
    public string $fieldsFilename;
    public string $segmentsFilename;
    public string $structuresInputDir;
    public string $messageTypeFilename;
    // JSON profile output directory
    public string $profilesOutputDir;
    public int  $indent;
    public bool $pretty;
    public bool $fieldsConstraints;

    private string $eventName;
    /** @var array<string, mixed> */
    private array $messageType;
    /** @var list<array<string, mixed>> */
    private array $messageProfile;
    /** @var array<string, array<string, mixed>> */
    private array $structuresSchemas;
    /** @var array<string, mixed> */
    private array $segmentsSchemas;
    /** @var array<string, mixed> */
    private array $fieldsSchemas;
    /** @var array<string, mixed> */
    private array $dataTypesSchemas;

    /**
     * Create a new instance.
     */
    public function __construct()
    {
        $this->setDefaults();
        
    }

    /**
     * Set default values.
     *
     * @return void
     */
    private function setDefaults(): void
    {
        $this->structuresSchemas = [];
        $this->segmentsSchemas = [];
        $this->fieldsSchemas = [];
        $this->dataTypesSchemas = [];
        $this->eventName = "";
        $this->indent = 4;
        $this->pretty = true;
        $this->fieldsConstraints = false;
    }

    /**
     * Set the datatype schema filename.
     *
     * @param string $filename dataTypes.json filename.
     *
     * @return void
     */
    public function setDataTypesFilename(string $filename): void
    {
        $this->dataTypesFilename = $filename;
    }

    /**
     * Set the field schema filename.
     *
     * @param string $filename fields.json filename.
     *
     * @return void
     */
    public function setFieldsFilename(string $filename): void
    {
        $this->fieldsFilename = $filename;
    }

    /**
     * Set the segment schema filename.
     *
     * @param string $filename segments.json filename.
     *
     * @return void
     */
    public function setSegmentsFilename(string $filename): void
    {
        $this->segmentsFilename = $filename;
    }

    /**
     * Set the message type filename.
     *
     * @param string $filename messageType.json filename.
     *
     * @return void
     */
    public function setMessageTypeFilename(string $filename): void
    {
        $this->messageTypeFilename = $filename;
    }

    /**
     * Set the message structure directory.
     *
     * @param string $directory Directory containing the message structures (structures/).
     *
     * @return void
     */
    public function setStructuresInputDir(string $directory): void
    {
        $this->structuresInputDir = $directory;
    }

    /**
     * Set the profile output directory.
     *
     * @param string $directory Output directory.
     *
     * @return void
     */
    public function setProfilesOutputDir(string $directory): void
    {
        $this->profilesOutputDir = $directory;
    }

    /**
     * Set the indentation level.
     *
     * Used with pretty print only.
     *
     * @param int $indent Number of spaces per indentation level.
     *
     * @return void
     */
    public function setIndentationLevel(int $indent = 4): void
    {
        $this->indent = intval($indent);
    }

    /**
     * Set pretty print.
     *
     * @param bool $pretty Pretty print JSON.
     *
     * @return void
     */
    public function setPretty(bool $pretty): void
    {
        $this->pretty = $pretty;
    }

    /**
     * Set fields constraints.
     *
     * Applies the IHE PAM FR conditional predicates to fields (see addField()).
     *
     * @param bool $fieldsConstraints Apply the conditional predicates.
     *
     * @return void
     */
    public function setFieldsConstraints(bool $fieldsConstraints): void
    {
        $this->fieldsConstraints = $fieldsConstraints;
    }


    /**
     * Create JSON profiles.
     *
     * Writes one profile per message (<type>-<event>-<structure>.json)
     * and messageType.json to the output directory.
     *
     * @param list<string> $messageType Message types to generate (e.g. ACK, ADT, SIU).
     * @param list<string> $ignoreEvents Trigger events to skip (e.g. A08).
     *
     * @return void
     *
     * @throws RuntimeException If a schema cannot be loaded or a profile cannot be encoded as JSON.
     */
    public function createJsonProfiles(array $messageType, array $ignoreEvents = []): void
    {
        // Load json schemas
        $this->messageType = $this->loadJson($this->messageTypeFilename);
        $this->dataTypesSchemas = $this->loadJson($this->dataTypesFilename);
        $this->fieldsSchemas = $this->loadJson($this->fieldsFilename);
        $this->segmentsSchemas = $this->loadJson($this->segmentsFilename);

        // Create profiles
        $messageStructures = [];
        foreach ($this->messageType as $type => $event) {
            if (in_array($type, $messageType)) {
                $messageStructures[$type] = [];

                foreach ($event as $eventName => $structureName) {
                    if (in_array($eventName, $ignoreEvents)) {
                        continue;
                    }

                    $this->eventName = $eventName;
                    $this->messageProfile = [];

                    // get structureId
                    $nameParts = explode("-", $structureName);
                    $structureId = end($nameParts);

                    $messageStructures[$type][$eventName] = $structureId;
                    
                    // get message structure
                    if (!isset($this->structuresSchemas[$structureName])) {
                        $this->structuresSchemas[$structureName] = $this->loadJson($this->structuresInputDir . "/" . $structureName . ".json");
                    }

                    // 'root' groupName is structureId
                    $groupName = $structureId;
                    foreach ($this->structuresSchemas[$structureName][$groupName]["elements"] as $element) {
                        $attributes = $this->getElementAttributes($element);
                        if ($attributes["Type"] == "segment") {
                            $this->messageProfile[] = $this->addSegment($element["segment"], $attributes);
                        }
                        else if ($attributes["Type"] == "group") {
                            $this->messageProfile[] = $this->addSegGroup($structureName, $element["group"], $attributes);
                        }
                    }

                    $outputFilename = "$type-$eventName-$structureId.json";
                    echo "- {$outputFilename}\n";
                    //file_put_contents($this->profilesOutputDir . "/" . $outputFilename, json_encode($this->messageProfile, JSON_PRETTY_PRINT));
                    if ($this->pretty) {
                        if ($this->indent == 4) {
                            $json = json_encode($this->messageProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        } else {
                            $json = json_encode($this->messageProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                            if ($json === false) {
                                throw new RuntimeException(
                                    "Unable to encode JSON: {$outputFilename}"
                                );
                            }

                            $json = preg_replace_callback(
                                '/^(?: {4})+/m',
                                function($m) {
                                    return str_repeat(' ', $this->indent * intdiv(strlen($m[0]), 4));
                                },
                                $json
                            );
                        }
                    } else {
                        $json = json_encode($this->messageProfile, JSON_UNESCAPED_SLASHES);
                    }
                    file_put_contents($this->profilesOutputDir . "/" . $outputFilename, $json);
                }
            }
        }
        file_put_contents($this->profilesOutputDir . "/messageType.json", json_encode($messageStructures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "Done.\n";
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
    private function loadJson(string $filename): array
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
     * Get element usage from its cardinality.
     *
     * Used when the schema has no usage: 0..0 is X, a minimum of 1 is R, otherwise O.
     *
     * @param string $minOccurs Minimum cardinality.
     * @param string $maxOccurs Maximum cardinality.
     *
     * @return string Usage (R, O or X).
     */
    private function getElementUsage(string $minOccurs = "0", string $maxOccurs = "0"): string
    {
        $usage = "O";
        if ($minOccurs == "0" && $maxOccurs == "0") {
            $usage = "X";
        }
        else if ($minOccurs == "1") {
            $usage = "R";
        }
        return $usage;
    }

    /**
     * Get group or segment attributes.
     *
     * @param array<string, mixed> $element Structure element (segment or group).
     *
     * @return array<string, string> Type, Name, Usage, Min and Max.
     */
    private function getElementAttributes(array $element): array
    {
        $elementType = (isset($element["segment"]) ? "segment" : "group");
        $elementName = trim($element[$elementType]);
        $elementMin = trim($element["minOccurs"]);
        $elementMax = trim($element["maxOccurs"]);
        $elementMax = ($elementMax == "unbounded" ? "*" : $elementMax);
        // Usage
        if (isset($element["usage"])) {
            $elementUsage = trim($element["usage"]);
        } else {
            $elementUsage = $this->getElementUsage($elementMin, $elementMax);
        }

        return $attributes = array(
            "Type" => $elementType,
            "Name" => $elementName,
            "Usage" => $elementUsage,
            "Min" => $elementMin,
            "Max" => $elementMax
        );
    }

    /**
     * Get field attributes.
     *
     * @param array<string, mixed> $field Segment field (segments.json).
     *
     * @return array<string, string> Name, Usage, Min and Max.
     */
    private function getFieldAttributes(array $field): array
    {
        $fieldName = trim($field["field"]);
        $fieldMin = trim($field["minOccurs"]);
        $fieldMax = trim($field["maxOccurs"]);
        $fieldMax = ($fieldMax == "unbounded" ? "*" : $fieldMax);
        // Usage
        if (isset($field["usage"])) {
            $fieldUsage = trim($field["usage"]);
        } else {
            $fieldUsage = $this->getElementUsage($fieldMin, $fieldMax);
        }

        return $fieldAttributes = array(
            "Name" => $fieldName,
            "Usage" => $fieldUsage,
            "Min" => $fieldMin,
            "Max" => $fieldMax,
        );
    }

    /**
     * Get component attributes.
     *
     * @param array<string, mixed> $component Datatype component (dataTypes.json).
     *
     * @return array<string, string> Name, Usage, Min, Max and Type.
     */
    private function getComponentAttributes(array $component): array
    {
        $componentName = trim($component["dataType"]);
        $componentMin = trim($component["minOccurs"]);
        $componentMax = trim($component["maxOccurs"]);
        $componentMax = ($componentMax == "unbounded" ? "*" : $componentMax);
        // Usage
        if (isset($component["usage"])) {
            $componentUsage = trim($component["usage"]);
        } else {
            $componentUsage = $this->getElementUsage($componentMin, $componentMax);
        }
        return $componentAttributes = array(
            "Name" => $componentName,
            "Usage" => $componentUsage,
            "Min" => $componentMin,
            "Max" => $componentMax,
            "Type" => "",
        );
    }

    /**
     * Add segment group.
     *
     * Adds the segments and groups of the group, recursively.
     *
     * @param string $structureName Message structure name (structure filename without .json).
     * @param string $segGroupName Group name (e.g. ADT_A01.PROCEDURE).
     * @param array<string, string> $segGroupAttributes Group attributes (see getElementAttributes()).
     *
     * @return array<string, mixed> Group profile.
     */
    private function addSegGroup(string $structureName, string $segGroupName, array $segGroupAttributes): array
    {
        // set segGroup attributes
        $segGroupAttributes["LongName"] = $segGroupAttributes["Name"];
        if (isset($this->structuresSchemas[$structureName][$segGroupName]['type']) && $this->structuresSchemas[$structureName][$segGroupName]['type'] === "choice") {
            $segGroupAttributes["kind"] = "choice";
        }
        $group = [];
        foreach ($this->structuresSchemas[$structureName][$segGroupName]["elements"] as $element) {
            $attributes = $this->getElementAttributes($element);
            if ($attributes["Type"] == "segment") {
                $group[] = $this->addSegment($element["segment"], $attributes);
            }
            else if ($attributes["Type"] == "group") {
                $group[] = $this->addSegGroup($structureName, $element["group"], $attributes);
            }
        }
        // add group
        $segGroup = array_merge($segGroupAttributes, array("segments" => $group));
        return $segGroup;
    }

    /**
     * Add segment.
     *
     * @param string $segName Segment name (e.g. PID).
     * @param array<string, string> $segAttributes Segment attributes (see getElementAttributes()).
     *
     * @return array<string, mixed> Segment profile.
     */
    private function addSegment(string $segName, array $segAttributes): array
    {
        // set segment attributes
        $segAttributes["LongName"] = trim($this->segmentsSchemas[$segName]["LongName"]);
        $segAttributes["Chapter"] = trim($this->segmentsSchemas[$segName]["Chapter"]);
        
        // get fields
        $fields = [];
        foreach ($this->segmentsSchemas[$segName]["fields"] as $field) {
            // field attributes
            $fieldAttributes = $this->getFieldAttributes($field);
            $fields[] = $this->addField($field["field"], $fieldAttributes);
        }
        
        // add segment
        $segment = array_merge($segAttributes, array("fields" => $fields));
        return $segment;
    }

    /**
     * Add field.
     *
     * Applies the conditional predicates if fields constraints are enabled.
     *
     * @param string $fieldName Field identifier (e.g. PID.3).
     * @param array<string, string> $fieldAttributes Field attributes (see getFieldAttributes()).
     *
     * @return array<string, mixed> Field profile.
     */
    private function addField(string $fieldName, array $fieldAttributes): array
    {
        // set field attributes
        $fieldTable = ($this->fieldsSchemas[$fieldName]["Table"] != "") ? $this->fieldsSchemas[$fieldName]["Table"] : "";
        $fieldTable = (substr($fieldTable,0,3) == "HL7") ? substr($fieldTable,3) : $fieldTable;
        $fieldAttributes["Item"] = ($this->fieldsSchemas[$fieldName]["Item"] != "") ? sprintf('%05s', $this->fieldsSchemas[$fieldName]["Item"]) : "";
        $fieldAttributes["Datatype"] = $this->fieldsSchemas[$fieldName]["Type"];
        $fieldAttributes["Length"] = $this->fieldsSchemas[$fieldName]["maxLength"];
        $fieldAttributes["Table"] = $fieldTable;
        $fieldAttributes["LongName"] = $this->fieldsSchemas[$fieldName]["LongName"];
        $fieldAttributes["Chapter"] = $this->fieldsSchemas[$fieldName]["Chapter"];

        // condition predicates
        // --------------------
        if ($this->fieldsConstraints) {
            // PID 
            if (substr($fieldName, 0, 3) == "PID") {
                // PID-7 – Date/Time of Birth
                // This field is required if available in the following messages: A28, A31, A01, A04, A08
                // In all other messages, it is optional.
                if ($fieldName == "PID.7") {
                    $fieldAttributes["Usage"] = "O";
                    if (in_array($this->eventName, array("A28", "A31", "A01", "A04", "A08"))) {
                        $fieldAttributes["Usage"] = "RE";
                    }
                }

                // PID-8 – Administrative Sex
                // This field is required if available in the following messages: A28, A31, A01, A04
                // In all other messages, it is optional.
                if ($fieldName == "PID.8") {
                    $fieldAttributes["Usage"] = "O";
                    if (in_array($this->eventName, array("A28", "A31", "A01", "A04"))) {
                        $fieldAttributes["Usage"] = "RE";
                    }
                }

                // PID-11 – Patient Address
                // This field is required if available in the following messages: A28, A31, A01, A04
                // In all other messages, it is optional.
                if ($fieldName == "PID.11") {
                    $fieldAttributes["Usage"] = "O";
                    if (in_array($this->eventName, array("A28", "A31", "A01", "A04"))) {
                        $fieldAttributes["Usage"] = "RE";
                    }
                }

                // PID-33 – Last Update Date/Time
                // This field is required if available in the following messages: A28, A31, A01, A04, A08
                if ($fieldName == "PID.33") {
                    $fieldAttributes["Usage"] = "C";
                    if (in_array($this->eventName, array("A28", "A31", "A01", "A04", "A08"))) {
                        $fieldAttributes["Usage"] = "RE";
                    }
                }
            }
            
            // PV1
            if (substr($fieldName, 0, 3) == "PV1") {
                // PV1 - General conditions of use
                // All messages of transaction [ITI-30] that use this segment, actually use a pseudo-PV1, which is empty. The only field populated is PV1-2 “Patient Class” values “N” (Not Applicable).
                if (in_array($this->eventName, array("A28", "A31", "A40", "A47", "A24", "A37"))) {
                    if (!in_array($fieldName, array("PV1.2"))) {
                        $fieldAttributes["Usage"] = "X";
                    }
                }
                else {
                    // PV1-3 – Assigned Patient Location
                    // This field is required in the following messages: A02, A12
                    // In all other messages of transaction [ITI-31], it is required if known to the sender.
                    if ($fieldName == "PV1.3") {
                        $fieldAttributes["Usage"] = "RE";
                        if (in_array($this->eventName, array("A02", "A12"))) {
                            $fieldAttributes["Usage"] = "R";
                        }
                    }

                    // PV1-6 – Prior Patient Location
                    // This field is required in the following messages: A02
                    // In all other messages of transaction [ITI-31], it is optional.
                    if ($fieldName == "PV1.6") {
                        $fieldAttributes["Usage"] = "O";
                        if (in_array($this->eventName, array("A02"))) {
                            $fieldAttributes["Usage"] = "R";
                        }
                    }

                    // PV1-42 – Pending Location
                    // This field is required in the Pending Transfer (A15) and Cancel Pending Transfer (A26) messages.
                    // In all other messages of transaction [ITI-31], it is optional.
                    if ($fieldName == "PV1.42") {
                        $fieldAttributes["Usage"] = "O";
                        if (in_array($this->eventName, array("A15", "A26"))) {
                            $fieldAttributes["Usage"] = "R";
                        }
                    }
                }
            }

            // PV2
            if (substr($fieldName, 0, 3) == "PV2") {
                if (in_array($this->eventName, array("A01", "A14"))) {
                    if ($fieldName == "PV2.38") {
                        $fieldAttributes["Usage"] = "RE";
                    }
                }
            }

        }
        // --------------------

        // If dataType has components
        $dataType = $this->fieldsSchemas[$fieldName]["Type"];
        if (isset($this->dataTypesSchemas[$dataType]["components"])) {
            $components = [];
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $key => $component) {
                // component attributes
                $componentAttributes = $this->getComponentAttributes($component);

                // Note: fix.
                // The table associated with the field is not used to validate the components.
                //if ($key == 0 && $fieldTable != "") {
                //    // send hl7 table to first component
                //    $componentAttributes["Table"] =  $fieldTable;
                //}

                $components[] = $this->addComponent($component["dataType"], $componentAttributes);
            }
            $field = array_merge($fieldAttributes, array("components" => $components));
        } else {
            $field = $fieldAttributes;
        }

        return $field;

    }

    /**
     * Add component.
     *
     * Sub-components are added for components only, not recursively.
     *
     * @param string $componentName Component identifier (e.g. CX.1).
     * @param array<string, string> $componentAttributes Component attributes (see getComponentAttributes()).
     * @param bool $isSubComponent True for a sub-component.
     *
     * @return array<string, mixed> Component profile.
     */
    private function addComponent(string $componentName, array $componentAttributes, bool $isSubComponent = false): array
    {
        // get component attributes
        $componentTable = ($this->dataTypesSchemas[$componentName]["Table"] != "") ? $this->dataTypesSchemas[$componentName]["Table"] : "";
        $componentTable = (substr($componentTable,0,3) == "HL7") ? substr($componentTable,3) : $componentTable;
        
        if (isset($componentAttributes["Table"]) && $componentAttributes["Table"] != "") {
            $componentTable = $componentAttributes["Table"];
        }

        $componentAttributes["Type"] = $this->dataTypesSchemas[$componentName]["Type"];
        $componentAttributes["Table"] = $componentTable;
        $componentAttributes["LongName"] = $this->dataTypesSchemas[$componentName]["LongName"];
        $componentAttributes["maxLength"] = $this->dataTypesSchemas[$componentName]["maxLength"];

        // if dataType (component) has (sub)components
        $dataType = $this->dataTypesSchemas[$componentName]["Type"];
        if (isset($this->dataTypesSchemas[$dataType]["components"]) && ! $isSubComponent) {
            $subcomponents = [];
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $key => $subcomponent) {
                // subcomponent attributes
                $subcomponentAttributes = $this->getComponentAttributes($subcomponent);

                // Note: fix.
                // The table associated with the component is not used to validate the sub-components.
                //if ($key == 0 && $componentTable != "") {
                //    // send hl7 table to first component
                //    $subcomponentAttributes["Table"] =  $componentTable;
                //}

                $subcomponents[] = $this->addComponent($subcomponent["dataType"], $subcomponentAttributes, true);
            }
            $component = array_merge($componentAttributes, array("components" => $subcomponents));
        } else {
            $component = $componentAttributes;
        }

        return $component;
    }
}


$configDist = __DIR__ . '/06-create-json-profile.config.dist.php';
$configFile = __DIR__ . '/06-create-json-profile.config.php';

if (!is_file($configFile)) {
    if (!copy($configDist, $configFile)) {
        throw new RuntimeException(
            'Unable to create configuration file.'
        );
    }

    echo "Configuration file created: {$configFile}\n";
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException(
        'Invalid configuration file.'
    );
}

// config
$inputDir     = $config["inputDir"];
$outputDir    = $config["outputDir"];
$msgType      = $config["msgType"];
$ignoreEvents = $config["ignoreEvents"];
$fieldsConstr = $config["fieldsConstraints"];
$indent       = $config["indent"];
$pretty       = $config["pretty"];

if (!is_string($inputDir) || $inputDir === '') {
    throw new RuntimeException(
        'Missing inputDir configuration.'
    );
}

if (!is_dir($inputDir)) {
    throw new RuntimeException(
        "Directory not found: {$inputDir}"
    );
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(
        "Unable to create directory: {$outputDir}"
    );
}

if ($inputDir === $outputDir) {
    throw new RuntimeException(
        'Input and output directories must be different.'
    );
}

// main
$profilesGen = new HL7jsonProfilesGenerator();

// set input directories
$profilesGen->setDataTypesFilename($inputDir . "/dataTypes.json");
$profilesGen->setFieldsFilename($inputDir . "/fields.json");
$profilesGen->setSegmentsFilename($inputDir . "/segments.json");
$profilesGen->setStructuresInputDir($inputDir . "/structures");
$profilesGen->setMessageTypeFilename($inputDir . "/messageType.json");

// set output directory
$profilesGen->setProfilesOutputDir($outputDir);

// pretty print
$profilesGen->setPretty($pretty);
$profilesGen->setIndentationLevel($indent);

// fields constraints (update condition predicates)
$profilesGen->setFieldsConstraints($fieldsConstr);

// create profile
$profilesGen->createJsonProfiles($msgType, $ignoreEvents);
