<?php

declare(strict_types=1);

namespace HL7;

/**
 * 
 * 
 * 
 */
class HL7Utils {

    /**
     * @access public
     * 
     */
    // Messgage (parse)
    public string $parseMessageError;
    public string $messageType;
    public string $messageTriggerEvent;
    public string $messageStructID;

    // Profile
    public string $profilePath;
    public string $profileMsgType;
    public string $profileEventType;
    public string $profileMsgStructID;
    public string $profileEventDesc;
    
    // HL7 Table
    public string $tablePath;
    public string $tableFileName;
    
    // Validation
    public array $testReport;
    public array $validationReport;
    public array $logs;


    /**
     * @access private
     * 
     */
    // Field Separator & Encoding Characters
    private string $segmentSeparator;
    private string $fieldSeparator;
    private string $componentSeparator;
    private string $fieldRepeatSeparator;
    private string $escapeChar;
    private string $subComponentSeparator;
    // Message
    private array $msgParse; // array[location][segmentName][field][repeat][component][subcomponent]
    // Profile
    private object $profile;
    private array $profileListOfSegmentNames;
    private array $messageListOfSegmentNames;
    private array $nonStandardSegment;
    private int $profilLocation;
    // Group
    private bool $isSegmentInGroup;
    private bool $isFirstSegmentInGroup;
    private array $segmentsInGroup;
    private string $groupName;
    private string $groupLongName;
    private string $groupUsage;
    private int $groupMin;
    private string $groupMaxStr;
    private float $groupMax;
    // Segment
    private int $segmentLocation; // Position (sequence) 
    private string $segmentName;
    private string $segmentLongName;
    private string $segmentUsage;
    private int $segmentMin;
    private string $segmentMaxStr;
    private float $segmentMax;
    // Field
    private int $fieldLocation;
    private int $fieldrepeat;
    private string $fieldName;
    private string $fieldUsage;
    private int $fieldMin;
    private string $fieldMaxStr;
    private float $fieldMax;
    private string $fieldDatatype;
    private string $fieldLength;
    private string $fieldItemNo; // HL7 unique reference for this field
    private string $fieldTable;  // Table reference
    private string $fieldReference; // HL7 chapter reference
    private string $fieldImpNote;
    // Component
    private int $componentLocation;
    private string $componentName;
    private string $componentUsage;
    private string $componentDatatype;
    private string $componentLength;
    private string $componentTable;
    private string $componentImpNote;
    // SubComponent
    private int $subcomponentLocation;
    private string $subcomponentName;
    private string $subcomponentUsage;
    private string $subcomponentDatatype;
    private string $subcomponentLength;
    private string $subcomponentConstantValue;
    private string $subcomponentTable;
    private string $subcomponentImpNote;
    // HL7 Table
    private object $table;
    private array $hl7tables;

    /**
    * Create a new instance of the HL7 Parser
    * 
    */
    public function __construct() {
        $this->setDefaults();
    }

    /**
    * Getters and Setters
    * 
    */
    private function setDefaults() {
        // Field Separator & Encoding Characters
        $this->segmentSeparator = '\r'; // 0x0D (CR)
        $this->fieldSeparator = '|';
        $this->componentSeparator = '^';
        $this->fieldRepeatSeparator = '~';
        $this->escapeChar = '\\';
        $this->subComponentSeparator = '&';
        // Message
        $this->msgParse = array();
        $this->messageType = "";
        $this->messageTriggerEvent = "";
        $this->messageStructID = "";
        // Profile
        $this->profilePath = __DIR__ . "/../profiles/xml-2.5";
        $this->profileMsgType = "";
        $this->profileEventType = "";
        $this->profileMsgStructID = "";
        $this->profileEventDesc = "";
        $this->tablePath = __DIR__ . "/../profiles";
        $this->tableFileName = "tables-2.5.json";
        // hl7 tables
        $this->hl7tables = array();
        $this->hl7FullTables = array();
        // Validation
        $this->validationReport = array();
        $this->testReport = array();
        $this->logs = array();
        $this->testReportErrorCnt = 0;
        // ..
        $this->isSegmentInGroup = false;
        $this->isFirstSegmentInGroup = false;
    }

    private function setParseMessageError($errorMessage) {
        $this->parseMessageError = $errorMessage;
    }

    public function getParseMessageError() {
        return $this->parseMessageError;
    }

    public function getParseMessage() {
        return $this->msgParse;
    }

    public function getMsgType() {
        return $this->messageType;
    }

    public function getMsgTriggerEvent() {
        return $this->messageTriggerEvent;
    }

    public function getMsgStructID() {
        return $this->messageStructID;
    }

    public function getHL7Tables() {
        return $this->hl7tables;
    }

    public function getHL7FullTables() {
        return $this->hl7FullTables;
    }

    public function getTestReport() {
        return $this->testReport;
    }

    public function getTestReportErrorCnt() {
        return $this->testReportErrorCnt;
    }

    public function getValidationReport() {
        return $this->validationReport;
    }

    public function getLogs() {
        return $this->logs;
    }





    /**
     * 
     * PARSE HL7 MESSAGE
     * -----------------
     * 
     */

    /**
     * Parse hl7 message
     * Simple (naive) parser
     * 
     * @param string $msgStr
     * @param bool $escapeCharacters
     * @return bool
     */
    public function parseMessage($msgStr = '', $escapeCharacters = false) {
        
        if(empty($msgStr)) {
            $this->setParseMessageError("Message is empty.");
            return false;
        }

        // Extract ctrl string : fisrt segment name (MSH) +  field separator (MSH-1) + encoding characters (MSH-2)
        $ctrl = substr($msgStr, 0, 9);
        if (!preg_match('/^([A-Z0-9]{3})(.)(.)(.)(.)(.)(.)/', $ctrl, $matches)) {
            // --------->    MSH         |  ^  ~  \  &  |
            $this->setParseMessageError("This is not a valid message. Please check MSH segment.");
            return false;
        }
        // first segment name (matches[1]) must be "MSH"
        if( $matches[1] != "MSH" ) {
            $this->setParseMessageError("This is not a valid message. MSH segment not found.");
            return false;
        }
        // field separator (matches[2]) must be the same as the fist field separator (matches[7])
        if( $matches[2] != $matches[7] ) {
            $this->setParseMessageError("This is not a valid message. Invalid field separator.");
            return false;
        }

        // Set separator characters ($matches[2] to [6])
        $this->fieldSeparator = $matches[2];
        $this->componentSeparator = $matches[3];
        $this->fieldRepeatSeparator = $matches[4];
        $this->escapeChar = $matches[5];
        $this->subComponentSeparator = $matches[6];

        // Split message to segments
        $segmentLocation = 0;
        $segments = preg_split("/[\n\r" . $this->segmentSeparator . ']/', $msgStr, -1, PREG_SPLIT_NO_EMPTY);
        // foreach segment
        foreach ($segments as $segment) {
            $segmentLocation++;
            $fields = preg_split("/\\" . $this->fieldSeparator .'/', $segment);
            $segmentName = $fields[0];
            $currentSegment = array();

            // foreach field
            for($i=1; $i<count($fields);$i++) {
                $n = $i;
                if( $segmentName === "MSH" ) {
                    // MSH-1 is fieldSeparator
                    $n = $i+1;
                }

                if( $segmentName === "MSH" && $i === 1) {
                    // set MSH-1 (fieldSeparator)
                    $currentSegment[1] = array(0 => $this->fieldSeparator);
                }

                $currentSegment[$n] = array();

                // get occurrences
                $fieldRepeats = preg_split("/\\".$this->fieldRepeatSeparator."/", $fields[$i], -1);
                if( $fieldRepeats === false || ($segmentName === "MSH" && $i === 1) ) {
                    // MSH-2 = '^~\&'
                    $fieldRepeats = array( 0 => $fields[$i] );
                }
                $rep = 0;
                foreach ($fieldRepeats as $fieldRepeat) {
                    $currentSegment[$n][$rep] = array();

                    // get components
                    $components = preg_split("/\\".$this->componentSeparator."/", $fieldRepeat, -1);
                    if( $components === false || ($segmentName === "MSH" && $i === 1) ) {
                        $components = array( 0 => $fieldRepeat);
                    }
                    // foreach component
                    for($j=0; $j<count($components);$j++) {
                        $currentSegment[$n][$rep][$j+1] = array();

                        // get subcomponents
                        $subcomponents = preg_split("/\\".$this->subComponentSeparator."/", $components[$j], -1);
                        if( $subcomponents === false || ($segmentName === "MSH" && $i===1) ) {
                            $subcomponents = array( 0 => $components[$j]);
                        }

                        // foreach subcomponent
                        for($k=0; $k<count($subcomponents);$k++) {
                            $val = $subcomponents[$k];
                            
                            if( $escapeCharacters ) {
                                // escape the field separator
                                $val = str_replace($this->escapeChar."F".$this->escapeChar,$this->fieldSeparator,$val);
                                // escape the encoding characters
                                $val = str_replace($this->escapeChar."S".$this->escapeChar,$this->componentSeparator,$val);
                                $val = str_replace($this->escapeChar."R".$this->escapeChar,$this->fieldRepeatSeparator,$val);
                                $val = str_replace($this->escapeChar."E".$this->escapeChar,$this->escapeChar,$val);
                                $val = str_replace($this->escapeChar."T".$this->escapeChar,$this->subComponentSeparator,$val);
                                // todo
                                // \Xdddd...\   hexadecimal data 
                                // \Zdddd...\   locally defined escape sequence 
                            }
                            
                            $currentSegment[$n][$rep][$j+1][$k+1] = $val;
                            
                            //if( count($subcomponents) > 1 ) {
                            //    $currentSegment[$n][$rep][$j+1][$k+1] = $val;
                            //}
                            //else if( count($components) > 1 ) {
                            //    // only one subcomponent
                            //   $currentSegment[$n][$rep][$j+1] = $val;
                            //}
                            //else {
                            //     // only one component
                            //    $currentSegment[$n][$rep] = $val;
                            //}
                        }
                    }
                    $rep++;
                }   
            }

            // Add segment to msgParse
            $this->msgParse[$segmentLocation] = array(
                $segmentName => $currentSegment,
            );
        }
        $this->setMessageContext();
        return true;
    }

    /**
     * Set hl7 message informations
     * message type, trigger event, strucutre ID
     */
    private function setMessageContext() {
        $this->messageType = (isset($this->msgParse[1]["MSH"][9][0][1])) ? $this->componentToString($this->msgParse[1]["MSH"][9][0][1]) : "";
        $this->messageTriggerEvent = (isset($this->msgParse[1]["MSH"][9][0][2])) ? $this->componentToString($this->msgParse[1]["MSH"][9][0][2]) : "";
        $this->messageStructID = (isset($this->msgParse[1]["MSH"][9][0][3])) ? $this->componentToString($this->msgParse[1]["MSH"][9][0][3]) : "";
    }





    /**
     * 
     * HL7 MESSAGE TO STRING
     * ---------------------
     * 
     * todo : 
     * - errors : messageToString
     * 
     */

    /**
     * Return msgParse to string
     * Note : msgParse[segmentLocation][segmentName][field][repeat][component][subcomponent]
     * 
     * @return string
     */
    public function messageToString() {
        if(empty($this->msgParse)) {
            // todo
            return false;
        }

        $messageStr = "";
        foreach($this->msgParse as $segmentLocation) {
            $segmentStr = $this->segmentLocationToString($segmentLocation);
            $messageStr .= $segmentStr . "\r\n"; // CRLF
        }
        return $messageStr;
    }

    /**
     * Return segmentLocation to string
     * Note : msgParse[segmentLocation][segmentName][field][repeat][component][subcomponent]
     *   
     * @param array $segmentLocation
     * @return string
     */
    public function segmentLocationToString($segmentLocation) {
        $segmentName = key($segmentLocation);
        $segmentStr = $segmentName . $this->fieldSeparator;
        $start = 1;
        if( $segmentName === "MSH" ) {
            // MSH-1 is field separator
            $start = 2;
        }
        for( $i=$start;$i<=count($segmentLocation[$segmentName]);$i++) {
            $fieldStr = $this->fieldToString($segmentLocation[$segmentName][$i]);
            $segmentStr .= $fieldStr;
            if( $i < count($segmentLocation[$segmentName]) ) {
                $segmentStr .= $this->fieldSeparator;
            }
        }
        return $segmentStr;
    }

    /**
     * Return field to string
     * 
     * @param array $field
     * @return string
     */
    public function fieldToString($field) {
        $fieldStr = "";
        // for field reps
        for($i=0; $i<count($field); $i++) {
            $fieldStr .= $this->fieldRepeatToString($field[$i]);
            if( $i < (count($field) - 1) ) {
                $fieldStr .= $this->fieldRepeatSeparator;
            }
        }
        return $fieldStr;
    }

    /**
     * Return field repeat to string
     * 
     * @param array $fieldRepeat
     * @return string
     */
    public function fieldRepeatToString($fieldRepeat) {
        $fieldRepeatStr = "";
        if( is_array($fieldRepeat) ) {
            // component
            for($i=1; $i<=count($fieldRepeat); $i++ ) {
                $fieldRepeatStr .= $this->componentToString($fieldRepeat[$i]);
                if( $i < (count($fieldRepeat) ) ) {
                    $fieldRepeatStr .= $this->componentSeparator;
                }
            }
         }
        else {
            $fieldRepeatStr .= $fieldRepeat;
        }
        return $fieldRepeatStr;
    }

    /**
     * Return component to string
     * 
     * @param array $component
     * @return string
     */
    public function componentToString($component) {
        $componentStr = "";
        if( is_array($component) ) {
            // sub component
            for($i=1; $i<=count($component); $i++) {
                $componentStr .= $this->subComponentToString($component[$i]);
                if( $i < count($component) ) {
                    $componentStr .= $this->subComponentSeparator;
                }
            }
        }
        else {
            $componentStr .= $component;
        }
        return $componentStr;
    }

    /**
     * Return subComponent to string
     * 
     * @param array $subComponent
     * @return string
     */
    public function subComponentToString($subComponent) {
        return $subComponent;
    }





    /**
     * 
     * HL7 MESSAGE
     * -----------
     * 
     */

    /**
     * Get segment Name, in hl7 message at segmentLocation 
     * 
     * @param Array msgParse : array[location][segmentName][field][repeat][component][subcomponent]
     * @param Integer segmentLocation
     * 
     * @return String segment name
     */
    private function getMessageSegmentName($segmentLocation) {
        $segmentName = "";
        if( isset($this->msgParse[$segmentLocation]) ) {
            $segmentName = sprintf("%s", key($this->msgParse[$segmentLocation]));
        }
        return $segmentName;
    }

    /**
     * Get the list of segment names, in hl7 message
     * 
     * @return array of segment names
     */
    private function getMessageListOfSegmentNames() {
        $segmentNames = array();
        foreach ($this->msgParse as $segment) {
            $segmentNames[] = key($segment);
        }
        return $segmentNames;
    }

    /**
     * Get segment (name) repetitions in hl7 message
     * 
     * @param string segmentName
     * @param integer segmentLocation 
     * @return integer number of repetitions
     */
    private function getSegmentReps($segmentName, $segmentLocation) {
        $reps = 0;
        if( isset($this->msgParse[$segmentLocation][$segmentName]) ) {
            $reps++;
            if( ! $this->isFirstSegmentInGroup ) {
                for($i=$segmentLocation+1; $i <= count($this->msgParse); $i++ ) {
                    if( isset($this->msgParse[$i][$segmentName]) ) {
                        $reps++;
                    }
                    else {
                        break;
                    }
                }
            }
        }
        return $reps;
    }

    /**
     * Get field repetitions in hl7 message segment
     * 
     * @param string $segmentName
     * @param integer $segmentLocation 
     * @param integer $fieldLocation 
     * @return Interger number of repetitions
     */
    private function getFieldReps($segmentName, $segmentLocation, $fieldLocation) {
        $reps = 0;
        if( isset($this->msgParse[$segmentLocation][$segmentName][$fieldLocation]) ) {
            $fieldReps = count($this->msgParse[$segmentLocation][$segmentName][$fieldLocation]);
            if( $fieldReps > 1 ) {
                $reps = $fieldReps;
            }
            else {
                // unique instance of Field
                if( is_array($this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0]) ) {
                    $hasValue = false;
                    foreach ($this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0] as $component) {
                        foreach ($component as $subcomponent) {
                            if( $subcomponent != "") {
                                $hasValue = true;
                            }
                        }
                    }
                    if( $hasValue ) {
                        $reps++;
                    }
                }
                else {
                    if( $this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0] != "") {
                        $reps++;
                    }
                }
            } 
        }
        return $reps;
    }

    /**
     * Check if Component exists (in msgParse)
     * 
     * @return bool
     */
    private function isComponentExists() {
        $exists = false;
        if( isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]) ) {
            if( is_array($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]) ) {
                // if has sub component
                $hasValue = false;
                foreach ($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation] as $subcomponent) {
                    if( $subcomponent != "") {
                        $hasValue = true;
                    }
                }
                if( $hasValue ) {
                    $exists = true;
                }
            }
            else if( $this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation] != "" ) {
                $exists = true;
            }
        }
        return $exists;
    }

    /**
     * Check if SubComponent exists (in msgParse)
     * 
     * @return bool
     */
    private function isSubComponentExists() {
        $exists = false;
        if( isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation]) ) {
            if( $this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation] != "" ) {
                $exists = true;
            }
        }
        return $exists;
    }





    /**
     * 
     * HL7 PROFILE
     * -----------
     * 
     */

    /**
     * set profile path
     * 
     * @param string $path
     */
    public function setProfilePath($path = "") {
        if ($path != "" && is_dir($path)) {
            $this->profilePath = $path;
        }
    }

    /**
     * set profile 
     * 
     * @param SimpleXML objet $profileXml
     */
    public function setProfile($profileXml = null) {
        if( ! is_null($profileXml) ) {
            $this->profile = $profileXml;
            $this->setProfileAttr();
        }
    }

    /**
     * Set Profile attributes
     * 
     */
    private function setProfileAttr() {
        $this->profileMsgType = (isset($this->profile->HL7v2xStaticDef['MsgType'])) ? sprintf("%s", $this->profile->HL7v2xStaticDef['MsgType']) : "";
        $this->profileEventType = (isset($this->profile->HL7v2xStaticDef['EventType'])) ? sprintf("%s", $this->profile->HL7v2xStaticDef['EventType']) : "";
        $this->profileMsgStructID = (isset($this->profile->HL7v2xStaticDef['MsgStructID'])) ? sprintf("%s", $this->profile->HL7v2xStaticDef['MsgStructID']) : "";
        $this->profileEventDesc = (isset($this->profile->HL7v2xStaticDef['EventDesc'])) ? sprintf("%s", $this->profile->HL7v2xStaticDef['EventDesc']) : "";
    }

    /**
     * Get segment Name, in profile
     * 
     * @param Segment
     * @return array of segment attributes
     */
    private function getProfileSegmentName($Segment) {
        return (isset($Segment['Name'])) ? sprintf("%s", $Segment['Name']) : "";
    }

    /**
     * Get the list of segment names, in profile
     * 
     * @return array of segment names
     */
    private function getProfileListOfSegmentNames() {
        $segmentNames = array();
        foreach ($this->profile->HL7v2xStaticDef->children() as $child) {
            $childName = $child->getName();
            if( $childName == "Segment") {
                $segmentName = $this->getProfileSegmentName($child);
                $segmentNames[] = $segmentName;
            }
            else if( $childName == "SegGroup") {
                $segmentNamesInSegGroup = $this->getProfileListOfSegmentNamesInSegGroup($child);
                $segmentNames = array_merge($segmentNames, $segmentNamesInSegGroup);
            }
        }
        return $segmentNames;
    }

    /**
     * Get the list of segment names, in a SegGroup
     * 
     * @return array of segment names
     */
    private function getProfileListOfSegmentNamesInSegGroup($Group) {
        $segmentNames = array();
        foreach ($Group->children() as $child) {
            $childName = $child->getName();
            if( $childName == "Segment") {
                $segmentName = $this->getProfileSegmentName($child);
                $segmentNames[] = $segmentName;
            }
            else if( $childName == "SegGroup") {
                $segmentNamesInSegGroup = $this->getProfileListOfSegmentNamesInSegGroup($child);
                $segmentNames = array_merge($segmentNames, $segmentNamesInSegGroup);
            }
        }
        return $segmentNames;
    }

    /**
     * Get the name of firsts segments in group and sub-goup
     * 
     * @param object $Group
     * @return array of name
     */
    private function getProfileListOfFirstNamesOfSegmentsInGroup($Group) {
        $segmentsNames = array();
        for( $i=0; $i < $Group->children()->count(); $i++ ) {
            $childType = $Group->children()->$i->getName();
            if( $i == 0 && $childType == "Segment" ) {
                $segmentsNames[] = sprintf("%s", $Group->children()->$i["Name"]);

            }
            else if( $childType == "SegGroup" ) {
                $segmentsNames = array_merge($segmentsNames, $this->getProfileListOfFirstNamesOfSegmentsInGroup($Group->children()->$i));
            }
        }
        return $segmentsNames;
    }

    /**
     * Get Group attributes
     * 
     * @param object $Group
     */
    private function getProfileGroupAttr($Group) {
        $this->groupName = (isset($Group['Name'])) ? sprintf("%s", $Group['Name']) : "";
        $this->groupLongName = (isset($Group['LongName'])) ? sprintf("%s", $Group['LongName']) : "";
        $this->groupUsage = (isset($Group['Usage'])) ? sprintf("%s", $Group['Usage']) : "";
        $this->groupMin = (isset($Group['Min'])) ? intval(sprintf("%s", $Group['Min'])) : 0;
        $this->groupMaxStr = (isset($Group['Max'])) ? sprintf("%s", $Group['Max']) : 0;
        $this->groupMax = ($this->groupMaxStr === "*") ? INF : intval($this->groupMaxStr);
    }

    /**
     * Get Segment attributes
     * 
     * @param object $Segment
     */
    private function getProfileSegmentAttr($Segment) {
        $this->segmentName = (isset($Segment['Name'])) ? sprintf("%s", $Segment['Name']) : "";
        $this->segmentLongName = (isset($Segment['LongName'])) ? sprintf("%s", $Segment['LongName']) : "";
        $this->segmentUsage = (isset($Segment['Usage'])) ? sprintf("%s", $Segment['Usage']) : "";
        $this->segmentMin = (isset($Segment['Min'])) ? intval(sprintf("%s", $Segment['Min'])) : 0;
        $this->segmentMaxStr = (isset($Segment['Max'])) ? sprintf("%s", $Segment['Max']) : 0;
        $this->segmentMax = ($this->segmentMaxStr === "*") ? INF : intval($this->segmentMaxStr);
    }

    /**
     * Get Field attributes
     * 
     * @param object $Field
     */
    private function getProfileFieldAttr($Field) {
        $this->fieldName = (isset($Field['Name'])) ? sprintf("%s", $Field['Name']) : "";
        $this->fieldUsage = (isset($Field['Usage'])) ? sprintf("%s", $Field['Usage']) : "";
        $this->fieldMin = (isset($Field['Min'])) ? intval(sprintf("%s", $Field['Min'])) : 0;
        $this->fieldMaxStr = (isset($Field['Max'])) ? sprintf("%s", $Field['Max']) : 0;
        $this->fieldMax = ($this->fieldMaxStr === "*") ? INF : intval($this->fieldMaxStr);
        $this->fieldDatatype = (isset($Field['Datatype'])) ? sprintf("%s", $Field['Datatype']) : "";
        $this->fieldLength = (isset($Field['Length'])) ? sprintf("%s", $Field['Length']) : "";
        $this->fieldItemNo = (isset($Field['ItemNo'])) ? sprintf("%s", $Field['ItemNo']) : "";
        $this->fieldTable = (isset($Field['Table'])) ? sprintf("%s", $Field['Table']) : "";
        $this->fieldReference = (isset($Field->Reference)) ? sprintf("%s", $Field->Reference) : "";
        $this->fieldImpNote = (isset($Field->ImpNote)) ? trim(preg_replace("/[\n\r]/", " ", sprintf("%s", $Field->ImpNote))) : "";
    }

    /**
     * Get Component attributes
     * 
     * @param object $Component
     */
    function getProfileComponentAttr($Component) {
        $this->componentName = (isset($Component['Name'])) ? sprintf("%s", $Component['Name']) : "";
        $this->componentUsage = (isset($Component['Usage'])) ? sprintf("%s", $Component['Usage']) : "";
        $this->componentDatatype = (isset($Component['Datatype'])) ? sprintf("%s", $Component['Datatype']) : "";
        $this->componentLength = (isset($Component['Length'])) ? sprintf("%s", $Component['Length']) : "";
        $this->componentTable = (isset($Component['Table'])) ? sprintf("%s", $Component['Table']) : "";
        $this->componentImpNote = (isset($Component->ImpNote)) ? trim(preg_replace("/[\n\r]/", " ", sprintf("%s", $Component->ImpNote))) : "";
    }

    /**
     * Get SubComponent attributes
     * 
     * @param object $SubComponent
     */
    function getProfileSubComponentAttr($SubComponent) {
        $this->subcomponentName = (isset($SubComponent['Name'])) ? sprintf("%s", $SubComponent['Name']) : "";
        $this->subcomponentUsage = (isset($SubComponent['Usage'])) ? sprintf("%s", $SubComponent['Usage']) : "";
        $this->subcomponentDatatype = (isset($SubComponent['Datatype'])) ? sprintf("%s", $SubComponent['Datatype']) : "";
        $this->subcomponentLength = (isset($SubComponent['Length'])) ? sprintf("%s", $SubComponent['Length']) : "";
        $this->subcomponentConstantValue = (isset($SubComponent['ConstantValue'])) ? sprintf("%s", $SubComponent['ConstantValue']) : "";
        $this->subcomponentTable = (isset($SubComponent['Table'])) ? sprintf("%s", $SubComponent['Table']) : "";
        $this->subcomponentImpNote = (isset($SubComponent->ImpNote)) ? trim(preg_replace("/[\n\r]/", " ", sprintf("%s", $SubComponent->ImpNote))) : "";
    }





     /**
     * 
     * HL7 TABLE
     * ---------
     * 
     */

    /**
     * Set HL7 Table path
     * 
     * @param string $path
     */
    public function setHL7TablePath($path = "") {
        if ($path != "" && is_dir($path)) {
            $this->tablePath = $path;
            echo "setHL7TablePath: OK<br>";
        }
    }

    /**
     * Set HL7 Table filename
     * 
     * @param string $filename
     */
    public function setHL7TableFilename($filename = "") {
        if ($filename != "" && is_file($this->tablePath."/".$filename)) {
            $this->tableFileName = $filename;
            echo "setHL7TableFilename: OK<br>";
        }
    }

    /**
     * Load hl7 table
     * 
     * @param 
     */
    public function loadJsonHL7Table() {
        if (is_file($this->tablePath."/".$this->tableFileName)) {
            $jsonStr = file_get_contents($this->tablePath."/".$this->tableFileName);
            $data = json_decode($jsonStr, true);
            echo "HL7 Table loaded<br>";
        }
    }

    /**
     * Load hl7 table
     * 
     * @param SimpleXML objet $tableXml
     */
    public function loadHL7Table($tableXml = null) {
        if( ! is_null($tableXml) ) {
            $this->table = $tableXml;
            $this->setHL7TableData();
        }
    }

    public function loadHL7Fulltable($tableXml = null) {
        if( ! is_null($tableXml) ) {
            $this->table = $tableXml;
            $this->setHL7FullTableData();
        }
    }

    /**
     * Convert table to array
     * 
     */
    private function setHL7TableData() {
        foreach ($this->table->hl7tables->hl7table as $hl7table) {
            $tableId = sprintf("%s", $hl7table['id']);
            if( count( $hl7table->children())> 0 )  {
                $elements = array();
                foreach ($hl7table->tableElement as $tableElement) {
                    $elements[] = sprintf("%s", $tableElement['code']);
                }
                $this->hl7tables["$tableId"] = $elements;
            }
        }
    }

    /**
     * Convert full table to array
     * 
     */
    private function setHL7FullTableData() {
        foreach ($this->table->hl7tables->hl7table as $hl7table) {
            $this->getHl7TableAttr($hl7table);
            
            if( count( $hl7table->children())> 0 )  {
                $elements = array();
                foreach ($hl7table->tableElement as $tableElement) {
                    $this->getHL7TableElmtAttr($tableElement);
                    
                    $elements[] = array(
                        'order' => $this->tableElementOrder,
                        'code' => $this->tableElementCode,
                        'description' => $this->tableElementDescription,
                        'displayName' => $this->tableElementDisplayName,
                        'source' => $this->tableElementSource,
                        'usage' => $this->tableElementUsage,
                        'creator' => $this->tableElementCreator,
                        'date' => $this->tableElementDate,
                        'instruction' => $this->tableElementInstruction
                    );
                }
                $this->hl7FullTables["$this->tableId"] = array(
                    'id' => $this->tableId,
                    'name' => $this->tableName,
                    'codeSys' => $this->tableCodeSys, 
                    'type' => $this->tableType,
                    'tableElement' => $elements
                );
            }
        }
    }

    /**
     * get hl7table attributes
     * 
     * @param object $hl7table
     */
    private function getHL7TableAttr($hl7table) {
        $this->tableId = (isset($hl7table['id'])) ? sprintf("%s", $hl7table['id']) : "";
        $this->tableName = (isset($hl7table['name'])) ? sprintf("%s", $hl7table['name']) : "";
        $this->tableCodeSys = (isset($hl7table['codeSys'])) ? sprintf("%s", $hl7table['codeSys']) : "";
        $this->tableType = (isset($hl7table['type'])) ? sprintf("%s", $hl7table['type']) : "";
    }

    /**
     * get tableElement attributes
     * 
     * @param object $tableElement
     */
    private function getHL7TableElmtAttr($tableElement) {
        $this->tableElementOrder = (isset($tableElement['order'])) ? sprintf("%s", $tableElement['order']) : "";
        $this->tableElementCode = (isset($tableElement['code'])) ? sprintf("%s", $tableElement['code']) : "";
        $this->tableElementDescription = (isset($tableElement['description'])) ? sprintf("%s", $tableElement['description']) : "";
        $this->tableElementDisplayName = (isset($tableElement['displayName'])) ? sprintf("%s", $tableElement['displayName']) : "";
        $this->tableElementSource = (isset($tableElement['source'])) ? sprintf("%s", $tableElement['source']) : "";
        $this->tableElementUsage = (isset($tableElement['usage'])) ? sprintf("%s", $tableElement['usage']) : "";
        $this->tableElementCreator = (isset($tableElement['creator'])) ? sprintf("%s", $tableElement['creator']) : "";
        $this->tableElementDate = (isset($tableElement['date'])) ? sprintf("%s", $tableElement['date']) : "";
        $this->tableElementInstruction = (isset($tableElement['instruction'])) ? sprintf("%s", $tableElement['instruction']) : "";
    }





    /**
     * 
     * MESSAGE VALIDATION
     * ------------------
     * 
     */

    /**
     * Validate hl7 message against profile
     * 
     * @param bool $debug
     * @return bool
     */
    public function validateMessage($debug = false) {
        $this->debug = $debug;
        
        $this->validationReport = array();
        $this->testReport = array();
        $this->logs = array();
        $this->testReportErrorCnt = 0;
        
        // Check profile path
        if ($this->profilePath == "") {
            $this->validationReport["Error"] = "Profile path not found.";
            $this->addLogs("Profile path not found.");
            return false;
        }
        
        // Load profile
        $profileFileName = $this->messageType . "-" . $this->messageTriggerEvent . "-" . $this->messageStructID . ".xml";
        if (is_file($this->profilePath."/".$profileFileName)) {
            $this->setProfile( simplexml_load_file($this->profilePath."/".$profileFileName));
        }

        if( ! isset($this->profile) ) {
            $this->validationReport["Error"] = "Missing profile.";
            $this->addLogs("Missing profile.");
            return false;
        }

        $this->validationReport["EventDesc"] = $this->profileEventDesc;
        $this->validationReport["MsgType"] = array(
            "MsgCode" => $this->profileMsgType,
            "EventType" => $this->profileEventType,
            "MsgStructID" => $this->profileMsgStructID
        );
        
        // Check message Type : message code,  trigger event, message structure
        $this->addLogs("--- Message Type ---");
        $result = ( $this->messageType === $this->profileMsgType ) ? true : false;
        $location = "MSH-9.1";
        $description = "Message code" . (($result)? " is '" : " expected value '") . $this->profileMsgType . "'.";
        $this->addTestReport($location,$description , "Value", $result);
        $this->addLogs($description);
        
        $result = ( $this->messageTriggerEvent === $this->profileEventType ) ? true : false;
        $location = "MSH-9.2";
        $description = "Trigger event" . (($result)? " is '" : " expected value '") . $this->profileEventType . "'.";
        $this->addTestReport($location,$description , "Value", $result);
        $this->addLogs($description);

        $result = ( $this->messageStructID === $this->profileMsgStructID ) ? true : false;
        $location = "MSH-9.3";
        $description = "Message structure" . (($result)? " is '" : " expected value '") . $this->profileMsgStructID . "'.";
        $this->addTestReport($location,$description , "Value", $result);
        $this->addLogs($description);

        // Get profile list of segment names & message list of segment names
        $this->profileListOfSegmentNames = $this->getProfileListOfSegmentNames();
        $this->messageListOfSegmentNames = $this->getMessageListOfSegmentNames();
        $this->validationReport["profileListOfSegmentNames"] = $this->profileListOfSegmentNames;
        $this->validationReport["messageListOfSegmentNames"] = $this->messageListOfSegmentNames;

        $this->addLogs("--- Segment names ---");
        $this->addLogs("Segment names in profile: " . implode(", ", $this->profileListOfSegmentNames) );
        $this->addLogs("Segment names in message: " . implode(", ", $this->messageListOfSegmentNames) );
        
        // Get non standard segments in message
        $this->nonStandardSegment = array_diff($this->messageListOfSegmentNames, $this->profileListOfSegmentNames);
        $this->validationReport["nonStandardSegment"] = array();
        if( count($this->nonStandardSegment) > 0) {
            $this->addLogs("--- Non standard segments ---");
            foreach( $this->nonStandardSegment as $key => $val ) {
                $description = "Segment '$val' appears in the message but is not defined in the message profile.";
                $this->validationReport["nonStandardSegment"][] = $description;
            }
        }

        // reset location
        $this->segmentLocation = 1;         // message segment location (SEQ) start at 1
        $this->profilLocation = 0;          // profile xml location
        $this->profilSegmentLocation = 0;   // profile segment location

        $this->validationReport["validation"] = array();
        $this->addLogs("--- Validation begin ---");
        for($this->profilLocation = 0; $this->profilLocation < $this->profile->HL7v2xStaticDef->children()->count(); $this->profilLocation ++) {
            $location = $this->profilLocation;
            $childName = $this->profile->HL7v2xStaticDef->children()->$location->getName();
            if( $childName == "Segment") {
                // Check if non standard segment
                $segmentName = $this->getMessageSegmentName($this->segmentLocation);
                if( in_array($segmentName, $this->nonStandardSegment) ) {
                    $description = "Segment '$segmentName' is not defined in the message profile (non standard segment).";
                    $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                    $this->addSegmentValidationReport($segmentName, "Non standard segment", "", "", true, true, "", $description);
                    $this->addLogs("-Segment- $segmentName: non standard segment");
                    $this->segmentLocation++;   // go next segment in message
                    $this->profilLocation--;    // go prev segment in profile
                    continue;
                }
                // Validate Segment
                $Segment = $this->profile->HL7v2xStaticDef->children()->$location;
                $this->validateSegment($Segment);
            }
            else if( $childName == "SegGroup") {
                // Validate Group
                $Group = $this->profile->HL7v2xStaticDef->children()->$location;
                $this->isGroupInSegGroup = false;
                $this->parentGroupExists = true;
                $this->validateGroup($Group);
            }
        }

        // If not at the end of the message
        if( $this->segmentLocation < count($this->msgParse) ) {
            for($i=$this->segmentLocation; $i <= count($this->msgParse); $i++) {
                $segmentName = $this->getMessageSegmentName($i);
                if( in_array($segmentName, $this->nonStandardSegment) ) {
                    // non standard segment
                    $description = "Segment '$segmentName' is not defined in the message profile (non standard segment).";
                    $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                    $this->addSegmentValidationReport($segmentName, "Non standard segment", "", "", true, true, "", $description);
                    $this->addLogs("-Segment- $segmentName: non standard segment");
                }
                else {
                    // not expected segment
                    $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
                    $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                    $this->addSegmentValidationReport($segmentName, "", "", "", true, true, "", $description);
                    $this->addLogs("-Segment- $segmentName: segment is not expected here");
                }
            }
        }
        $this->addLogs("--- Validation end ---");
        return true;
    }

    /**
     * Validate Group
     * 
     * @param object $Group
     */
    private function validateGroup($Group) {
        // get profile Group Attr 
        $this->getProfileGroupAttr($Group);
        $groupName = $this->groupName;
        $groupUsage = $this->groupUsage;
        $groupCard = "[$this->groupMin..$this->groupMaxStr]";
        $isGroupInSegGroup = $this->isGroupInSegGroup; // is Group in SegGroup
        $parentGroupExists = $this->parentGroupExists; // parent Group exists

        // Get first Names of the segments in this group (Note : group can start with a Segment or with a Group)
        $firstSegmentsInGroup = $this->getProfileListOfFirstNamesOfSegmentsInGroup($Group);
        $segmentLocation = $this->segmentLocation;
        $segmentName = ( isset($this->msgParse[$segmentLocation]) ) ? key($this->msgParse[$segmentLocation]) : "";
        $segmentsInGroup = $this->getProfileListOfSegmentNamesInSegGroup($Group);
        // group exists
        $groupExists = in_array($segmentName, $firstSegmentsInGroup);
        // group reps
        $groupReps = 0;
        if( $groupExists ) {
            for( $i=$segmentLocation; $i <= count($this->msgParse); $i++) {
                if( key($this->msgParse[$i]) == $firstSegmentsInGroup[0] ) {
                    $groupReps++;
                }
                if( in_array( key($this->msgParse[$i]), $this->nonStandardSegment) ) {
                    // non standard segment
                    // do notthing
                }
                else if( ! in_array( key($this->msgParse[$i]), $segmentsInGroup) ) {
                    // not in the group
                    break;
                }
            }
            if($groupReps == 0) $groupReps=1; // Note: if first segment is missing (RE, O, C, X)
        }
        $this->addLogs("--- $groupName BEGIN - " . (($groupReps > 1) ? " ($groupReps reps.)" : ""));


        $groupError = false;
        $groupComments = "";
        
        if( $parentGroupExists ) {
            // check usage
            list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($this->groupUsage, $groupExists, "Group", $this->groupName, $this->profileMsgStructID);
            if( ! $checkUsageResult ) {
                $groupError = true;
                $groupComments .= $checkUsageDesc . " ";
            }
            
            // check card.
            list($checkCardinalityResult, $checkCardinalityType, $checkCardinalityDesc) = $this->checkCardinality($this->groupMin, $this->groupMax, $this->groupMaxStr, $groupReps, $groupExists, $this->groupUsage, "Group", $this->groupName, $this->profileMsgStructID);
            if( ! $checkCardinalityResult ) {
                $groupError = true;
            }
            $groupComments .= $checkCardinalityDesc . " ";
        }
        
        $currentprofilSegmentLocation = $this->profilSegmentLocation;
        if( $groupExists ) {
            // for each group repetition
            for( $i = 0; $i < $groupReps; $i++) {
                // For each Element (segment or segment group) in the Group
                $this->addGroupValidationReport("---", "--- $groupName begin" . (($groupReps > 1) ? " (Rep. ".($i+1)."/$groupReps)" : ""), $groupUsage, $groupCard, $groupExists, $groupError, $groupReps, $groupComments);
                
                $this->profilSegmentLocation = $currentprofilSegmentLocation;
                $this->segmentsInGroup = $segmentsInGroup;
                $this->isFirstSegmentInGroup = true;
                for( $j = 0 ; $j < $Group->children()->count(); $j++ ) {
                    $childName = $Group->children()->$j->getName();
                    $this->isSegmentInGroup = false;
                    if( $childName == "Segment") {
                        // Check if non standard segment
                        $theSegmentName = $this->getMessageSegmentName($this->segmentLocation);
                        if( in_array($theSegmentName, $this->nonStandardSegment) ) {
                            $description = "Segment '$theSegmentName' is not defined in the message profile (non standard segment).";
                            $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                            $this->addSegmentValidationReport($theSegmentName, "Non standard segment", "", "", true, true, "", $description);
                            $this->addLogs("-Segment- $theSegmentName: non standard segment");
                            $this->segmentLocation++;
                            $j--;
                            continue;
                        }
                        // Check segment
                        $this->isSegmentInGroup = true;
                        $Segment = $Group->children()->$j;
                        $this->validateSegment($Segment);
                        $this->isFirstSegmentInGroup = false;
                    }
                    else if( $childName == "SegGroup") {
                        $thegroup = $Group->children()->$j;
                        $this->isGroupInSegGroup = true;
                        $this->parentGroupExists = true;
                        $this->validateGroup($thegroup);
                    }
                }

                $this->addGroupValidationReport("---", "--- $groupName end", "", "", $groupExists, $groupError, "", "");
            }
        }
        else {
            // group doesn't exist
            $this->addGroupValidationReport("---", "--- $groupName begin" . (($groupReps > 1) ? " (Rep. ".($i+1)."/$groupReps)" : ""), $groupUsage, $groupCard, $groupExists, $groupError, $groupReps, $groupComments);
                    
            for( $j = 0 ; $j < $Group->children()->count(); $j++ ) {
                
                
                $childName = $Group->children()->$j->getName();
                $this->isSegmentInGroup = false;
                if( $childName == "Segment") {
                    $this->isSegmentInGroup = true;
                    $Segment = $Group->children()->$j;
                    $this->getProfileSegmentAttr($Segment);
                    // get the name of the segment in message
                    $theSegmentName = $this->getMessageSegmentName($this->segmentLocation);
                    if( $theSegmentName == $this->segmentName ) {
                        // Segment is not expected here
                        $description = "Segment '$theSegmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
                        $this->addLogs("-Segment- $theSegmentName: not expected");
                        $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                        $this->addSegmentValidationReport($theSegmentName, "", "", "", true, true, "", $description);
                        $this->segmentLocation++;
                    }
                    else {
                        $this->addSegmentValidationReport($this->segmentName, $this->segmentLongName, $this->segmentUsage, "[$this->segmentMin..$this->segmentMaxStr]", false, false, "", "");
                    }
                    $this->profilSegmentLocation++;
                }
                else if( $childName == "SegGroup") {
                    $thegroup = $Group->children()->$j;
                    $this->isGroupInSegGroup = true;
                    $this->parentGroupExists = false;
                    $this->validateGroup($thegroup);   
                }
            }
            $this->addGroupValidationReport("---", "--- $groupName end", "", "", $groupExists, $groupError, "", "");
        }


        // validation report (test in progress)
        $this->addLogs("--- $groupName END");
        $this->isSegmentInGroup = false;
    }

    /**
     * Validate Segment
     * 
     * @param object $Segment
     */
    private function validateSegment($Segment) {
        // get profile segment Attr
        $this->getProfileSegmentAttr($Segment);

        // get message segment Attr
        $segmentName = $this->getMessageSegmentName($this->segmentLocation);
        $segmentExists = ( isset($this->msgParse[$this->segmentLocation][$this->segmentName]) ) ? true : false;
        $segmentReps = ($segmentExists) ? $this->getSegmentReps($segmentName, $this->segmentLocation) : 0;
        $this->addLogs("-Segment- $this->segmentName: profilLocation: $this->profilLocation - profilSegmentLocation: $this->profilSegmentLocation");
        $this->addLogs("-Segment- segmentLocation: $this->segmentLocation - segmentName: $segmentName - segmentExists: " . ($segmentExists ? 'true' : 'false') . " - segmentReps: $segmentReps");

        $segmentError = false;
        $segmentComments = "";

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($this->segmentUsage, $segmentExists, "Segment", $this->segmentName, $this->profileMsgStructID);
        if( !$checkUsageResult ) {
            $segmentError = true;
            $segmentComments .= $checkUsageDesc . " ";
        }

        // check card.
        list($checkCardinalityResult, $checkCardinalityType, $checkCardinalityDesc) = $this->checkCardinality($this->segmentMin, $this->segmentMax, $this->segmentMaxStr, $segmentReps, $segmentExists, $this->segmentUsage, "Segment", $this->segmentName, $this->profileMsgStructID);
        if( !$checkCardinalityResult ) {
            $segmentError = true;
        }
        $segmentComments .= $checkCardinalityDesc . " ";

        // validation report 
        $this->addSegmentValidationReport($this->segmentName, $this->segmentLongName . (($segmentReps > 1) ? " ($segmentReps reps.)" : ""), $this->segmentUsage, "[$this->segmentMin..$this->segmentMaxStr]", $segmentExists, $segmentError, $segmentReps, trim($segmentComments) );

        // check content
        if( $segmentExists ) {
            // For Segment Reps
            for( $i = 0; $i < $segmentReps; $i++) {
                $this->fieldLocation=0;
                // for each field
                foreach ($Segment->Field as $Field) {
                    $this->fieldLocation++;
                    $this->validateField($Field);
                }
                // Check if there are more Fields in message Segment
                $fieldsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName]);
                if( $fieldsCnt > count($Segment->Field) ) {
                    for($i=$this->fieldLocation+1; $i <= $fieldsCnt; $i++ ) {
                        $description = "Field '$this->segmentName-$i' is not expected in the '$this->segmentName' structure.";
                        $type = "Element not expected";
                        $result = false;
                        $fieldValue =  $this->fieldToString($this->msgParse[$this->segmentLocation][$this->segmentName][$i]);
                        $this->addTestReport("$this->segmentName-$i", $description, $type, $result);
                        $this->addLogs("-Field- $this->segmentName-$i : $description");
                        $this->addFieldValidationReport("$this->segmentName-$i", "", "", "", "", "", "", "", "", "", $fieldValue, true, true, "", $description);
                    }
                }
                $this->segmentLocation++;
            }
            $this->profilSegmentLocation++;
        }
        else if( in_array($segmentName, $this->nonStandardSegment) ) {
            // non standard segment, skip it
            // should never happen (have already been checked)
            $description = "Segment '$segmentName' is not defined in the message profile (non standard segment).";
            $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
            $this->addSegmentValidationReport($segmentName, "Non standard segment", "", "", true, true, "", $description);
            $this->addLogs("-Segment- $segmentName: non standard segment");
            $this->segmentLocation++;
            $this->profilSegmentLocation++;
        }
        else {
            if($segmentName != "") {
                if( $this->checkSegmentNameInProfileStructure($segmentName) ) {
                    // segment appears later in the profile
                    $this->addLogs("-Segment- appears later in the profile");
                    $this->profilSegmentLocation++;
                }
                else if($this->isSegmentInGroup && in_array($segmentName, $this->segmentsInGroup) ) {
                    // segment is in a group and appears later in the group.
                    $this->addLogs("-Segment- segment is in a group and appears later in the group.");
                    $this->profilSegmentLocation++;
                }
                else if($this->isSegmentInGroup) {
                    // segment is in a group and appears later in parent group.
                    $this->addLogs("-Segment- segment is in a group and appears later in parent group.");
                    $this->profilSegmentLocation++;
                }
                else {
                    // segment is not expected here
                    $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
                    $this->addTestReport($this->profileMsgStructID, $description, "Structure", false);
                    $this->addSegmentValidationReport($segmentName, "", "", "", true, true, "", $description);
                    $this->addLogs("-Segment- segment is not expected here");
                    $this->profilLocation--;    // go prev.
                    $this->segmentLocation++;   // skip segment
                }
            }
        }
    }

    /**
     * Validate Field
     * 
     * @param object $Field
     */
    private function validateField($Field) {
        // current Location
        $currentLocation = "$this->segmentName-$this->fieldLocation";
        
        // get profile Field Attr
        $this->getProfileFieldAttr($Field);
        $fieldReps = $this->getFieldReps($this->segmentName, $this->segmentLocation, $this->fieldLocation);
        $fieldExists = ($fieldReps > 0) ? true : false;
        $this->addLogs("-Field- $currentLocation : fieldExists: " . ($fieldExists ? 'true' : 'false') . " - fieldReps: $fieldReps.");

        $fieldError = false;
        $fieldComments = "";
        
        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($this->fieldUsage, $fieldExists, "Field", $this->fieldName, $currentLocation);
        if( ! $checkUsageResult ) {
            $fieldError = true;
            $fieldComments .= $checkUsageDesc . " ";
        }
        
        // check card.
        list($checkCardinalityResult, $checkCardinalityType, $checkCardinalityDesc) = $this->checkCardinality($this->fieldMin, $this->fieldMax, $this->fieldMaxStr, $fieldReps, $fieldExists, $this->fieldUsage, "Field", $this->fieldName, $currentLocation);
        if( ! $checkCardinalityResult ) {
            $fieldError = true;
        }
        $fieldComments .= $checkCardinalityDesc . " ";
        
        if( $fieldReps == 0 ) {
            $this->addFieldValidationReport($currentLocation, $this->fieldName, $this->fieldUsage, "[$this->fieldMin..$this->fieldMaxStr]", $this->fieldDatatype, $this->fieldLength, $this->fieldItemNo, $this->fieldTable, $this->fieldReference, $this->fieldImpNote, "", $fieldExists, $fieldError, $fieldReps, trim($fieldComments));
        }
        
        for($repeat = 0; $repeat < $fieldReps ; $repeat ++) {
            $fieldRepeatComments = $fieldComments;

            // check length
            $fieldValue = $this->fieldRepeatToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat]);
            list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($this->fieldLength, $fieldValue, "Field", $this->fieldName, $currentLocation);
            if( ! $checkLengthResult ) {
                $fieldError = true;
                $fieldRepeatComments .= $checkLengthDesc . " ";
            }

            // check table
            if( $this->fieldTable !== "" && isset($this->hl7tables[$this->fieldTable]) ) {
                list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($this->fieldTable, $fieldValue, "Field", $this->fieldName, $currentLocation);
                if( ! $checkHL7tableResult ) {
                    $fieldError = true;
                }
                $fieldRepeatComments .= $checkHL7tableDesc . " ";
            }

            // to do: check datatype (ST, TS...)

            // Validation Report
            $this->addFieldValidationReport($currentLocation, $this->fieldName . ( ($fieldReps > 1) ? " (Rep. " . ($repeat+1) . ")" : ""), $this->fieldUsage, "[$this->fieldMin..$this->fieldMaxStr]", $this->fieldDatatype, $this->fieldLength, $this->fieldItemNo, $this->fieldTable, $this->fieldReference, $this->fieldImpNote, $fieldValue, $fieldExists, $fieldError, $fieldReps, trim($fieldRepeatComments));
            
            $this->fieldrepeat = $repeat;
            if( is_array($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat]) ) {
                // Field has components
                $componentsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat]);
                if( isset($Field->Component)) {
                    $this->componentLocation=0;
                    foreach ($Field->Component as $Component) {
                        $this->componentLocation++;
                        $this->validateComponent($Component);
                    }

                    // Check if there are more Components in message
                    if( $componentsCnt > count($Field->Component) ) {
                        for($i=$this->componentLocation+1; $i <= $componentsCnt; $i++) {
                            $description = "Component '$currentLocation.$i' is not expected in the '$currentLocation' structure.";
                            $type = "Element not expected";
                            $result = false;
                            $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                            $this->addLogs("-Component- $currentLocation.$i : $description");

                            // Validation Report
                            $componentValue = $this->componentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i]);
                            $this->addComponentValidationReport("$currentLocation.$i", "", "", "", "", "", "", $componentValue, true, true, $description);
                        }
                    }
                }
                else {
                    // Check if there are more than one Component in message
                    if( $componentsCnt > 1 ) {
                        // No components in profile
                        $description = "Components are not expected in the '$currentLocation' structure.";
                        $type = "Element not expected";
                        $result = false;
                        $this->addTestReport($currentLocation, $description, $type, false);
                        $this->addLogs("-Component- $currentLocation : $description");
                        
                        // Validation Report
                        for($i=1; $i <= $componentsCnt; $i++) {
                            $description = "Component '$currentLocation.$i' is not expected in the '$currentLocation' structure.";
                            $componentValue = $this->componentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i]);
                            $this->addComponentValidationReport("$currentLocation.$i", "", "", "", "", "", "", $componentValue, true, true, $description);
                        }
                    }
                }
            }
        }        
    }

    /**
     * Validate Component
     * 
     * @param object $Component
     */
    private function validateComponent($Component) {
        $currentLocation = "$this->segmentName-$this->fieldLocation.$this->componentLocation";
        
        // get profile Component Attr
        $this->getProfileComponentAttr($Component);
        $componentExists = $this->isComponentExists();
        $this->addLogs("-Component- $currentLocation : componentExists: " . ($componentExists ? 'true' : 'false') . " ($this->componentUsage)");
        
        $componentError = false;
        $componentComments = "";

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($this->componentUsage, $componentExists, "Component", $this->componentName, $currentLocation);
        if( ! $checkUsageResult ) {
            $componentError = true;
            $componentComments .= $checkUsageDesc . " ";
        }
        
        $componentValue = "";
        if( $componentExists ) {
            // check length
            $componentValue = $this->componentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]);

            list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($this->componentLength, $componentValue, "Component", $this->componentName, $currentLocation);
            if( ! $checkLengthResult ) {
                $componentError = true;
                $componentComments .= $checkLengthDesc . " ";
            }

            // check table
            if( $this->componentTable !== "" && isset($this->hl7tables[$this->componentTable]) ) {
                list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($this->componentTable, $componentValue, "Component", $this->componentName, $currentLocation);
                if( ! $checkHL7tableResult ) {
                    $componentError = true;
                }
                $componentComments .= $checkHL7tableDesc . " ";
            }

            // to do: check datatype
        }

        // Validation Report
        $this->addComponentValidationReport( $currentLocation, $this->componentName, $this->componentUsage, $this->componentDatatype, $this->componentLength, $this->componentTable, $this->componentImpNote, $componentValue, $componentExists, $componentError, trim($componentComments));

        if( $componentExists ) {
            if( is_array($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]) ) {
                // Component has SubComponent
                $subcomponentsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]);
                if( isset($Component->SubComponent)) {
                    $this->subcomponentLocation=0;
                    foreach ($Component->SubComponent as $SubComponent) {
                        $this->subcomponentLocation++;
                        $this->validateSubComponent($SubComponent);
                    }

                    // Check if there are more SubComponent in message
                    if( $subcomponentsCnt > count($Component->SubComponent) ) {
                        for($i=$this->subcomponentLocation+1; $i <= $subcomponentsCnt; $i++) {
                            $description = "SubComponent '$currentLocation.$i' is not expected in the '$currentLocation' structure.";
                            $type = "Element not expected";
                            $result = false;
                            $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                            $this->addLogs("-SubComponent- $currentLocation.$i : $description");
                            // Validation Report
                            $subcomponentValue = $this->subComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i]);
                            $this->addSubComponentValidationReport("$currentLocation.$i", "", "", "", "", "", "", "", $subcomponentValue, true, true, $description);
                        }
                    }
                }
                else {
                    // Check if there are more than one SubComponent in message
                    if( $subcomponentsCnt > 1 ) {
                        // No SubComponent in profile
                        $description = "SubComponent are not expected in the '$currentLocation' structure.";
                        $type = "Element not expected";
                        $result = false;
                        $this->addTestReport($currentLocation, $description, $type, false);
                        $this->addLogs("-SubComponent- $currentLocation : $description");
                        // Validation Report
                        for($i=1; $i <= $subcomponentsCnt; $i++) {
                            $description = "SubComponent '$currentLocation.$i' is not expected in the '$currentLocation' structure.";
                            $subcomponentValue = $this->subComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i]);
                            $this->addSubComponentValidationReport("$currentLocation.$i", "", "", "", "", "", "", "", $subcomponentValue, true, true, $description);
                        }
                    }
                }
            }
        }    
    }

    /**
     * Validate SubComponent
     * 
     * @param object $SubComponent
     */
    private function validateSubComponent($SubComponent) {
        $currentLocation = "$this->segmentName-$this->fieldLocation.$this->componentLocation.$this->subcomponentLocation";
    
        // get profile SubComponent Attr
        $this->getProfileSubComponentAttr($SubComponent);
        $subcomponentExists = $this->isSubComponentExists();
        $this->addLogs("-SubComponent- $currentLocation : subcomponentExists: " . ($subcomponentExists ? 'true' : 'false') . " ($this->subcomponentUsage)");
        
        $subcomponentError = false;
        $subcomponentComments = "";

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($this->subcomponentUsage, $subcomponentExists, "SubComponent", $this->subcomponentName, $currentLocation);
        if( ! $checkUsageResult ) {
            $subcomponentError = true;
            $subcomponentComments .= $checkUsageDesc . " ";
        }
        
        $subcomponentValue = "";
        if( $subcomponentExists) {
            // check length
            $subcomponentValue = $this->subComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation]);
            list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($this->subcomponentLength, $subcomponentValue, "SubComponent", $this->subcomponentName, $currentLocation);
            if( ! $checkLengthResult ) {
                $subcomponentError = true;
                $subcomponentComments .= $checkLengthDesc . " ";
            }

            // check table
            if( $this->subcomponentTable !== "" && isset($this->hl7tables[$this->subcomponentTable]) ) {
                list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($this->subcomponentTable, $subcomponentValue, "SubComponent", $this->subcomponentName, $currentLocation);
                if( ! $checkHL7tableResult ) {
                    $subcomponentError = true;
                }
                $subcomponentComments .= $checkHL7tableDesc . " ";
            }

            // to do: check datatype

            // to do: check constantValue
        }

        // Validation Report
        $this->addSubComponentValidationReport($currentLocation, $this->subcomponentName, $this->subcomponentUsage, $this->subcomponentDatatype, $this->subcomponentLength, $this->subcomponentConstantValue, $this->subcomponentTable, $this->subcomponentImpNote, $subcomponentValue, $subcomponentExists, $subcomponentError, trim($subcomponentComments));
        
    }

    /**
     * Check if segment name exists in next segments of the profile
     * 
     * @param string $segmentName
     * @return bool
     */
    private function checkSegmentNameInProfileStructure($segmentName) {
        $profileNextSegmentsNames = array();
        for($i=$this->profilSegmentLocation+1; $i < count($this->profileListOfSegmentNames); $i++) {
            $profileNextSegmentsNames[] = $this->profileListOfSegmentNames[$i];
        }
        return (in_array($segmentName, $profileNextSegmentsNames)) ? true : false;
    }

    /**
     * Check Group/Segment/Field/Component/SubComponent Usage
     * 
     * @param string $elementUsage
     * @param bool $elementExists
     * @param string $elementType
     * @param string $elementName
     * @param string $elementLocation
     */
    private function checkUsage($elementUsage = "", $elementExists = false, $elementType = "", $elementName = "", $elementLocation = "") {
        // need : segmentUsage + segmentExists  + currentLocation + type (segment/Field...)
        $result = null;
        $type = "";
        $description = "";
        if( $elementUsage == "R" && ! $elementExists ) {
            $description = "$elementType '$elementName' is required.";
            $type = "Required element";
            $result = false;
        }
        else if( $elementUsage == "X" && $elementExists ) {
            $description = "$elementType '$elementName' is not allowed.";
            $type = "Element not allowed";
            $result = false;
        }
        else if( $elementUsage == "C" && $elementExists ) {
            $description = "$elementType '$elementName' optionality is set as 'conditional'. Refer to the specification to check the optionality which applies in the context of this message.";
            $type = "Conditional";
            $result = true;
        }
        else if( $elementExists ) {
            $description = "$elementType '$elementName' usage is $elementUsage.";
            $result = true;
            $type = "Usage";
        }
        else {
            $description = "$elementType '$elementName' usage is $elementUsage.";
            $result = true;
            $type = "Usage";
        }

        if( ! is_null($result) ) {
            $this->addTestReport($elementLocation, $description, $type, $result);
        }

        return array($result, $type, $description);
    }

    /**
     * Check Group/Segment/Field Cardinality
     * 
     * @param int $min
     * @param float $max
     * @param string $maxStr
     * @param int $elementCnt
     * @param bool $elementExists
     * @param string $elementUsage
     * @param string $elementType
     * @param string $elementName
     * @param string $elementLocation
     */
    private function checkCardinality($min = 0, $max = 0, $maxStr, $elementCnt = 0, $elementExists = false, $elementUsage = "", $elementType = "", $elementName = "", $elementLocation = "") {
        $result = null;
        $type = "";
        $description = "";
        if( ($elementCnt < $min) && $elementUsage == "R" ) {
            $description = "$elementType '$elementName' cardinality is [$min..$maxStr]. Must have at least $min repetition(s) (found $elementCnt).";
            $result = false;
            $type = "Cardinality";
        }
        if( ($max > 0) && ($elementCnt > $max) ) {
            $description = "$elementType '$elementName' cardinality is [$min..$maxStr]. Must have no more than $maxStr repetition(s) (found $elementCnt).";
            $result = false;
            $type = "Cardinality";
        }
        else if( $elementExists ) {
            $description = "$elementType '$elementName' cardinality is [$min..$maxStr]. Found $elementCnt time(s).";
            $result = true;
            $type = "Cardinality";
        }
        else {
            $description = "$elementType '$elementName' cardinality is [$min..$maxStr]. Found $elementCnt time(s).";
            $result = true;
            $type = "Cardinality";
        }

        if( ! is_null($result) ) {
            $this->addTestReport($elementLocation, $description, $type, $result);
        }

        return array($result, $type, $description);
    }

    /**
     * Check Field/Component/SubComponent Length
     * 
     * @param int $length
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     * @param string $elementLocation
     * 
     * @return array [bool $result, string $type, string $description]
     * 
     */
    private function checkLength($length = 0, $elementValue = "", $elementType = "", $elementName = "", $elementLocation = "") {
        $type = "Length";
        $result = ( mb_strlen($elementValue) <= $length ) ? true : false;
        $description = "$elementType '$elementName' length " . (($result)? "does not exceed" : "exceeds") . " the length defined in the message profile ($length).";
        $this->addTestReport($elementLocation, $description, $type, $result);
        
        return array($result, $type, $description);
    }

    /**
     * Check Field/Component/SubComponent hl7table 
     * 
     * @param string $table
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     * @param string $elementLocation
     * 
     * @return array [bool $result, string $type, string $description]
     */
    private function checkHL7table($table="", $elementValue = "", $elementType = "", $elementName = "", $elementLocation = "") {
        $type = "Table";
        $result = (in_array($elementValue, $this->hl7tables[$table])) ? true : false;
        $description = "$elementType '$elementName' value ($elementValue) " . (($result)? "exists in" : "not in") . " table $table.";
        $this->addTestReport($elementLocation, $description, $type, $result);

        return array($result, $type, $description);
    }

    



    /**
     * 
     * Logs & Reports
     * ------------------
     * 
     */

    /**
     * Add logs
     * 
     * @param $string
     */
    private function addLogs($string) {
        if( $this->debug ) {
            $this->logs[] = $string;
        }
    }

    /**
     * Add test report
     *
     * @param string $location
     * @param string $description
     * @param string $type
     * @param bool $result
     */
    private function addTestReport($location = "", $description = "", $type = "", $result = true ) {
        $this->testReport[] = array(
            "Location" => $location,
            "Description" => $description,
            "Type" => $type,
            "Result" => $result
        );

        if( ! $result ) {
            $this->testReportErrorCnt++;
        }
    }

    /**
     * Add Group validation report
     * 
     * @param string $name
     * @param string $longName
     * @param string $usage
     * @param string $card
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementReps
     * @param string $elementComments
     * 
     */
    private function addGroupValidationReport($name = "", $longName= "", $usage = "", $card = "", $elementExists = false, $elementError = false, $elementReps = "", $elementComments = "" ) {
        $this->validationReport["validation"][] = array(
            "type" => "Group",
            "name" => $name,
            "longName" => $longName,
            "usage" => $usage,
            "card" => $card,
            "elementExists" => $elementExists,
            "elementError" => $elementError,
            "elementReps" => $elementReps,
            "elementComments" => $elementComments,
        );
    }

    /**
     * Add Segment validation report
     * 
     * @param string $name
     * @param string $longName
     * @param string $usage
     * @param string $card
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementReps
     * @param string $elementComments
     * 
     */
    private function addSegmentValidationReport($name = "", $longName= "", $usage = "", $card = "", $elementExists = false, $elementError = false, $elementReps = "", $elementComments = "" ) {
        $this->validationReport["validation"][] = array(
            "type" => "Segment",
            "name" => $name,
            "longName" => $longName,
            "usage" => $usage,
            "card" => $card,
            "elementExists" => $elementExists,
            "elementError" => $elementError,
            "elementReps" => $elementReps,
            "elementComments" => $elementComments,
        );
    }

    /**
     * Add Field validation report
     * 
     * @param string $location (currentLocation)
     * @param string $name
     * @param string $usage
     * @param string $card
     * @param string $datatype
     * @param string $length
     * @param string $itemNo
     * @param string $table
     * @param string $reference
     * @param string $impNote
     * @param string $elementValue
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementReps
     * @param string $elementComments
     * 
     */
    private function addFieldValidationReport( $location = "", $name= "", $usage = "", $card = "", $datatype = "", $length = "", $itemNo = "", $table = "", $reference = "", $impNote = "", $elementValue = "", $elementExists = false, $elementError = false, $elementReps = "", $elementComments = "") {
        $this->validationReport["validation"][] = array(
            "type" => "Field",
            "location" => $location,
            "name" => $name,
            "usage" => $usage,
            "card" => $card,
            "datatype" => $datatype,
            "length" => $length,
            "itemNo" => $itemNo,
            "table" => $table,
            "reference" => $reference,
            "impNote" => $impNote,
            "elementValue" => $elementValue,
            "elementExists" => $elementExists,
            "elementError" => $elementError,
            "elementReps" => $elementReps,
            "elementComments" => $elementComments
        );
    }

    /**
     * Add Component validation report
     * 
     * @param string $location (currentLocation)
     * @param string $name
     * @param string $usage
     * @param string $datatype
     * @param string $length
     * @param string $table
     * @param string $impNote
     * @param string $elementValue
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementComments
     * 
     */
    private function addComponentValidationReport( $location = "", $name= "", $usage = "", $datatype = "", $length = "", $table = "", $impNote = "", $elementValue = "", $elementExists = false, $elementError = false, $elementComments = "") {
        $this->validationReport["validation"][] = array(
            "type" => "Component",
            "location" => $location,
            "name" => $name,
            "usage" => $usage,
            "datatype" => $datatype,
            "length" => $length,
            "table" => $table,
            "impNote" => $impNote,
            "elementValue" => $elementValue,
            "elementExists" => $elementExists,
            "elementError" => $elementError,
            "elementComments" => $elementComments
        );
    }

    /**
     * Add SubComponent validation report
     * 
     * @param string $location (currentLocation)
     * @param string $name
     * @param string $usage
     * @param string $datatype
     * @param string $length
     * @param string $constantValue
     * @param string $table
     * @param string $impNote
     * @param string $elementValue
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementComments
     * 
     */
    private function addSubComponentValidationReport( $location = "", $name= "", $usage = "", $datatype = "", $length = "", $constantValue = "", $table = "", $impNote = "", $elementValue = "", $elementExists = false, $elementError = false, $elementComments = "") {
        $this->validationReport["validation"][] = array(
            "type" => "SubComponent",
            "location" => $location,
            "name" => $name,
            "usage" => $usage,
            "datatype" => $datatype,
            "length" => $length,
            "constantValue" => $constantValue,
            "table" => $table,
            "impNote" => $impNote,
            "elementValue" => $elementValue,
            "elementExists" => $elementExists,
            "elementError" => $elementError,
            "elementComments" => $elementComments
        );
    }

}
