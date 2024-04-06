<?php
/**
 * Create json profile from json schema
 * 
 */

declare(strict_types=1);
require_once("config.php");


class HL7xmlProfilesGenerator {
    /**
     * @access public
     */
    // json schemas inputs
    public string $dataTypesFilename;
    public string $fieldsFilename;
    public string $segmentsFilename;
    public string $structuresInputDir;
    public string $messageTypeFilename;
    public string $eventDescFilename;
    // json profiles output directory
    public string $profilesOutputDir;

    /**
     * @access private
     */
    private string $eventName;
    private array $messageType;
    private array $eventDesc;
    private array $structuresSchemas;
    private array $segmentsSchemas;
    private array $fieldsSchemas;
    private array $dataTypesSchemas;
    private object $messageProfile;

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

    public function setEventDescFilename($filename) {
        $this->eventDescFilename = $filename;
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
     * @param string $HL7Version
     * @param array $messageType
     * @param array $ignoreEvents
     */
    public function createXmlProfiles($HL7Version, $messageType, $ignoreEvents = array()) {
        // Load json schemas
        $this->messageType = $this->loadJsonSchemas($this->messageTypeFilename);
        $this->eventDesc = $this->loadJsonSchemas($this->eventDescFilename);
        
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
                    $xmlDoc = new DOMDocument("1.0", "UTF-8" );

                    // get strucureId
                    $nameParts = explode("-", $strucureName);
                    $strucureId = end($nameParts);

                    $outputFilename = "$type-$eventName-$strucureId.xml";
                    $eventDesc = $this->eventDesc[$type][$eventName];

                    // output format 
                    $xmlDoc->preserveWhiteSpace = false;
                    $xmlDoc->formatOutput = true;
                    
                    // add comment
                    // $xmlDoc->appendChild($xmlDoc->createComment("\r\n " . $outputFilename . " " . date('Y-m-d h:i:s') . "\r\n"));

                    // create stylesheet line
                    $xslt = $xmlDoc->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="http://gazelle.ihe.net/xsl/mp2htm.xsl"');
                    $xmlDoc->appendChild($xslt);

                    // add HL7v2xConformanceProfile (root node)
                    $HL7v2xConformanceProfile = $xmlDoc->appendChild($xmlDoc->createElement("HL7v2xConformanceProfile"));

                    // add HL7v2xConformanceProfile attributes
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createAttribute('xmlns:xsi'))->appendChild($xmlDoc->createTextNode("http://www.w3.org/2001/XMLSchema-instance"));
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createAttribute('xsi:noNamespaceSchemaLocation'))->appendChild($xmlDoc->createTextNode("http://gazelle.ihe.net/xsd/HL7MessageProfileSchema.xsd"));
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createAttribute('HL7Version'))->appendChild($xmlDoc->createTextNode($HL7Version));
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createAttribute('ProfileType'))->appendChild($xmlDoc->createTextNode("Constrainable"));

                    // add MetaData
                    $MetaData = $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("MetaData"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Name'))->appendChild($xmlDoc->createTextNode("ITI"));
                    $MetaData->appendChild($xmlDoc->createAttribute('OrgName'))->appendChild($xmlDoc->createTextNode("IHE"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Version'))->appendChild($xmlDoc->createTextNode($HL7Version));
                    $MetaData->appendChild($xmlDoc->createAttribute('Status'))->appendChild($xmlDoc->createTextNode("DRAFT"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Topics'))->appendChild($xmlDoc->createTextNode("confsig-IHE-" . $HL7Version . "-profile-accNE_accAL-Deferred"));

                    // add ImpNote
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("ImpNote"))->appendChild($xmlDoc->createTextNode($eventDesc));
                    
                    // add UseCase
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("UseCase"));
                    
                    // add Encodings
                    $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("Encodings"))->appendChild($xmlDoc->createElement("Encoding"))->appendChild($xmlDoc->createTextNode("ER7"));

                    // add DynamicDef
                    $DynamicDef = $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("DynamicDef"));
                    $DynamicDef->appendChild($xmlDoc->createAttribute('AccAck'))->appendChild($xmlDoc->createTextNode("NE"));
                    $DynamicDef->appendChild($xmlDoc->createAttribute('AppAck'))->appendChild($xmlDoc->createTextNode("AL"));
                    $DynamicDef->appendChild($xmlDoc->createAttribute('MsgAckMode'))->appendChild($xmlDoc->createTextNode("Deferred"));

                    // add HL7v2xStaticDef
                    $HL7v2xStaticDef = $HL7v2xConformanceProfile->appendChild($xmlDoc->createElement("HL7v2xStaticDef"));
                    $HL7v2xStaticDef->appendChild($xmlDoc->createAttribute('MsgType'))->appendChild($xmlDoc->createTextNode($type));
                    $HL7v2xStaticDef->appendChild($xmlDoc->createAttribute('EventType'))->appendChild($xmlDoc->createTextNode($eventName));
                    $HL7v2xStaticDef->appendChild($xmlDoc->createAttribute('MsgStructID'))->appendChild($xmlDoc->createTextNode($strucureId));
                    $HL7v2xStaticDef->appendChild($xmlDoc->createAttribute('EventDesc'))->appendChild($xmlDoc->createTextNode($eventDesc));
                    $HL7v2xStaticDef->appendChild($xmlDoc->createAttribute('Role'))->appendChild($xmlDoc->createTextNode("Sender"));

                    // add HL7v2xStaticDef MetaData
                    $MetaData = $HL7v2xStaticDef->appendChild($xmlDoc->createElement("MetaData"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Name'))->appendChild($xmlDoc->createTextNode("ITI"));
                    $MetaData->appendChild($xmlDoc->createAttribute('OrgName'))->appendChild($xmlDoc->createTextNode("IHE"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Version'))->appendChild($xmlDoc->createTextNode($HL7Version));
                    $MetaData->appendChild($xmlDoc->createAttribute('Status'))->appendChild($xmlDoc->createTextNode("DRAFT"));
                    $MetaData->appendChild($xmlDoc->createAttribute('Topics'))->appendChild($xmlDoc->createTextNode("confsig-IHE-" . $HL7Version . "-static-" . $type . "-" . $eventName . "-null-" . $strucureId . "-" . $HL7Version . "-DRAFT-Sender"));
                    
                    // get message structure
                    if (!isset($this->structuresSchemas[$strucureName])) {
                        $this->structuresSchemas[$strucureName] = $this->loadJsonSchemas($this->structuresInputDir . "/" . $strucureName . ".json");
                    }

                    // 'root' groupName is strucureId
                    $groupName = $strucureId;
                    $this->messageProfile = $xmlDoc;
                    foreach ($this->structuresSchemas[$strucureName][$groupName]["elements"] as $element) {
                        $attributes = $this->getElementAttributes($element);
                        if ($attributes["Type"] == "segment") {
                            // add segment node
                            $segNode = $HL7v2xStaticDef->appendChild($xmlDoc->createElement("Segment"));
                            $this->addSegment($segNode, $element["segment"], $attributes);
                        }
                        else if ($attributes["Type"] == "group") {
                            // add group node
                            $segGroupNode = $HL7v2xStaticDef->appendChild($xmlDoc->createElement("SegGroup"));
                            $this->addSegGroup($segGroupNode, $strucureName, $element["group"], $attributes);
                        }
                    }

                    echo "- $outputFilename<br/>";
                    $xmlDoc->save($this->profilesOutputDir . "/" . $outputFilename);
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
     * @param object $segGroupNode
     * @param string $strucureName (strucureId)
     * @param string $segGroupName
     * @param array $segGroupAttributes
     * @return array $segGroup
     */
    private function addSegGroup($segGroupNode, $strucureName, $segGroupName, $segGroupAttributes) {
        // add attributes to segment group node
        $segGroupAttributes["LongName"] = $segGroupAttributes["Name"];
        $segGroupNode->appendChild($this->messageProfile->createAttribute('Name'))->appendChild($this->messageProfile->createTextNode($segGroupName));
        $segGroupNode->appendChild($this->messageProfile->createAttribute('LongName'))->appendChild($this->messageProfile->createTextNode($segGroupAttributes["LongName"]));
        $segGroupNode->appendChild($this->messageProfile->createAttribute('Usage'))->appendChild($this->messageProfile->createTextNode($segGroupAttributes["Usage"]));
        $segGroupNode->appendChild($this->messageProfile->createAttribute('Min'))->appendChild($this->messageProfile->createTextNode($segGroupAttributes["Min"]));
        $segGroupNode->appendChild($this->messageProfile->createAttribute('Max'))->appendChild($this->messageProfile->createTextNode($segGroupAttributes["Max"]));

        foreach ($this->structuresSchemas[$strucureName][$segGroupName]["elements"] as $element) {
            $attributes = $this->getElementAttributes($element);
            if ($attributes["Type"] == "segment") {
                // add segment node
                $segNode = $segGroupNode->appendChild($this->messageProfile->createElement("Segment"));
                $this->addSegment($segNode, $element["segment"], $attributes);
            }
            else if ($attributes["Type"] == "group") {
                // add group node
                $segGrpNode = $segGroupNode->appendChild($this->messageProfile->createElement("SegGroup"));
                $this->addSegGroup($segGrpNode, $strucureName, $element["group"], $attributes);
            }
        }
    }

    /**
     * Add segment schema
     * 
     * @param object $segNode
     * @param string $segName
     * @param array $segAttributes
     * @return array $segment
     */
    private function addSegment($segNode, $segName, $segAttributes) {
        // add attributes to segment node
        $segAttributes["LongName"] = trim($this->segmentsSchemas[$segName]["LongName"]);
        $segAttributes["Chapter"] = trim($this->segmentsSchemas[$segName]["Chapter"]);
        $segNode->appendChild($this->messageProfile->createAttribute('Name'))->appendChild($this->messageProfile->createTextNode($segName));
        $segNode->appendChild($this->messageProfile->createAttribute('LongName'))->appendChild($this->messageProfile->createTextNode($segAttributes["LongName"]));
        $segNode->appendChild($this->messageProfile->createAttribute('Usage'))->appendChild($this->messageProfile->createTextNode($segAttributes["Usage"]));
        $segNode->appendChild($this->messageProfile->createAttribute('Min'))->appendChild($this->messageProfile->createTextNode($segAttributes["Min"]));
        $segNode->appendChild($this->messageProfile->createAttribute('Max'))->appendChild($this->messageProfile->createTextNode($segAttributes["Max"]));
        // $segNode->appendChild($this->messageProfile->createAttribute('Chapter'))->appendChild($this->messageProfile->createTextNode($segAttributes["Chapter"]));

        // get fields
        $fields = array();
        foreach ($this->segmentsSchemas[$segName]["fields"] as $field) {
            // field attributes
            $fieldAttributes = $this->getFieldAttributes($field);

            // add field node
            $fieldNode = $segNode->appendChild($this->messageProfile->createElement("Field"));
            $this->addField($fieldNode, $field["field"], $fieldAttributes);
        }
    }

    /**
     * Add field schema
     * 
     * @param object $fieldNode
     * @param string $fieldName
     * @param array $fieldAttributes
     * @return array $field
     */
    private function addField($fieldNode, $fieldName, $fieldAttributes) {
        // set field attributes
        // $fieldAttributes["Item"] = sprintf('%05s', $this->fieldsSchemas[$fieldName]["Item"]);
        $fieldAttributes["Item"] = ($this->fieldsSchemas[$fieldName]["Item"] != "") ? sprintf('%05s', $this->fieldsSchemas[$fieldName]["Item"]) : "";
        $fieldAttributes["Datatype"] = $this->fieldsSchemas[$fieldName]["Type"];
        $fieldAttributes["Length"] = $this->fieldsSchemas[$fieldName]["maxLength"];
        // $fieldAttributes["Table"] = ($this->fieldsSchemas[$fieldName]["Table"] != "") ? substr($this->fieldsSchemas[$fieldName]["Table"],3) : "";
        $fieldAttributes["Table"] = ($this->fieldsSchemas[$fieldName]["Table"] != "") ? $this->fieldsSchemas[$fieldName]["Table"] : "";
        $fieldAttributes["Table"] = (substr($fieldAttributes["Table"],0,3) == "HL7") ? substr($fieldAttributes["Table"],3) : $fieldAttributes["Table"];
        $fieldAttributes["LongName"] = $this->fieldsSchemas[$fieldName]["LongName"];
        $fieldAttributes["Chapter"] = $this->fieldsSchemas[$fieldName]["Chapter"];

        // condition predicates
        // --------------------
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
        // --------------------

        // add field attributes
        $fieldNode->appendChild($this->messageProfile->createAttribute('Name'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["LongName"]));
        $fieldNode->appendChild($this->messageProfile->createAttribute('Usage'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Usage"]));
        $fieldNode->appendChild($this->messageProfile->createAttribute('Min'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Min"]));
        $fieldNode->appendChild($this->messageProfile->createAttribute('Max'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Max"]));
        $fieldNode->appendChild($this->messageProfile->createAttribute('Datatype'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Datatype"]));
        $fieldNode->appendChild($this->messageProfile->createAttribute('Length'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Length"]));
        if ($fieldAttributes["Table"] != "") {
            $fieldNode->appendChild($this->messageProfile->createAttribute('Table'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Table"]));
        }
        $fieldNode->appendChild($this->messageProfile->createAttribute('ItemNo'))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Item"]));

        // add Reference node if Chapter exists
        $fieldNode->appendChild($this->messageProfile->createElement("Reference"))->appendChild($this->messageProfile->createTextNode($fieldAttributes["Chapter"]));

        // If dataType has components
        $dataType = $this->fieldsSchemas[$fieldName]["Type"];
        if (isset($this->dataTypesSchemas[$dataType]["components"])) {
            $components = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $component) {
                // component attributes
                $componentAttributes = $this->getComponentAttributes($component);

                // add component node
                $componentNode = $fieldNode->appendChild($this->messageProfile->createElement("Component"));
                $this->addComponent($componentNode, $component["dataType"], $componentAttributes);
            }
        }
    }

    /**
     * Add component schema
     * 
     * @param object $componentNode
     * @param string $componentName
     * @param array $componentAttributes
     * @param bool $isSubComponent
     * @return array $component
     */
    private function addComponent($componentNode, $componentName, $componentAttributes, $isSubComponent = false) {
        // get component attributes
        $componentAttributes["Type"] = $this->dataTypesSchemas[$componentName]["Type"];
        //$componentAttributes["Table"] = ($this->dataTypesSchemas[$componentName]["Table"] != "") ? substr($this->dataTypesSchemas[$componentName]["Table"],3) : "";
        $componentAttributes["Table"] = ($this->dataTypesSchemas[$componentName]["Table"] != "") ? $this->dataTypesSchemas[$componentName]["Table"] : "";
        $componentAttributes["Table"] = (substr($componentAttributes["Table"],0,3) == "HL7") ? substr($componentAttributes["Table"],3) : $componentAttributes["Table"];
        $componentAttributes["LongName"] = $this->dataTypesSchemas[$componentName]["LongName"];
        $componentAttributes["maxLength"] = $this->dataTypesSchemas[$componentName]["maxLength"];
        
        // add component attributes
        $componentNode->appendChild($this->messageProfile->createAttribute('Name'))->appendChild($this->messageProfile->createTextNode($componentAttributes["LongName"]));
        $componentNode->appendChild($this->messageProfile->createAttribute('Usage'))->appendChild($this->messageProfile->createTextNode($componentAttributes["Usage"]));
        $componentNode->appendChild($this->messageProfile->createAttribute('Datatype'))->appendChild($this->messageProfile->createTextNode($componentAttributes["Type"]));
        $componentNode->appendChild($this->messageProfile->createAttribute('Length'))->appendChild($this->messageProfile->createTextNode($componentAttributes["maxLength"]));
        if ($componentAttributes["Table"] != "") {
            $componentNode->appendChild($this->messageProfile->createAttribute('Table'))->appendChild($this->messageProfile->createTextNode($componentAttributes["Table"]));
        }
        
        // if dataType (component) has (sub)components
        $dataType = $this->dataTypesSchemas[$componentName]["Type"];
        if (isset($this->dataTypesSchemas[$dataType]["components"]) && ! $isSubComponent) {
            $subcomponents = array();
            foreach ($this->dataTypesSchemas[$dataType]["components"] as $subcomponent) {
                // subcomponent attributes
                $subcomponentAttributes = $this->getComponentAttributes($subcomponent);

                // add subcomponent node
                $subcomponentNode = $componentNode->appendChild($this->messageProfile->createElement("SubComponent"));
                $this->addComponent($subcomponentNode, $subcomponent["dataType"], $subcomponentAttributes, true);
            }
        }
    }
}






// config
$HL7Version   = $createXmlProfile["HL7Version"];
$inputDir     = $createXmlProfile["inputDir"];
$outputDir    = $createXmlProfile["outputDir"];
$msgType      = $createXmlProfile["msgType"];
$ignoreEvents = $createXmlProfile["ignoreEvents"];

// main
$profilesGen = new HL7xmlProfilesGenerator();

// set input directories
$profilesGen->setDataTypesFilename($inputDir . "/dataTypes/dataTypes.json");
$profilesGen->setFieldsFilename($inputDir . "/fields/fields.json");
$profilesGen->setSegmentsFilename($inputDir . "/segments/segments.json");
$profilesGen->setStructuresInputDir($inputDir . "/structures");
$profilesGen->setMessageTypeFilename($inputDir . "/messageType.json");
$profilesGen->setEventDescFilename($inputDir . "/eventDesc.json");

// set output directory
$profilesGen->setProfilesOutputDir($outputDir);

// create profile
$profilesGen->createXmlProfiles($HL7Version, $msgType, $ignoreEvents);





