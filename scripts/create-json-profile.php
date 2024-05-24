<?php
/**
 * Create json profile from json schema
 * 
 */

declare(strict_types=1);
require_once("config.php");


class HL7jsonProfilesGenerator {
    /**
     * @access public
     */
    // json schemas inputs
    public string $dataTypesFilename;
    public string $fieldsFilename;
    public string $segmentsFilename;
    public string $structuresInputDir;
    public string $messageTypeFilename;
    // json profiles output directory
    public string $profilesOutputDir;
    public int  $indent;
    public bool $pretty;
    public bool $fieldsConstraints;

    /**
     * @access private
     */
    private string $eventName;
    private array $messageType;
    private array $messageProfile;
    private array $structuresSchemas;
    private array $segmentsSchemas;
    private array $fieldsSchemas;
    private array $dataTypesSchemas;

    /**
     * Create a new instance
     */
    public function __construct() {
        $this->setDefaults();
        
    }

    private function setDefaults() {
        $this->structuresSchemas = array();
        $this->segmentsSchemas = array();
        $this->fieldsSchemas = array();
        $this->dataTypesSchemas = array();
        $this->eventName = "";
        $this->indent = 4;
        $this->pretty = true;
        $this->fieldsConstraints = false;
    }

    /**
     * Set json schemas input and output directories
     * 
     * @param string $filename / $directory
     */
    public function setDataTypesFilename($filename) {
        $this->dataTypesFilename = $filename;
    }

    public function setFieldsFilename($filename) {
        $this->fieldsFilename = $filename;
    }

    public function setSegmentsFilename($filename) {
        $this->segmentsFilename = $filename;
    }

    public function setMessageTypeFilename($filename) {
        $this->messageTypeFilename = $filename;
    }

    public function setStructuresInputDir($directory) {
        $this->structuresInputDir = $directory;
    }

    public function setProfilesOutputDir($directory) {
        $this->profilesOutputDir = $directory;
    }

    /**
     * Set indentation level (2 spaces, 4 spaces, etc.)
     * 
     * @param int $indent (nb of spaces)
     */
    public function setIndentationLevel($indent = 4) {
        $this->indent = intval($indent);
    }

    /**
     * Set pretty print
     * 
     * @param bool $pretty
     */
    public function setPretty($pretty) {
        $this->pretty = $pretty;
    }

    /**
     * Set fields constraints (update condition predicates)
     * 
     * @param bool $fieldsConstraints
     */
    public function setFieldsConstraints($fieldsConstraints) {
        $this->fieldsConstraints = $fieldsConstraints;
    }


    /**
     * Create json profiles
     * Write json profiles to profilesOutputDir
     * 
     * @param array $messageType
     * @param array $ignoreEvents
     */
    public function createJsonProfiles($messageType, $ignoreEvents = array()) {
        // Load json schemas
        $this->messageType = $this->loadJsonSchemas($this->messageTypeFilename);
        $this->dataTypesSchemas = $this->loadJsonSchemas($this->dataTypesFilename);
        $this->fieldsSchemas = $this->loadJsonSchemas($this->fieldsFilename);
        $this->segmentsSchemas = $this->loadJsonSchemas($this->segmentsFilename);

        // Create profiles
        foreach ($this->messageType as $type => $event) {
            if (in_array($type, $messageType)) {
                foreach ($event as $eventName => $strucureName) {
                    if (in_array($eventName, $ignoreEvents)) {
                        continue;
                    }
                    $this->eventName = $eventName;
                    $this->messageProfile = array();

                    // get strucureId
                    $nameParts = explode("-", $strucureName);
                    $strucureId = end($nameParts);
                    
                    // get message structure
                    if (!isset($this->structuresSchemas[$strucureName])) {
                        $this->structuresSchemas[$strucureName] = $this->loadJsonSchemas($this->structuresInputDir . "/" . $strucureName . ".json");
                    }

                    // 'root' groupName is strucureId
                    $groupName = $strucureId;
                    foreach ($this->structuresSchemas[$strucureName][$groupName]["elements"] as $element) {
                        $attributes = $this->getElementAttributes($element);
                        if ($attributes["Type"] == "segment") {
                            $this->messageProfile[] = $this->addSegment($element["segment"], $attributes);
                        }
                        else if ($attributes["Type"] == "group") {
                            $this->messageProfile[] = $this->addSegGroup($strucureName, $element["group"], $attributes);
                        }
                    }

                    $outputFilename = "$type-$eventName-$strucureId.json";
                    echo "- $outputFilename<br/>";
                    //file_put_contents($this->profilesOutputDir . "/" . $outputFilename, json_encode($this->messageProfile, JSON_PRETTY_PRINT));
                    if ($this->pretty) {
                        if ($this->indent == 4) {
                            $json = json_encode($this->messageProfile, JSON_PRETTY_PRINT);
                        } else {
                            $json = preg_replace_callback(
                                '/^(?: {4})+/m',
                                function($m) {
                                    return str_repeat(' ', $this->indent * (strlen($m[0]) / 4));
                                },
                                json_encode($this->messageProfile, JSON_PRETTY_PRINT)
                            );
                        }
                    } else {
                        $json = json_encode($this->messageProfile);
                    }
                    file_put_contents($this->profilesOutputDir . "/" . $outputFilename, $json);
                }
            }
        }
        echo "Done.";
    }

    /**
     * Load JSON structure schemas 
     *
     * @param $filename
     * @return array $data
     */
    private function loadJsonSchemas($filename) {
        $data = array();
        if (file_exists($filename) && is_file($filename)) {
            $jsonStr = file_get_contents($filename);
            $data = json_decode($jsonStr, true);
        }
        return $data;
    }

    /**
     * Get element usage
     * 
     * @param string $minOccurs
     * @param string $maxOccurs
     * @return string $usage
     */
    private function getElementUsage($minOccurs = "0", $maxOccurs = "0") {
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
     * Get group or segment attributes
     * 
     * @param array $element
     * @return array $attributes
     */
    private function getElementAttributes($element) {
        $elementType = (isset($element["segment"]) ? "segment" : "group");
        $elementName = trim($element[$elementType]);
        $elementMin = trim($element["minOccurs"]);
        $elementMax = trim($element["maxOccurs"]);
        $elementMax = ($elementMax == "unbounded" ? "*" : $elementMax);
        // Usage
        if (isset($element["Usage"])) {
            $elementUsage = trim($element["Usage"]);
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
     * Get field attributes
     * 
     * @param array $field
     * @return array $fieldAttributes
     */
    private function getFieldAttributes($field) {
        $fieldName = trim($field["field"]);
        $fieldMin = trim($field["minOccurs"]);
        $fieldMax = trim($field["maxOccurs"]);
        $fieldMax = ($fieldMax == "unbounded" ? "*" : $fieldMax);
        // Usage
        if (isset($field["Usage"])) {
            $fieldUsage = trim($field["Usage"]);
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
     * Get component attributes
     * 
     * @param array $component
     * @return array $componentAttributes
     */
    private function getComponentAttributes($component) {
        $componentName = trim($component["dataType"]);
        $componentMin = trim($component["minOccurs"]);
        $componentMax = trim($component["maxOccurs"]);
        $componentMax = ($componentMax == "unbounded" ? "*" : $componentMax);
        // Usage
        if (isset($component["Usage"])) {
            $componentUsage = trim($component["Usage"]);
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
     * Add segment group
     * 
     * @param string $strucureName (strucureId)
     * @param string $segGroupName
     * @param array $segGroupAttributes
     * @return array $segGroup
     */
    private function addSegGroup($strucureName, $segGroupName, $segGroupAttributes) {
        // set segGroup attributes
        $segGroupAttributes["LongName"] = $segGroupAttributes["Name"];
        $group = array();
        foreach ($this->structuresSchemas[$strucureName][$segGroupName]["elements"] as $element) {
            $attributes = $this->getElementAttributes($element);
            if ($attributes["Type"] == "segment") {
                $group[] = $this->addSegment($element["segment"], $attributes);
            }
            else if ($attributes["Type"] == "group") {
                $group[] = $this->addSegGroup($strucureName, $element["group"], $attributes);
            }
        }
        // add group
        $segGroup = array_merge($segGroupAttributes, array("segments" => $group));
        return $segGroup;
    }

    /**
     * Add segment schema
     * 
     * @param string $segName
     * @param array $segAttributes
     * @return array $segment
     */
    private function addSegment($segName, $segAttributes) {
        // set segment attributes
        $segAttributes["LongName"] = trim($this->segmentsSchemas[$segName]["LongName"]);
        $segAttributes["Chapter"] = trim($this->segmentsSchemas[$segName]["Chapter"]);
        
        // get fields
        $fields = array();
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
     * Add field schema
     * 
     * @param string $fieldName
     * @param array $fieldAttributes
     * @return array $field
     */
    private function addField($fieldName, $fieldAttributes) {
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
                    if (!in_array($fieldName, array("PV1.1", "PV1.2"))) {
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
        }
        // --------------------

        // If dataType has components
        $dataType = $this->fieldsSchemas[$fieldName]["Type"];
        if (isset($this->dataTypesSchemas[$dataType]["components"])) {
            $components = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $key => $component) {
                // component attributes
                $componentAttributes = $this->getComponentAttributes($component);
                if ($key == 0 && $fieldTable != "") {
                    // send hl7 tabme to first component
                    $componentAttributes["Table"] =  $fieldTable;
                }
                $components[] = $this->addComponent($component["dataType"], $componentAttributes);
            }
            $field = array_merge($fieldAttributes, array("components" => $components));
        } else {
            $field = $fieldAttributes;
        }

        return $field;

    }

    /**
     * Add component schema
     * 
     * @param string $componentName
     * @param array $componentAttributes
     * @param bool $isSubComponent
     * @return array $component
     */
    private function addComponent($componentName, $componentAttributes, $isSubComponent = false) {
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
            $subcomponents = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $key => $subcomponent) {
                // subcomponent attributes
                $subcomponentAttributes = $this->getComponentAttributes($subcomponent);
                if ($key == 0 && $componentTable != "") {
                    // send hl7 tabme to first component
                    $subcomponentAttributes["Table"] =  $componentTable;
                }
                $subcomponents[] = $this->addComponent($subcomponent["dataType"], $subcomponentAttributes, true);
            }
            $component = array_merge($componentAttributes, array("components" => $subcomponents));
        } else {
            $component = $componentAttributes;
        }

        return $component;
    }
}






// config
$inputDir     = $createJsonProfile["inputDir"];
$outputDir    = $createJsonProfile["outputDir"];
$msgType      = $createJsonProfile["msgType"];
$ignoreEvents = $createJsonProfile["ignoreEvents"];
$fieldsConstr = $createJsonProfile["fieldsConstraints"];
$indent       = $createJsonProfile["indent"];
$pretty       = $createJsonProfile["pretty"];

// main
$profilesGen = new HL7jsonProfilesGenerator();

// set input directories
$profilesGen->setDataTypesFilename($inputDir . "/dataTypes/dataTypes.json");
$profilesGen->setFieldsFilename($inputDir . "/fields/fields.json");
$profilesGen->setSegmentsFilename($inputDir . "/segments/segments.json");
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





