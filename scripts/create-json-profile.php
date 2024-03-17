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

    /**
     * @access private
     */
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
                    file_put_contents($this->profilesOutputDir . "/" . $outputFilename, json_encode($this->messageProfile, JSON_PRETTY_PRINT));
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
            "Max" => $fieldMax
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
            "Max" => $componentMax
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
        $fieldAttributes["Item"] = sprintf('%05s', $this->fieldsSchemas[$fieldName]["Item"]);
        $fieldAttributes["Datatype"] = $this->fieldsSchemas[$fieldName]["Type"];
        $fieldAttributes["Length"] = $this->fieldsSchemas[$fieldName]["maxLength"];
        $fieldAttributes["Table"] = $this->fieldsSchemas[$fieldName]["Table"];
        $fieldAttributes["LongName"] = $this->fieldsSchemas[$fieldName]["LongName"];
        $fieldAttributes["Chapter"] = $this->fieldsSchemas[$fieldName]["Chapter"];

        $dataType = $this->fieldsSchemas[$fieldName]["Type"];
        // If dataType has components
        if (isset($this->dataTypesSchemas[$dataType]["components"])) {
            $components = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $component) {
                // component attributes
                $componentAttributes = $this->getComponentAttributes($component);
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
        $componentAttributes["Type"] = $this->dataTypesSchemas[$componentName]["Type"];
        $componentAttributes["Table"] = $this->dataTypesSchemas[$componentName]["Table"];
        $componentAttributes["LongName"] = $this->dataTypesSchemas[$componentName]["LongName"];
        $componentAttributes["maxLength"] = $this->dataTypesSchemas[$componentName]["maxLength"];
        
        $dataType = $this->dataTypesSchemas[$componentName]["Type"];
        // if dataType (component) has (sub)components
        if (isset($this->dataTypesSchemas[$dataType]["components"]) && ! $isSubComponent) {
            $subcomponents = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $subcomponent) {
                // subcomponent attributes
                $subcomponentAttributes = $this->getComponentAttributes($subcomponent);
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

// create profile
$profilesGen->createJsonProfiles($msgType, $ignoreEvents);





