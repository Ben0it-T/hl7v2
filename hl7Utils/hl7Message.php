<?php
declare(strict_types=1);
namespace HL7;

/**
 * HL7 message
 * Parses and compares HL7 message against a profile.
 * 
 */
class Message {

    public  bool   $debug;
    public  array  $logs;
    public  string $parseMessageError;
    // Field Separator & Encoding Characters
    private string $segmentSeparator;
    private string $fieldSeparator;
    private string $componentSeparator;
    private string $fieldRepeatSeparator;
    private string $escapeChar;
    private string $subComponentSeparator;
    // Message
    private array  $msgParse; // (very) simple array representation of the HL7 message
    private array  $msgData;  // array representation of the HL7 message, according to profile
    public  string $messageType; // message type code (MSH-9.1)
    public  string $messageTriggerEvent; // trigger event code (MSH-9.2)
    public  string $messageStructID; // abstract message structure code (MSH-9.3)
    public  string $messageVersionID; // message version ID (MSH-12.1)
    private array  $messageSegmentNames; // list of segment names
    private array  $notDefinedSegment; // list of not defined segment names
    private array  $notPresentSegment; // list of not present segment names
    // Profile
    private string $profilePath;
    private array  $profile;
    private array  $profileSegmentNames;
    private array  $hl7tables;
    // Validation
    public  array  $testReport;
    public  array  $validationReport;
    public  int    $testReportErrorCnt;

    /**
     * Create a new instance
     * 
     * @param bool $debug
     */
    public function __construct($debug = false) {
        $this->setDefaults();
        $this->debug = $debug;
    }

    private function setDefaults() {
        $this->debug = false;
        $this->logs = array();
        $this->parseMessageError = "";
        // Field Separator & Encoding Characters
        $this->segmentSeparator = '\r'; // 0x0D (CR)
        $this->fieldSeparator = '|';
        $this->componentSeparator = '^';
        $this->fieldRepeatSeparator = '~';
        $this->escapeChar = '\\';
        $this->subComponentSeparator = '&';
        // Message
        $this->msgParse = array();
        $this->msgData = array();
        $this->messageType = "";
        $this->messageTriggerEvent = "";
        $this->messageStructID = "";
        $this->messageVersionID = "";
        $this->messageSegmentNames = array();
        $this->notDefinedSegment = array();
        $this->notPresentSegment = array();
        // Profile
        $this->profilePath = __DIR__ . "/../profiles";
        $this->profile = array();
        $this->profileSegmentNames = array();
        $this->hl7tables = array();
        // Validation
        $this->testReport = array();
        $this->validationReport = array();
        $this->testReportErrorCnt = 0;
    }

    /**
     * Getters & setters
     * 
     */
    public function getMsgParse() {
        return $this->msgParse;
    }

    public function getMsgData() {
        return $this->msgData;
    }

    public function getMsgXML($exportURN = true) {
        return $this->msgDataToXML($exportURN);
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


    public function setDebug($debug = true) {
        $this->debug = $debug;
    }

    public function setProfilePath($path = "") {
        if ($path != "" && is_dir($path)) {
            $this->profilePath = $path;
        }
    }






    /**
     * Add logs, if debug is true
     * 
     * @param $string
     */
    private function addLogs($string) {
        if ($this->debug) {
            $this->logs[] = $string;
        }
    }

    /**
     * Add test report and increase test report error counter
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
        if (!$result) {
            $this->testReportErrorCnt++;
        }
    }

    /**
     * Add validation report
     * 
     * @param string $type (Group, Segment, Field ...)
     * @param string $location (currentLocation)
     * @param string $name
     * @param string $longName
     * @param string $usage
     * @param string $card
     * @param string $datatype
     * @param string $length
     * @param string $constantValue
     * @param string $itemNo
     * @param string $table
     * @param string $reference
     * @param string $impNote
     * @param string $elementValue
     * @param bool $elementExists
     * @param bool $elementError
     * @param string $elementReps
     * @param string $elementComments
     */
     private function addValidationReport($type = "", $location = "", $name = "", $longName= "", $usage = "", $card = "", $datatype = "", $length = "", $constantValue = "", $itemNo = "", $table = "", $reference = "", $impNote = "", $elementValue = "", $elementExists = false, $elementError = false, $elementReps = "", $elementComments = "" ) {
        $report = array();
        switch ($type) {
            case 'Group':
                $report = array(
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
                break;

            case 'Segment':
                $report = array(
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
                break;

            case 'Field':
                $report = array(
                    "type" => "Field",
                    "location" => $location,
                    "name" => $name,
                    "longName" => $longName,
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
                    "elementComments" => $elementComments,
                );
                break;

            case 'Component':
                $report = array(
                    "type" => "Component",
                    "location" => $location,
                    "name" => $name,
                    "longName" => $longName,
                    "usage" => $usage,
                    "datatype" => $datatype,
                    "length" => $length,
                    "table" => $table,
                    "impNote" => $impNote,
                    "elementValue" => $elementValue,
                    "elementExists" => $elementExists,
                    "elementError" => $elementError,
                    "elementComments" => $elementComments,
                );
                break;

            case 'SubComponent':
                $report = array(
                    "type" => "SubComponent",
                    "location" => $location,
                    "name" => $name,
                    "longName" => $longName,
                    "usage" => $usage,
                    "datatype" => $datatype,
                    "length" => $length,
                    "constantValue" => $constantValue,
                    "table" => $table,
                    "impNote" => $impNote,
                    "elementValue" => $elementValue,
                    "elementExists" => $elementExists,
                    "elementError" => $elementError,
                    "elementComments" => $elementComments,
                );
                break;

            default:
                break;
        }
        $this->validationReport[] = $report;
    }





    /**
     * Parse HL7 message string
     * Splits message to segments and creates a very simple array representation of the message (msgParse),
     * then compares message against a profile.
     * 
     * Note: msgParse[segmentLocation][segmentName][field][repeat][component][subcomponent]
     * 
     * @param string $msgStr the HL7 message string
     * @return bool  Returns false if ctrl string is not valid or if profile/hl7tables not found
     */
    public function parseMessage($msgStr = '') {
        if (empty($msgStr)) {
            $this->parseMessageError = "Message is empty.";
            return false;
        }

        // Check if message is a valid message
        // Extract ctrl string (first nine chars):
        //   - first three chars: fisrt segment name (MSH)
        //   - char 4: field separator (MSH-1)
        //   - char 5 to 8: encoding characters (MSH-2)
        //   - char 9: must be a field separator
        $ctrl = substr($msgStr, 0, 9);
        if (!preg_match('/^([A-Z0-9]{3})(.)(.)(.)(.)(.)(.)/', $ctrl, $matches)) {
            // --------->    MSH         |  ^  ~  \  &  |
            $this->parseMessageError = "This is not a valid message. Please check MSH segment.";
            return false;
        }
        // first segment name must be "MSH"
        if ($matches[1] != "MSH") {
            $this->parseMessageError = "This is not a valid message. MSH segment not found.";
            return false;
        }
        // field separator must be the same as the first field separator
        if ($matches[2] != $matches[7]) {
            $this->parseMessageError = "This is not a valid message. Invalid field separator.";
            return false;
        }

        // Set separator and encoding characters
        $this->fieldSeparator = $matches[2];
        $this->componentSeparator = $matches[3];
        $this->fieldRepeatSeparator = $matches[4];
        $this->escapeChar = $matches[5];
        $this->subComponentSeparator = $matches[6];

        // Split message to segments
        $segments = preg_split("/[\n\r" . $this->segmentSeparator . ']/', $msgStr, -1, PREG_SPLIT_NO_EMPTY);

        // Read the fisrt segment (MSH) and get 'Message Type' (MSH-9) and 'Version ID' (MSH-12)
        $MSH = preg_split("/\\" . $this->fieldSeparator .'/', $segments[0]);
        // message type (MSH-9)
        $messageType = preg_split("/\\".$this->componentSeparator."/", $MSH[8], -1);
        $this->messageType = (isset($messageType[0]) ? $messageType[0] : "");
        $this->messageTriggerEvent = (isset($messageType[1]) ? $messageType[1] : "");
        $this->messageStructID = (isset($messageType[2]) ? $messageType[2] : "");
        if ($this->messageType == "ACK") {
            $this->messageTriggerEvent = $this->messageStructID = $this->messageType;
        }
        // message version ID (MSH-12)
        $messageVersionID = preg_split("/\\".$this->componentSeparator."/", $MSH[11], -1);
        $this->messageVersionID = (isset($messageVersionID[0]) ? $messageVersionID[0] : "");

        // Simply (naively) convert the HL7 message into a simple array: msgParse[segmentLocation][segmentName][field][repeat][component][subcomponent]
        // foreach segment
        foreach ($segments as $segment) {
            $fields = preg_split("/\\" . $this->fieldSeparator .'/', $segment);
            $segmentName = $fields[0];
            $currentSegment = array();

            // foreach field
            for ($i=1; $i<count($fields); $i++) {
                $n = $i;
                if ($segmentName === "MSH") {
                    // MSH-1 is fieldSeparator
                    $n = $i+1;
                }
                if ($segmentName === "MSH" && $i === 1) {
                    // set MSH-1 (fieldSeparator)
                    //$currentSegment[1] = array(0 => $this->fieldSeparator);
                    $currentSegment[1] = array(0 => array(1 => array(1 => $this->fieldSeparator)));
                }
                $currentSegment[$n] = array();

                // get field repeats
                $fieldRepeats = preg_split("/\\".$this->fieldRepeatSeparator."/", $fields[$i], -1);
                if ($fieldRepeats === false || ($segmentName === "MSH" && $i === 1)) {
                    // MSH-2 = '^~\&'
                    $fieldRepeats = array( 0 => $fields[$i] );
                }

                // foreach field repeat
                foreach ($fieldRepeats as $rep => $fieldRepeat) {
                    $currentSegment[$n][$rep] = array();

                    // get components
                    $components = preg_split("/\\".$this->componentSeparator."/", $fieldRepeat, -1);
                    if ($components === false || ($segmentName === "MSH" && $i === 1)) {
                        // MSH-2 = '^~\&'
                        $components = array( 0 => $fieldRepeat);
                    }

                    // foreach component
                    for ($j=0; $j<count($components); $j++) {
                        $currentSegment[$n][$rep][$j+1] = array();
                        // get subcomponents
                        $subcomponents = preg_split("/\\".$this->subComponentSeparator."/", $components[$j], -1);
                        if ($subcomponents === false || ($segmentName === "MSH" && $i===1)) {
                            $subcomponents = array( 0 => $components[$j]);
                        }

                        // foreach subcomponent
                        for ($k=0; $k<count($subcomponents); $k++) {
                            $currentSegment[$n][$rep][$j+1][$k+1] = $subcomponents[$k];
                        }
                    }
                }
            }

            // Push segment to msgParse
            $this->msgParse[] = array(
                $segmentName => $currentSegment,
            );

            // Push segment name
            $this->messageSegmentNames[] = $segmentName;
        }

        // Validate HL7 message against profile and set an array representation of the HL7 message
        $ret = $this->validateMessage();

        return $ret;
    }





    /**
     * Return a string representation of the message (from msgParse)
     * Note: msgParse[segmentLocation][segmentName][field][repeat][component][subcomponent]
     * 
     * @return string
     */
    public function msgParseToString() {
        $messageStr = "";
        if (!empty($this->msgParse)) {
            foreach ($this->msgParse as $segmentLocation) {
                $segmentStr = $this->msgParseSegmentLocationToString($segmentLocation);
                $messageStr .= $segmentStr . "\r\n"; // CRLF
            }
        }
        return $messageStr;
    }

    /**
     * Return msgParse segmentLocation to string
     * 
     * @param array $segmentLocation
     * @return string
     */
    public function msgParseSegmentLocationToString($segmentLocation) {
        $segmentName = key($segmentLocation);
        $segmentStr = $segmentName . $this->fieldSeparator;
        $start = 1;
        if ($segmentName === "MSH") {
            // MSH-1 is field separator
            $start = 2;
        }
        for ($i=$start; $i <= count($segmentLocation[$segmentName]); $i++) {
            $fieldStr = $this->msgParseFieldToString($segmentLocation[$segmentName][$i]);
            $segmentStr .= $fieldStr;
            if ($i < count($segmentLocation[$segmentName])) {
                $segmentStr .= $this->fieldSeparator;
            }
        }
        return $segmentStr;
    }

    /**
     * Return msgParse field to string
     * 
     * @param array $field
     * @return string
     */
    public function msgParseFieldToString($field) {
        $fieldStr = "";
        // for field repeats
        for ($i=0; $i < count($field); $i++) {
            $fieldStr .= $this->msgParseFieldRepeatToString($field[$i]);
            if ($i < (count($field) - 1)) {
                $fieldStr .= $this->fieldRepeatSeparator;
            }
        }
        return $fieldStr;
    }

    /**
     * Return msgParse field repeat to string
     * 
     * @param array $fieldRepeat
     * @return string
     */
    public function msgParseFieldRepeatToString($fieldRepeat) {
        $fieldRepeatStr = "";
        if (is_array($fieldRepeat)) {
            // component
            for ($i=1; $i <= count($fieldRepeat); $i++) {
                $fieldRepeatStr .= $this->msgParseComponentToString($fieldRepeat[$i]);
                if ($i < (count($fieldRepeat))) {
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
     * Return msgParse component to string
     * 
     * @param array $component
     * @return string
     */
    public function msgParseComponentToString($component) {
        $componentStr = "";
        if (is_array($component)) {
            // sub component
            for ($i=1; $i <= count($component); $i++) {
                $componentStr .= $this->msgParseSubComponentToString($component[$i]);
                if ($i < count($component)) {
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
     * Return msgParse subComponent to string
     * 
     * @param array $subComponent
     * @return string
     */
    public function msgParseSubComponentToString($subComponent) {
        return $subComponent;
    }





    /**
     * Escape field separator and encoding characters
     * 
     * @param string $string
     * @return string 
     */
    public function escapeSequences($string) {
        // E T S R F
        // escape the encoding characters
        $string = str_replace($this->escapeChar,$this->escapeChar."E".$this->escapeChar,$string);
        $string = str_replace($this->subComponentSeparator,$this->escapeChar."T".$this->escapeChar,$string);
        $string = str_replace($this->componentSeparator,$this->escapeChar."S".$this->escapeChar,$string);
        $string = str_replace($this->fieldRepeatSeparator,$this->escapeChar."R".$this->escapeChar,$string);
        // escape the field separator
        $string = str_replace($this->fieldSeparator,$this->escapeChar."F".$this->escapeChar,$string);
        // Todo
        // \.br\        Carriage return
        // \X0A\        Line feed
        // \X0D\        
        // \Xdddd...\   hexadecimal data 
        // \Zdddd...\   locally defined escape sequence
        return $string;
    }

    /**
     * Unescape field separator and encoding characters
     * 
     * @param string $string
     * @return string
     */
    public function unescapeSequences($string) {
        // F R S T E
        // unescape the field separator
        $string = str_replace($this->escapeChar."F".$this->escapeChar,$this->fieldSeparator,$string);
        // unescape the encoding characters
        $string = str_replace($this->escapeChar."R".$this->escapeChar,$this->fieldRepeatSeparator,$string);
        $string = str_replace($this->escapeChar."S".$this->escapeChar,$this->componentSeparator,$string);
        $string = str_replace($this->escapeChar."T".$this->escapeChar,$this->subComponentSeparator,$string);
        $string = str_replace($this->escapeChar."E".$this->escapeChar,$this->escapeChar,$string);
        // Todo
        // \.br\        Carriage return
        // \X0A\        Line feed
        // \X0D\        
        // \Xdddd...\   hexadecimal data 
        // \Zdddd...\   locally defined escape sequence
        return $string;
    }





    /**
     * Get segment Name at segmentLocation in msgParse
     * 
     * @param int $segmentLocation
     * @return string
     */
    private function getMsgParseSegmentName($segmentLocation) {
        $segmentName = "";
        if (isset($this->msgParse[$segmentLocation])) {
            $segmentName = sprintf("%s", key($this->msgParse[$segmentLocation]));
        }
        return $segmentName;
    }

    /**
     * Get the number of repetitions of a segment in msgParse
     * 
     * @param  string $segmentName
     * @param  int    $segmentLocation 
     * @return int    number of repetitions of the segment
     */
    private function getMsgParseSegmentReps($segmentName, $segmentLocation) {
        $segmentReps = 0;
        if (isset($this->msgParse[$segmentLocation][$segmentName])) {
            $segmentReps++;
            for ($i=$segmentLocation+1; $i <= count($this->msgParse); $i++) {
                if (isset($this->msgParse[$i][$segmentName])) {
                    $segmentReps++;
                } else {
                    break;
                }
            }
        }
        return $segmentReps;
    }

    /**
     * Get the number of repetitions of a group in msgParse
     * Get msgParse segment repetitions
     * 
     * @param  int     $segmentLocation
     * @param  string  $firstSegmentNameInGroup
     * @param  array   $segmentsInGroup
     * @return int     number of repetitions of the group
     */
    private function getMsgParseGroupReps($segmentLocation, $firstSegmentNameInGroup, $segmentsInGroup) {
        $groupReps = 0;
        for ($i=$segmentLocation; $i <= count($this->msgParse); $i++) {
            $segmentName = $this->getMsgParseSegmentName($i);
            if ($segmentName == $firstSegmentNameInGroup) {
                $groupReps++;
            }
            if (in_array($segmentName, $this->notDefinedSegment)) {
                // not defined segment
                // do notthing
            } else if (!in_array($segmentName, $segmentsInGroup)) {
                // not in the group
                break;
            }
        }
        return $groupReps;
    }

    /**
     * Get the number of repetitions of a field
     * 
     * @param  string $segmentName
     * @param  int    $segmentLocation 
     * @param  int    $fieldLocation 
     * @return int    number of repetitions of the field
     */
    private function getMsgParseFieldReps($segmentName, $segmentLocation, $fieldLocation) {
        $reps = 0;
        if (isset($this->msgParse[$segmentLocation][$segmentName][$fieldLocation])) {
            $fieldReps = count($this->msgParse[$segmentLocation][$segmentName][$fieldLocation]);
            if ($fieldReps > 1) {
                $reps = $fieldReps;
            }
            else {
                // unique instance of Field
                if (is_array($this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0])) {
                    $hasValue = false;
                    foreach ($this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0] as $component) {
                        foreach ($component as $subcomponent) {
                            if ($subcomponent != "") {
                                $hasValue = true;
                            }
                        }
                    }
                    if ($hasValue) {
                        $reps = 1;
                    }
                }
                else {
                    if ($this->msgParse[$segmentLocation][$segmentName][$fieldLocation][0] != "") {
                        $reps = 1;
                    }
                }
            }
        }
        return $reps;
    }

    /**
     * Check if Component exists
     * 
     * @return bool
     */
    private function isMsgParseComponentExists() {
        $exists = false;
        if (isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation])) {
            if (is_array($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation])) {
                // if has sub component
                $hasValue = false;
                foreach ($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation] as $subcomponent) {
                    if ($subcomponent != "") {
                        $hasValue = true;
                    }
                }
                if ($hasValue) {
                    $exists = true;
                }
            }
            else if ($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation] != "") {
                $exists = true;
            }
        }
        return $exists;
    }

    /**
     * Check if SubComponent exists
     * 
     * @return bool
     */
    private function isMsgParseSubComponentExists() {
        $exists = false;
        if (isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation])) {
            if ($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation] != "") {
                $exists = true;
            }
        }
        return $exists;
    }





    /**
     * Validate message against profile
     * Compares message against profile and creates 
     * - an array representation of the HL7 message according to profile (msgData),
     * - a test report and a validation report
     * 
     * @return bool Returns false if profil or hl7tables not found
     */
    private function validateMessage() {
        $this->testReport = array();
        $this->testReportErrorCnt = 0;
        $this->validationReport = array();

        // Load json profile
        $this->addLogs("--- Load json profile ---");
        if ($this->messageStructID == "") {
            $messageTypeFileName = $this->profilePath . "/json-" . $this->messageVersionID . "/messageType.json";
            if (!is_file($messageTypeFileName)) {
                $this->parseMessageError = "Profile not found.";
                $this->addLogs("Profile not found.");
                return false;
            }
            $messageTypeArray = json_decode(file_get_contents($messageTypeFileName), true);
            if (isset($messageTypeArray[$this->messageType][$this->messageTriggerEvent])) {
                $this->messageStructID = $messageTypeArray[$this->messageType][$this->messageTriggerEvent];
            }
        }

        $profileName = $this->messageType . "-" . $this->messageTriggerEvent . "-" . $this->messageStructID;
        $profileFileName = $this->profilePath . "/json-" . $this->messageVersionID . "/" . $profileName . ".json";
        if (!is_file($profileFileName)) {
            $this->parseMessageError = "Profile not found.";
            $this->addLogs("Profile not found.");
            return false;
        }
        $this->profile = json_decode(file_get_contents($profileFileName), true);
        $this->profileSegmentNames = $this->getProfileListOfSegmentNames($this->profile);

        // Load HL7 table
        $this->addLogs("--- Load HL7 table ---");
        $tableNameFileName = $this->profilePath . "/json-" . $this->messageVersionID . "/" . "hl7tables.json";
        if (!is_file($tableNameFileName)) {
            $this->parseMessageError = "HL7 tables not found.";
            $this->addLogs("HL7 tables not found.");
            return false;
        }
        $this->hl7tables = json_decode(file_get_contents($tableNameFileName), true);

        // Get not defined segment and not present segment
        $this->notDefinedSegment = array_diff($this->messageSegmentNames, $this->profileSegmentNames);
        $this->notPresentSegment = array_diff($this->profileSegmentNames, $this->messageSegmentNames);

        $this->addLogs("--- Segment names ---");
        $this->addLogs("Segment names in profile: " . implode(", ", $this->profileSegmentNames));
        $this->addLogs("Segment names in message: " . implode(", ", $this->messageSegmentNames));
        $this->addLogs("Not defined segments: " . implode(", ", $this->notDefinedSegment));
        $this->addLogs("Not present segments: " . implode(", ", $this->notPresentSegment));
        $this->addLogs("--- Validation begin ---");

        $this->segmentLocation = 0;         // msgParse segment location
        $this->profileSegmentLocation = 0;  // profileSegmentNames current location
        $this->profileLocationMoveBack = false;
        $this->profileParentGroupFirstSegmentsName = array();

        // Create root group
        $groupDef = array(
            "Type" => "group",
            "Name" => $this->messageStructID,
            "Usage" => "R",
            "Min" => "1",
            "Max" => "1",
            "LongName" => $this->messageStructID,
            "segments" => $this->profile,
        );

        // Validate root group
        $data = $this->validateGroup($groupDef);
        $this->msgData = $data[0];

        // If not at the end of the message
        if ($this->segmentLocation < count($this->msgParse)) {
            $this->addLogs("There are more segments in message.");
            for ($i = $this->segmentLocation; $i < count($this->msgParse); $i++) {
                $segmentName = $this->getMsgParseSegmentName($i);
                if (in_array($segmentName, $this->notDefinedSegment)) {
                    // not defined segment
                    $description = "Segment '$segmentName' is not defined in the message profile.";
                    $this->addLogs("-Segment- >> message '$segmentName' segment is not defined.");
                    $this->addTestReport($this->messageStructID, $description, "Structure", false);
                    $this->addValidationReport("Segment", "", $segmentName, "Not defined segment", "", "", "", "", "", "", "", "", "", "", true, true, 1, $description);
                    $this->msgData["segments"][] = $this->notDefinedSegmentToArray($this->msgParse[$this->segmentLocation]);
                }
                else {
                    // segment is not expected here
                    $segmentDef = $this->findProfileSegmentDef($segmentName, $this->profile);
                    $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
                    $this->addLogs("-Segment- >> message '$segmentName' segment is not expected here.");
                    $this->addTestReport($this->messageStructID, $description, "Structure", false);
                    $this->addValidationReport("Segment", "", $segmentName, $segmentDef["LongName"], $segmentDef["Usage"], "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]", "", "", "", "", "", "", "", "", true, true, 1, $description);
                    $this->msgData["segments"][] = $this->notExpectedSegmentToArray($segmentName, $segmentDef);
                }
                $this->segmentLocation++;
            }
        }

        return true;
    }

    /**
     * Validate Group
     * 
     * @param  array $groupDef
     * @return array $groupArray
     */
    private function validateGroup($groupDef) {
        $groupArray = array();
        $groupError = false;
        $groupComments = "";
        $segmentsInGroup = $this->getProfileListOfSegmentNamesInGroup($groupDef);
        $firstSegmentsInGroup = $this->getProfileListOfFirstNamesOfSegmentsInGroup($groupDef);
        $segmentsInMsgParse = array_slice($this->messageSegmentNames,$this->segmentLocation);
        $segmentName = $this->getMsgParseSegmentName($this->segmentLocation);
        $isGroupExists = (in_array($firstSegmentsInGroup[0], $segmentsInMsgParse) ? true : false);
        $isGroupRepeats = false;
        $groupReps = ($groupDef["Name"] != $this->messageStructID ? $this->getMsgParseGroupReps($this->segmentLocation, $firstSegmentsInGroup[0], $segmentsInGroup) : 1);
        if ($groupReps == 0) { $isGroupExists = false; }
        if ($groupReps > 1) {
            $isGroupRepeats = true;
            $this->profileParentGroupFirstSegmentsName[] = $firstSegmentsInGroup[0];
        }

        $this->addLogs("-Group- --- Group '".$groupDef["Name"]."' begin");
        $this->addLogs("-Group- Group '".$groupDef["Name"]."' " . (($groupDef["Name"] == $this->messageStructID) ? "is" : "is not") . " the root group.");
        $this->addLogs("-Group- firstSegmentNameInGroup: ".$firstSegmentsInGroup[0]);
        $this->addLogs("-Group- firstSegmentsInGroup: " . (implode(", ", $firstSegmentsInGroup)));
        $this->addLogs("-Group- segmentsInGroup: " . (implode(", ", $segmentsInGroup)));
        $this->addLogs("-Group- segmentsInMsgParse: " . (implode(", ", $segmentsInMsgParse)));
        $this->addLogs("-Group- isGroupExists: " . ($isGroupExists ? "true" : "false"));
        $this->addLogs("-Group- groupReps: $groupReps");
        $this->addLogs("-Group- isGroupRepeats: " . ($isGroupRepeats ? "true" : "false"));
        $this->addLogs("-Group- profileParentGroupFirstSegmentsName: " . (implode(", ", $this->profileParentGroupFirstSegmentsName)));
        $this->addLogs("-Group- Segment found (segmentName): $segmentName - location: $this->segmentLocation");

        // check group Usage
        list($checkGroupUsageResult, $checkGroupUsageType, $checkGroupUsageDesc) = $this->checkUsage($groupDef["Usage"], $isGroupExists, "Group", "'".$groupDef["Name"]."'");
        if (!$checkGroupUsageResult) {
            $groupError = true;
            $groupComments .= $checkGroupUsageDesc . " ";
        }

        // check group Cardinality
        list($checkGroupCardinalityResult, $checkGroupCardinalityType, $checkGroupCardinalityDesc) = $this->checkCardinality($groupDef["Min"], $groupDef["Max"], $groupReps, $isGroupExists, $groupDef["Usage"], "Group", "'".$groupDef["Name"]."'");
        if (!$checkGroupCardinalityResult) {
            $groupError = true;
        }
        $groupComments .= $checkGroupCardinalityDesc . " ";

        if (!$isGroupExists) {
            // The group does not exist. Move on and return.
            $this->addLogs("-Group- Group '" . $groupDef["Name"] . "' not found in HL7 message. Move on.");
            $this->addLogs("-Group- --- Group '".$groupDef["Name"]."' end");
            $this->addTestReport($this->messageStructID, $checkGroupUsageDesc, $checkGroupUsageType, $checkGroupUsageResult);
            $this->addTestReport($this->messageStructID, $checkGroupCardinalityDesc, $checkGroupCardinalityType, $checkGroupCardinalityResult);
            $this->addValidationReport("Group", "", "---", "--- ".$groupDef["Name"]." begin", $groupDef["Usage"], "[" . $groupDef["Min"] . ".." . $groupDef["Max"] . "]", "", "", "", "", "", "", "", "", $isGroupExists, $groupError, $groupReps, trim($groupComments));
            $this->addValidationReport("Group", "", "---", "--- ".$groupDef["Name"]." end", "", "", "", "", "", "", "", "", "", "", $isGroupExists, $groupError, "", "");
            // Move
            for ($i = 0; $i < count($segmentsInGroup); $i++) { 
                $this->profileSegmentLocation++; // profileSegmentLocation: Move on
            }
            return $groupArray;
        } else if (in_array($segmentName, $this->notDefinedSegment)) {
            // The group exists but HL7 message segment is not defined. Move back and return.
            $this->addLogs("-Group- Group '" . $groupDef["Name"] . "' found in HL7 message.");
            $this->addLogs("-Group- Segment '$segmentName' is not defined. Move back.");
            $this->addLogs("-Group- --- Group '".$groupDef["Name"]."' end");
            $segmentReps = $this->getMsgParseSegmentReps($segmentName, $this->segmentLocation);
            $description = "Segment '$segmentName' is not defined in the message profile. Found $segmentReps time(s).";
            $this->addTestReport($this->messageStructID, $description, "Structure", false);
            $this->addValidationReport("Segment", "", $segmentName, "Not defined segment", "", "", "", "", "", "", "", "", "", "", true, true, $segmentReps, $description);
            for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                $groupArray[] = $this->notDefinedSegmentToArray($this->msgParse[$this->segmentLocation]);
                $this->segmentLocation++; // msgParse: Move on
            }
            // Move
            $this->profileLocationMoveBack = true; // profileLocation: Move back
            return $groupArray;
        } else if ($segmentName != $firstSegmentsInGroup[0] && !in_array($segmentName, $this->profileParentGroupFirstSegmentsName)) {
            // The group exists but HL7 message segment is not the first segment of the group or of the parent group. Move back and return.
            $this->addLogs("-Group- Group '" . $groupDef["Name"] . "' found in HL7 message.");
            $this->addLogs("-Group- Segment '$segmentName' exists in profile but is not expected here. Move back.");
            $this->addLogs("-Group- --- Group '".$groupDef["Name"]."' end");
            $segmentReps = $this->getMsgParseSegmentReps($segmentName, $this->segmentLocation);
            $segmentDef = $this->findProfileSegmentDef($segmentName, $this->profile);
            $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure. Found $segmentReps time(s).";
            $this->addTestReport($this->messageStructID, $description, "Structure", false);
            $this->addValidationReport("Segment", "", $segmentName, $segmentDef["LongName"], $segmentDef["Usage"], "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]", "", "", "", "", "", "", "", "", true, true, $segmentReps, $description);
            for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                $groupArray[] = $this->notExpectedSegmentToArray($segmentName, $segmentDef);
                $this->segmentLocation++; // msgParse: Move on
            }
            // Move
            $this->profileLocationMoveBack = true; // profileLocation: Move back
            return $groupArray;
        }

        // Add test reports if not the root group
        if ($groupDef["Name"] != $this->messageStructID) {
            $this->addTestReport($this->messageStructID, $checkGroupUsageDesc, $checkGroupUsageType, $checkGroupUsageResult);
            $this->addTestReport($this->messageStructID, $checkGroupCardinalityDesc, $checkGroupCardinalityType, $checkGroupCardinalityResult);
        }

        // foreach group repetition
        $profileSegmentLocation = $this->profileSegmentLocation; // keep profileSegmentLocation
        for ($i = 0; $i < $groupReps; $i++) {
            $this->profileSegmentLocation = $profileSegmentLocation;
            $this->addLogs("-Group- group '".$groupDef["Name"]."' rep " . ($i+1) ."/$groupReps");
            // Add validation report if not the root group
            if ($groupDef["Name"] != $this->messageStructID) {
                $this->addValidationReport("Group", "", "---", "--- ".$groupDef["Name"]." begin" . (($groupReps > 1) ? " (Rep. ".($i+1)."/$groupReps)" : ""), $groupDef["Usage"], "[" . $groupDef["Min"] . ".." . $groupDef["Max"] . "]", "", "", "", "", "", "", "", "", $isGroupExists, $groupError, $groupReps, trim($groupComments));
            }

            // Create group structure
            $group = array(
                "Type" => $groupDef["Type"],
                "Name" => $groupDef["Name"],
                "LongName" => $groupDef["LongName"],
                "segments" => array(),
            );

            for ($j=0; $j < count($groupDef["segments"]); $j++) {
                $this->segmentName = $groupDef["segments"][$j]["Name"];
                $type = $groupDef["segments"][$j]["Type"];
                $segmentArray = array();
                if ($type == "segment") {
                    $segmentName = $this->getMsgParseSegmentName($this->segmentLocation);
                    $this->addLogs("-Segment- profile segment: " . $groupDef["segments"][$j]["Name"] ." (location: $this->profileSegmentLocation). HL7 message segment: $segmentName (location: $this->segmentLocation)");

                    if ($groupDef["segments"][$j]["Name"] === $segmentName) {
                        // Profile segment name matches parseMsg segment name
                        $this->addLogs("-Segment- >> Profile segment found. Validate '$segmentName' segment. Move on");

                        $segmentComments = "";
                        $segmentError = false;
                        $segmentReps = 1;
                        // if segment is not the first segment of the group
                        if ($j > 0) {
                            $segmentReps = $this->getMsgParseSegmentReps($segmentName, $this->segmentLocation);
                        }

                        // check segment Usage
                        list($checkSegmentUsageResult, $checkSegmentUsageType, $checkSegmentUsageDesc) = $this->checkUsage($groupDef["segments"][$j]["Usage"], true, "Segment", "'".$groupDef["segments"][$j]["Name"]."'");

                        if (!$checkSegmentUsageResult) {
                            $segmentError = true;
                            $segmentComments .= $checkSegmentUsageDesc . " ";
                        }

                        // check segment Cardinality
                        list($checkSegmentCardinalityResult, $checkSegmentCardinalityType, $checkSegmentCardinalityDesc) = $this->checkCardinality($groupDef["segments"][$j]["Min"], $groupDef["segments"][$j]["Max"], $segmentReps, true, $groupDef["segments"][$j]["Usage"], "Segment", "'".$groupDef["segments"][$j]["Name"]."'");
                        if (!$checkSegmentCardinalityResult) {
                            $segmentError = true;
                        }
                        $segmentComments .= $checkSegmentCardinalityDesc . " ";

                        $this->addTestReport($this->messageStructID, $checkSegmentUsageDesc, $checkSegmentUsageType, $checkSegmentUsageResult);
                        $this->addTestReport($this->messageStructID, $checkSegmentCardinalityDesc, $checkSegmentCardinalityType, $checkSegmentCardinalityResult);
                        $this->addValidationReport("Segment", "", $groupDef["segments"][$j]["Name"], $groupDef["segments"][$j]["LongName"], $groupDef["segments"][$j]["Usage"], "[" . $groupDef["segments"][$j]["Min"] . ".." . $groupDef["segments"][$j]["Max"] . "]", "", "", "", "", "", "", "", "", true, $segmentError, $segmentReps, trim($segmentComments));

                        for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                            $segmentArray = $this->validateSegment($groupDef["segments"][$j]);
                            $segmentArray["hasError"] = $segmentError;
                            $segmentArray["comments"] = trim($segmentComments);
                            $group["segments"][] = $segmentArray;
                            $this->segmentLocation++; // msgParse: Move on

                        }
                        // Move
                        $this->profileSegmentLocation++; // profileSegment: Move on
                    } else {
                        $this->addLogs("-Segment- >> Profile segment not found.");
                        $segmentError = false;
                        $segmentComments = "";
                        // check segment Usage
                        list($checkSegmentUsageResult, $checkSegmentUsageType, $checkSegmentUsageDesc) = $this->checkUsage($groupDef["segments"][$j]["Usage"], false, "Segment", "'".$groupDef["segments"][$j]["Name"]."'");
                        if (!$checkSegmentUsageResult) {
                            $segmentError = true;
                            $segmentComments .= $checkSegmentUsageDesc . " ";
                        }
                        // check segment Cardinality
                        list($checkSegmentCardinalityResult, $checkSegmentCardinalityType, $checkSegmentCardinalityDesc) = $this->checkCardinality($groupDef["segments"][$j]["Min"], $groupDef["segments"][$j]["Max"], 0, false, $groupDef["segments"][$j]["Usage"], "Segment", "'".$groupDef["segments"][$j]["Name"]."'");
                        if (!$checkSegmentCardinalityResult) {
                            $segmentError = true;
                        }
                        $segmentComments .= $checkSegmentCardinalityDesc . " ";

                        if (in_array($groupDef["segments"][$j]["Name"], $this->notPresentSegment)) {
                            // Case 1: profile segment name is not present in parseMsg
                            $this->addLogs("-Segment- >> profile segment '".$groupDef["segments"][$j]["Name"]."' is not present in HL7 message (Case 1). Move on.");
                            $this->addTestReport($this->messageStructID, $checkSegmentUsageDesc, $checkSegmentUsageType, $checkSegmentUsageResult);
                            $this->addTestReport($this->messageStructID, $checkSegmentCardinalityDesc, $checkSegmentCardinalityType, $checkSegmentCardinalityResult);
                            $this->addValidationReport("Segment", "", $groupDef["segments"][$j]["Name"], $groupDef["segments"][$j]["LongName"], $groupDef["segments"][$j]["Usage"], "[" . $groupDef["segments"][$j]["Min"] . ".." . $groupDef["segments"][$j]["Max"] . "]", "", "", "", "", "", "", "", "", false, $segmentError, 0, trim($segmentComments));
                            // Move
                            $this->profileSegmentLocation++; // profileSegment: Move on
                        } else if (in_array($segmentName, $this->notDefinedSegment)) {
                            // Case 2: HL7 message segment is not defined
                            $this->addLogs("-Segment- >> message segment '$segmentName' is not defined (Case 2). Move back.");
                            $segmentReps = $this->getMsgParseSegmentReps($segmentName, $this->segmentLocation);
                            $description = "Segment '$segmentName' is not defined in the message profile. Found $segmentReps time(s).";
                            $this->addTestReport($this->messageStructID, $description, "Structure", false);
                            $this->addValidationReport("Segment", "", $segmentName, "Not defined segment", "", "", "", "", "", "", "", "", "", "", true, true, $segmentReps, $description);
                            for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                                $group["segments"][] = $this->notDefinedSegmentToArray($this->msgParse[$this->segmentLocation]);
                                $this->segmentLocation++; // msgParse: Move on
                            }
                            // Move
                            $this->profileLocationMoveBack = true; // profileLocation: Move back
                        } else {
                            // Case 3: HL7 message segment exists in profile but is not expected here
                            $this->addLogs("-Segment- >> message '$segmentName' segment exists in profile but is not expected here (Case 3).");
                            $nextSegmentsOfTheGroup = array_slice($segmentsInGroup,$j+1);
                            $this->addLogs("-Segment- >> nextSegmentsOfTheGroup: " . (implode(", ", $nextSegmentsOfTheGroup)));

                            if (in_array($segmentName, $nextSegmentsOfTheGroup)) {
                                // a. message segment name appears later in the group. Move on.
                                $case = "appearsLater";
                                $this->addLogs("-Segment- >> message '$segmentName' segment appears later in the group (a). Move on.");
                            } else if (in_array($segmentName, $segmentsInGroup) && !$isGroupRepeats) {
                                // b. message segment name is in the group but is not expected here. Move back.
                                $case = "notExpected";
                                $this->addLogs("-Segment- >> message '$segmentName' segment is in the group but is not expected here (b). Move back.");
                            } else if (in_array($segmentName, $segmentsInGroup) && $isGroupRepeats) {
                                // c. message segment name appears later in a repetition of the group. Move on.
                                $case = "appearsLater";
                                $this->addLogs("-Segment- >> message '$segmentName' segment appears later in a repetition of the group (c). Move on.");
                            } else if (in_array($segmentName, $this->profileParentGroupFirstSegmentsName)) {
                                // d. message segment name appears in a repetition of the parent group. Move on.
                                $case = "appearsLater";
                                $this->addLogs("-Segment- >> message '$segmentName' segment appears in a repetition of the parent group (d). Move on.");
                            } else if ($this->checkSegmentNameInProfileStructure($segmentName)) {
                                // e. message segment name appears later in the profile. Move on.
                                $case = "appearsLater";
                                $this->addLogs("-Segment- >> message '$segmentName' segment appears later in the profile (e). Move on.");
                            } else if ($segmentName != "") {
                                // f. segment exists in profile but is not expected here
                                $case = "notExpected";
                                $this->addLogs("-Segment- >> message '$segmentName' segment exists in profile but is not expected here (f). Move back.");
                            } else {
                                $case = "appearsLater";
                                $this->addLogs("-Segment- >> End of msgParse (g). j: $j. profileSegmentLocation: $this->profileSegmentLocation. segmentName: '$segmentName' ($this->segmentLocation). Move on.");
                            }

                            if ($case == "appearsLater") {
                                $this->addTestReport($this->messageStructID, $checkSegmentUsageDesc, $checkSegmentUsageType, $checkSegmentUsageResult);
                                $this->addTestReport($this->messageStructID, $checkSegmentCardinalityDesc, $checkSegmentCardinalityType, $checkSegmentCardinalityResult);
                                $this->addValidationReport("Segment", "", $groupDef["segments"][$j]["Name"], $groupDef["segments"][$j]["LongName"], $groupDef["segments"][$j]["Usage"], "[" . $groupDef["segments"][$j]["Min"] . ".." . $groupDef["segments"][$j]["Max"] . "]", "", "", "", "", "", "", "", "", false, $segmentError, 0, trim($segmentComments));
                                $this->profileSegmentLocation++; // profileSegment: Move on
                            } else if ($case == "notExpected") {
                                $segmentReps = $this->getMsgParseSegmentReps($segmentName, $this->segmentLocation);
                                $segmentDef = $this->findProfileSegmentDef($segmentName, $this->profile);
                                $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure. Found $segmentReps time(s).";
                                $this->addTestReport($this->messageStructID, $description, "Structure", false);
                                $this->addValidationReport("Segment", "", $segmentName, $segmentDef["LongName"], $segmentDef["Usage"], "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]", "", "", "", "", "", "", "", "", true, true, $segmentReps, $description);
                                for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                                    $group["segments"][] = $this->notExpectedSegmentToArray($segmentName, $segmentDef);
                                    $this->segmentLocation++; // msgParse: Move on
                                }
                                $this->profileLocationMoveBack = true; // profileLocation: Move back
                            }

                        }
                    }
                } else if ($type == "group") {
                    $data = $this->validateGroup($groupDef["segments"][$j]);
                    foreach ($data as $occurrence) {
                        $group["segments"][] = $occurrence;
                    }
                }

                // move back
                if ($this->profileLocationMoveBack) {
                    $j--;
                    $this->profileLocationMoveBack = false;
                }
            } // end for segments in the group
            $groupArray[] = $group;
            // Add validation report (end of group) if not the root group
            if ($groupDef["Name"] != $this->messageStructID) {
                $this->addValidationReport("Group", "", "---", "--- ".$groupDef["Name"]." end", "", "", "", "", "", "", "", "", "", "", $isGroupExists, $groupError, "", "");
            }
        } // end for group repetitions

        if ($isGroupRepeats) {
            array_pop($this->profileParentGroupFirstSegmentsName);
        }
        $this->addLogs("-Group- --- Group '".$groupDef["Name"]."' end");
        return $groupArray;
    }

    /**
     * Validate Segment
     * 
     * @param  array $segmentDef
     * @return array $segmentArray
     */
    private function validateSegment($segmentDef) {
        $segmentArray = array();
        $this->segmentName = $segmentDef["Name"];
        $this->fieldLocation=0;
        $this->addLogs("-Segment- Validate segment '" . $segmentDef["Name"] . "'");

        // Create segment structure
        $segmentArray = array(
            "Type" => $segmentDef["Type"],
            "Name" => $segmentDef["Name"],
            "LongName" => $segmentDef["LongName"],
            "hasError" => "",
            "comments" => "",
            "fields" => array(),
        );

        // Validate fields
        for ($i = 0; $i < count($segmentDef["fields"]); $i++) {
            $this->fieldLocation++;
            $data = $this->validateField($segmentDef["fields"][$i]);
            if (count($data) > 0) {
                $segmentArray["fields"][$i+1] = $data;
            }
        }

        // Check if there are more fields in message Segment
        $fieldsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName]);
        if ($fieldsCnt > count($segmentDef["fields"])) {
            $this->addLogs("-Segment- There are more fields in segment '$this->segmentName'.");
            for ($i = $this->fieldLocation+1; $i <= $fieldsCnt; $i++ ) {
                $data = $this->notDefinedFieldToArray($this->msgParse[$this->segmentLocation][$this->segmentName][$i], $this->segmentName, $i);
                $description = "Field '$this->segmentName-$i' is not expected in the '$this->segmentName' structure. Found " . count($data) . " rep(s).";
                $type = "Element not expected";
                $result = false;
                $fieldValue = $this->msgParseFieldToString($this->msgParse[$this->segmentLocation][$this->segmentName][$i]);
                $this->addLogs("-Field- $this->segmentName-$i : $description");
                $this->addTestReport("$this->segmentName-$i", $description, $type, $result);
                $this->addValidationReport("Field", "$this->segmentName-$i", "$this->segmentName.$i", "Not defined field", "", "", "UNKNOWN", "", "", "", "", "", "", $fieldValue, true, true, count($data), $description);
                for ($j = 0; $j < count($data); $j++) {
                    $data[$j]["hasError"] = true;
                    $data[$j]["comments"] = $description;
                }
                $segmentArray["fields"][$i] = $data;
            }
        }

        return $segmentArray;
    }

    /**
     * Validate Field
     * 
     * @param  array $fieldDef
     * @return array $fieldArray
     */
    private function validateField($fieldDef) {
        $fieldArray = array();
        $currentLocation = "$this->segmentName-$this->fieldLocation";
        $fieldReps = $this->getMsgParseFieldReps($this->segmentName, $this->segmentLocation, $this->fieldLocation);
        $fieldExists = ($fieldReps > 0) ? true : false;
        $fieldError = false;
        $fieldComments = "";

        $this->addLogs("-Field- $currentLocation : fieldExists: " . ($fieldExists ? "true" : "false") . " - fieldReps: $fieldReps.");

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($fieldDef["Usage"], $fieldExists, "Field", "'".$fieldDef["LongName"]."'");
        if (!$checkUsageResult) {
            $fieldError = true;
            $fieldComments .= $checkUsageDesc . " ";
        }

        // check card.
        list($checkCardinalityResult, $checkCardinalityType, $checkCardinalityDesc) = $this->checkCardinality($fieldDef["Min"], $fieldDef["Max"], $fieldReps, $fieldExists, $fieldDef["Usage"], "Field", "'".$fieldDef["LongName"]."'");
        if (!$checkCardinalityResult) {
            $fieldError = true;
        }
        $fieldComments .= $checkCardinalityDesc . " ";

        $this->addTestReport($currentLocation, $checkUsageDesc, $checkUsageType, $checkUsageResult);
        $this->addTestReport($currentLocation, $checkCardinalityDesc, $checkCardinalityType, $checkCardinalityResult);

        if ($fieldExists) {
            for ($repeat = 0; $repeat < $fieldReps ; $repeat ++) {
                $fieldRepeatComments = $fieldComments;
                $fieldValue = $this->msgParseFieldRepeatToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat]);
                $this->fieldrepeat = $repeat;

                // check length
                if ($fieldDef["Length"] !== "") {
                    list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($fieldDef["Length"], $fieldValue, "Field", "'".$fieldDef["LongName"]."'");
                    $this->addTestReport($currentLocation, $checkLengthDesc, $checkLengthType, $checkLengthResult);
                    if (!$checkLengthResult) {
                        $fieldError = true;
                        $fieldRepeatComments .= $checkLengthDesc . " ";
                    }
                }

                // check table
                if ($fieldDef["Table"] !== "" && isset($this->hl7tables[$fieldDef["Table"]]) && !isset($fieldDef["components"])) {
                    if (!empty($this->hl7tables[$fieldDef["Table"]]["elements"])) {
                        list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($fieldDef["Table"], $fieldValue, "Field", "'".$fieldDef["LongName"]."'");
                        $this->addTestReport($currentLocation, $checkHL7tableDesc, $checkHL7tableType, $checkHL7tableResult);
                        if (!$checkHL7tableResult) {
                            $fieldError = true;
                        }
                        $fieldRepeatComments .= $checkHL7tableDesc . " ";
                    }
                }

                $this->addValidationReport("Field", $currentLocation, $fieldDef["Name"] . ( ($fieldReps > 1) ? " (Rep. " . ($repeat+1) . ")" : ""), $fieldDef["LongName"], $fieldDef["Usage"], "[". $fieldDef["Min"] . ".." . $fieldDef["Max"] . "]", $fieldDef["Datatype"], $fieldDef["Length"], "", $fieldDef["Item"], $fieldDef["Table"], $fieldDef["Chapter"], "", $fieldValue, $fieldExists, $fieldError, $fieldReps, trim($fieldRepeatComments));

                // Create field structure
                $fld = array(
                    "Type" => "field",
                    "Name" => $fieldDef["Name"],
                    "LocName" => $currentLocation,
                    "LongName" => $fieldDef["LongName"],
                    "Datatype" => $fieldDef["Datatype"],
                    "hasError" => $fieldError,
                    "comments" => $fieldRepeatComments,
                    "value" => $fieldValue,
                );

                $componentsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat]);
                if (isset($fieldDef["components"])) {
                    // validate components
                    $fld["components"] = array();
                    $this->componentLocation=0;
                    for ($i = 0; $i < count($fieldDef["components"]); $i++) {
                        $this->componentLocation++;
                        $data = $this->validateComponent($fieldDef["components"][$i]);
                        if (count($data) > 0) {
                            $fld["components"][$i+1] = $data;
                        }
                    }

                    // Check if there are more components in message
                    if ($componentsCnt > count($fieldDef["components"])) {
                        $this->addLogs("-Field- There are more components in Field '$currentLocation'.");
                        $type = "Element not expected";
                        $result = false;
                        for ($i = $this->componentLocation+1; $i <= $componentsCnt; $i++) {
                            $data = $this->notDefinedComponentToArray($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i], $currentLocation, $i);
                            $description = "Component '$currentLocation.$i' is not expected in Field '$currentLocation' structure.";
                            $componentValue = $this->msgParseComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i]);
                            $this->addLogs("-Component- $currentLocation.$i : $description");
                            $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                            $this->addValidationReport("Component", "$currentLocation.$i", "UNKNOWN.$i", "Not defined component", "", "", "UNKNOWN", "", "", "", "", "", "", $componentValue, true, true, "", $description);
                            $data["hasError"] = true;
                            $data["comments"] = $description;
                            $fld["components"][$i] = $data;
                        }
                    }
                }
                else if ($componentsCnt > 1) {
                    // There is no Component in the profile
                    // Check if there are more than one Component in message
                    $this->addLogs("-Field- Components are not expected in Field '$currentLocation' structure.");
                    $type = "Element not expected";
                    $result = false;
                    for ($i = 1; $i <= $componentsCnt; $i++) {
                        $data = $this->notDefinedComponentToArray($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i], $currentLocation, $i);
                        $description = "Component '$currentLocation.$i' is not expected in Field '$currentLocation' structure.";
                        $componentValue = $this->msgParseComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$repeat][$i]);
                        $this->addLogs("-Component- $currentLocation.$i : $description");
                        $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                        $this->addValidationReport("Component", "$currentLocation.$i", "UNKNOWN.$i", "Not defined component", "", "", "UNKNOWN", "", "", "", "", "", "", $componentValue, true, true, "", $description);
                        $data["hasError"] = true;
                        $data["comments"] = $description;
                        $fld["components"][$i] = $data;
                    }
                }
                // add field
                $fieldArray[] = $fld;
            }
        } else {
            if (isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation])) {
                $fieldArray[0] = array(
                    "Type" => "field",
                    "Name" => $fieldDef["Name"],
                    "LocName" => $currentLocation,
                    "LongName" => $fieldDef["LongName"],
                    "Datatype" => $fieldDef["Datatype"],
                    "hasError" => $fieldError,
                    "comments" => $fieldComments,
                    "value" => "",
                );
            }
            $this->addValidationReport("Field", $currentLocation, $fieldDef["Name"], $fieldDef["LongName"], $fieldDef["Usage"], "[". $fieldDef["Min"] . ".." . $fieldDef["Max"] . "]", $fieldDef["Datatype"], $fieldDef["Length"], "", $fieldDef["Item"], $fieldDef["Table"], $fieldDef["Chapter"], "", "", $fieldExists, $fieldError, $fieldReps, trim($fieldComments));
        }
        return $fieldArray;
    }

    /**
     * Validate Component
     * 
     * @param  array $componentDef
     * @return array $componentArray
     */
    private function validateComponent($componentDef) {
        $componentArray = array();
        $currentLocation = "$this->segmentName-$this->fieldLocation.$this->componentLocation";
        $componentExists = $this->isMsgParseComponentExists();
        $componentValue = "";
        $componentError = false;
        $componentComments = "";

        $this->addLogs("-Component- $currentLocation : componentExists: " . ($componentExists ? 'true' : 'false') . " (" . $componentDef["Usage"] . ")");

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($componentDef["Usage"], $componentExists, "Component", "'".$componentDef["LongName"] . "' (" . $componentDef["Name"] . ")");
        if (!$checkUsageResult) {
            $componentError = true;
            $componentComments .= $checkUsageDesc . " ";
        }
        $this->addTestReport($currentLocation, $checkUsageDesc, $checkUsageType, $checkUsageResult);

        if ($componentExists) {
            $componentValue = $this->msgParseComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]);
            // check length
            if ($componentDef["maxLength"] !== "") {
                list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($componentDef["maxLength"], $componentValue, "Component", "'".$componentDef["LongName"] . "' (" . $componentDef["Name"] . ")");
                $this->addTestReport($currentLocation, $checkLengthDesc, $checkLengthType, $checkLengthResult);
                if (!$checkLengthResult) {
                    $componentError = true;
                    $componentComments .= $checkLengthDesc . " ";
                }
            }

            // check table
            if ($componentDef["Table"] !== "" && isset($this->hl7tables[$componentDef["Table"]])) {
                if (!empty($this->hl7tables[$componentDef["Table"]]["elements"])) {
                    list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($componentDef["Table"], $componentValue, "Component", "'".$componentDef["LongName"] . "' (" . $componentDef["Name"] . ")");
                    $this->addTestReport($currentLocation, $checkHL7tableDesc, $checkHL7tableType, $checkHL7tableResult);
                    if (!$checkHL7tableResult) {
                        $componentError = true;
                    }
                    $componentComments .= $checkHL7tableDesc . " ";
                }
            }
        }

        $this->addValidationReport("Component", $currentLocation, $componentDef["Name"], $componentDef["LongName"], $componentDef["Usage"], "[". $componentDef["Min"] . ".." . $componentDef["Max"] . "]", $componentDef["Type"], $componentDef["maxLength"], "", "", $componentDef["Table"], "", "", $componentValue, $componentExists, $componentError, "", trim($componentComments));

        if ($componentExists) {

            // Create component structure
            $componentArray = array(
                "Type" => "component",
                "Name" => $componentDef["Name"],
                "LocName" => $currentLocation,
                "LongName" => $componentDef["LongName"],
                "Datatype" => $componentDef["Type"],
                "hasError" => $componentError,
                "comments" => $componentComments,
                "value" => $componentValue,
            );

            $subcomponentsCnt = count($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation]);
            if (isset($componentDef["components"])) {
                // validate subComponents
                $componentArray["subcomponents"] = array();
                $this->subcomponentLocation=0;
                for ($i = 0; $i < count($componentDef["components"]); $i++) {
                    $this->subcomponentLocation++;
                    $data = $this->validateSubComponent($componentDef["components"][$i]);
                    if (count($data) > 0) {
                        $componentArray["subcomponents"][$i+1] = $data;
                    }
                }

                // Check if there are more subComponent in message
                if ($subcomponentsCnt > count($componentDef["components"])) {
                    $type = "Element not expected";
                    $result = false;
                    $this->addLogs("-Component- There are more subComponents in component '$currentLocation'.");
                    for($i = $this->subcomponentLocation+1; $i <= $subcomponentsCnt; $i++) {
                        $data = $this->notDefinedSubComponentToArray($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i], $currentLocation, $i);
                        $description = "SubComponent '$currentLocation.$i' is not expected in Component '$currentLocation' structure.";
                        $subcomponentValue = $this->msgParseSubComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i]);
                        $this->addLogs("-SubComponent- $currentLocation.$i : $description");
                        $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                        $this->addValidationReport("SubComponent", "$currentLocation.$i", "UNKNOWN.$i", "Not defined subcomponent", "", "", "UNKNOWN", "", "", "", "", "", "", $subcomponentValue, true, true, "", $description);
                        $data["hasError"] = true;
                        $data["comments"] = $description;
                        $componentArray["subcomponents"][$i] = $data;
                    }
                }
            } else if ($subcomponentsCnt > 1) {
                // There is no subComponent in the profile
                // Check if there are more than one SubComponent in message
                $this->addLogs("-Component- SubComponent are not expected in Component '$currentLocation' structure.");
                $type = "Element not expected";
                $result = false;
                for($i = 1; $i <= $subcomponentsCnt; $i++) {
                    $data = $this->notDefinedSubComponentToArray($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i], $currentLocation, $i);
                    $description = "SubComponent '$currentLocation.$i' is not expected in Component '$currentLocation' structure.";
                    $subcomponentValue = $this->msgParseSubComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$i]);
                    $this->addLogs("-SubComponent- $currentLocation.$i : $description");
                    $this->addTestReport("$currentLocation.$i", $description, $type, $result);
                    $this->addValidationReport("SubComponent", "$currentLocation.$i", "UNKNOWN.$i", "Not defined subcomponent", "", "", "UNKNOWN", "", "", "", "", "", "", $subcomponentValue, true, true, "", $description);
                    $data["hasError"] = true;
                    $data["comments"] = $description;
                    $componentArray["subcomponents"][$i] = $data;
                }
            }
        } else {
            if (isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation])) {
                $componentArray = array(
                    "Type" => "component",
                    "Name" => $componentDef["Name"],
                    "LocName" => $currentLocation,
                    "LongName" => $componentDef["LongName"],
                    "Datatype" => $componentDef["Type"],
                    "hasError" => $componentError,
                    "comments" => $componentComments,
                    "value" => "",
                );
            }
        }
        return $componentArray;
    }

    /**
     * Validate SubComponent
     * 
     * @param  array $subComponentDef
     * @return array $subComponentArray
     */
    private function validateSubComponent($subComponentDef) {
        $subComponentArray = array();
        $currentLocation = "$this->segmentName-$this->fieldLocation.$this->componentLocation.$this->subcomponentLocation";
        $subcomponentExists = $this->isMsgParseSubComponentExists();
        $subcomponentValue = "";
        $subcomponentError = false;
        $subcomponentComments = "";

        $this->addLogs("-SubComponent- $currentLocation : subcomponentExists: " . ($subcomponentExists ? 'true' : 'false') . " (" . $subComponentDef["Usage"] . ")");

        // check usage
        list($checkUsageResult, $checkUsageType, $checkUsageDesc) = $this->checkUsage($subComponentDef["Usage"], $subcomponentExists, "SubComponent", "'".$subComponentDef["LongName"] . "' (" . $subComponentDef["Name"] . ")");
        if (!$checkUsageResult) {
            $subcomponentError = true;
            $subcomponentComments .= $checkUsageDesc . " ";
        }
        $this->addTestReport($currentLocation, $checkUsageDesc, $checkUsageType, $checkUsageResult);

        if ($subcomponentExists) {
            $subcomponentValue = $this->msgParseSubComponentToString($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation]);
            // check length
            if ($subComponentDef["maxLength"] !== "") {
                list($checkLengthResult, $checkLengthType, $checkLengthDesc) = $this->checkLength($subComponentDef["maxLength"], $subcomponentValue, "SubComponent", "'".$subComponentDef["LongName"] . "' (" . $subComponentDef["Name"] . ")");
                $this->addTestReport($currentLocation, $checkLengthDesc, $checkLengthType, $checkLengthResult);
                if (!$checkLengthResult) {
                    $subcomponentError = true;
                    $subcomponentComments .= $checkLengthDesc . " ";
                }
            }

            // check table
            if ($subComponentDef["Table"] !== "" && isset($this->hl7tables[$subComponentDef["Table"]])) {
                if (!empty($this->hl7tables[$subComponentDef["Table"]]["elements"])) {
                    list($checkHL7tableResult, $checkHL7tableType, $checkHL7tableDesc) = $this->checkHL7table($subComponentDef["Table"], $subcomponentValue, "SubComponent", "'".$subComponentDef["LongName"] . "' (" . $subComponentDef["Name"] . ")");
                    $this->addTestReport($currentLocation, $checkHL7tableDesc, $checkHL7tableType, $checkHL7tableResult);
                    if (!$checkHL7tableResult) {
                        $subcomponentError = true;
                    }
                    $subcomponentComments .= $checkHL7tableDesc . " ";
                }
            }
            // subcomponent
            $subComponentArray = array(
                "Type" => "subcomponent",
                "Name" => $subComponentDef["Name"],
                "LocName" => $currentLocation,
                "LongName" => $subComponentDef["LongName"],
                "Datatype" => $subComponentDef["Type"],
                "hasError" => $subcomponentError,
                "comments" => $subcomponentComments,
                "value" => $subcomponentValue,
            );
        } else {
            if (isset($this->msgParse[$this->segmentLocation][$this->segmentName][$this->fieldLocation][$this->fieldrepeat][$this->componentLocation][$this->subcomponentLocation])) {
                $subComponentArray = array(
                    "Type" => "subcomponent",
                    "Name" => $subComponentDef["Name"],
                    "LocName" => $currentLocation,
                    "LongName" => $subComponentDef["LongName"],
                    "Datatype" => $subComponentDef["Type"],
                    "hasError" => $subcomponentError,
                    "comments" => $subcomponentComments,
                    "value" => "",
                );
            }
        }
        $this->addValidationReport("SubComponent", $currentLocation, $subComponentDef["Name"], $subComponentDef["LongName"], $subComponentDef["Usage"], "[". $subComponentDef["Min"] . ".." . $subComponentDef["Max"] . "]", $subComponentDef["Type"], $subComponentDef["maxLength"], "", "", $subComponentDef["Table"], "", "", $subcomponentValue, $subcomponentExists, $subcomponentError, "", trim($subcomponentComments));
        return $subComponentArray;
    }





    /**
     * Check if segment name exists further in the profile
     * 
     * @param string $segmentName
     * @return bool
     */
    private function checkSegmentNameInProfileStructure($segmentName) {
        $profileNextSegmentsNames = array();
        for ($i=$this->profileSegmentLocation+1; $i < count($this->profileSegmentNames); $i++) {
            $profileNextSegmentsNames[] = $this->profileSegmentNames[$i];
        }
        return (in_array($segmentName, $profileNextSegmentsNames) ? true : false);
    }

    /**
     * Check Group/Segment/Field/Component/SubComponent Usage
     * 
     * @param string $elementUsage
     * @param bool $elementExists
     * @param string $elementType
     * @param string $elementName
     * @return array [bool $result, string $type, string $description]
     */
    private function checkUsage($elementUsage = "", $elementExists = false, $elementType = "", $elementName = "") {
        $result = null;
        $type = "";
        $description = "";
        if ($elementUsage == "R" && !$elementExists) {
            $description = "$elementType $elementName is required.";
            $type = "Required element";
            $result = false;
        } else if ($elementUsage == "X" && $elementExists) {
            $description = "$elementType $elementName is not allowed.";
            $type = "Element not allowed";
            $result = false;
        } else if ($elementUsage == "C" && $elementExists) {
            $description = "$elementType $elementName optionality is set as 'conditional'. Refer to the specification to check the optionality which applies in the context of this message.";
            $type = "Conditional";
            $result = true;
        } else if ($elementExists) {
            $description = "$elementType $elementName usage is $elementUsage.";
            $result = true;
            $type = "Usage";
        } else {
            $description = "$elementType $elementName usage is $elementUsage.";
            $result = true;
            $type = "Usage";
        }
        $this->addLogs("-$elementType- $type: $description");
        return array($result, $type, $description);
    }

    /**
     * Check Group/Segment/Field Cardinality
     * 
     * @param string $min
     * @param string $max
     * @param int $elementCnt
     * @param bool $elementExists
     * @param string $elementUsage
     * @param string $elementType
     * @param string $elementName
     * @return array [bool $result, string $type, string $description]
     */
    private function checkCardinality($min = "0", $max = "0", $elementCnt = 0, $elementExists = false, $elementUsage = "", $elementType = "", $elementName = "") {
        $result = null;
        $type = "";
        $description = "";
        $maxStr = $max;
        $max = ($maxStr === "*") ? INF : intval($maxStr);
        $min = intval($min);
        if (($elementCnt < $min) && $elementUsage == "R") {
            $description = "$elementType $elementName cardinality is [$min..$maxStr]. Must have at least $min repetition(s) (found $elementCnt).";
            $result = false;
            $type = "Cardinality";
        } else if ($elementCnt > $max) {
            $description = "$elementType $elementName cardinality is [$min..$maxStr]. Must have no more than $maxStr repetition(s) (found $elementCnt).";
            $result = false;
            $type = "Cardinality";
        } else if ($elementExists) {
            $description = "$elementType $elementName cardinality is [$min..$maxStr]. Found $elementCnt time(s).";
            $result = true;
            $type = "Cardinality";
        } else {
            $description = "$elementType $elementName cardinality is [$min..$maxStr]. Found $elementCnt time(s).";
            $result = true;
            $type = "Cardinality";
        }
        $this->addLogs("-$elementType- $type: $description");
        return array($result, $type, $description);
    }

    /**
     * Check Field/Component/SubComponent Length
     * 
     * @param int $length
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     * @return array [bool $result, string $type, string $description]
     */
    private function checkLength($length = 0, $elementValue = "", $elementType = "", $elementName = "") {
        $type = "Length";
        $result = (mb_strlen($elementValue) <= $length ? true : false);
        $description = "$elementType $elementName length " . (($result)? "does not exceed" : "exceeds") . " the length defined in the message profile ($length).";
        $this->addLogs("-$elementType- $type: $description");
        return array($result, $type, $description);
    }

    /**
     * Check Field/Component/SubComponent hl7table 
     * 
     * @param string $table
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     * @return array [bool $result, string $type, string $description]
     */
    private function checkHL7table($table="", $elementValue = "", $elementType = "", $elementName = "") {
        $type = "Table";
        //$result = (in_array($elementValue, $this->hl7tables[$table]["elements"]) ? true : false);
        $result = (in_array(strtoupper($elementValue), array_map('strtoupper', $this->hl7tables[$table]["elements"])) ? true : false);
        $description = "$elementType $elementName value ($elementValue) " . (($result)? "exists in" : "not in") . " table $table.";
        $this->addLogs("-$elementType- $type: $description");
        return array($result, $type, $description);
    }





    /**
     * Find segment definition in profile
     * 
     * @param  string $segmentName
     * @param  array  $segGroup
     * @return array  $segmentDef
     */
    private function findProfileSegmentDef($segmentName, $segGroup) {
        $segmentDef = array();
        foreach ($segGroup as $child) {
            if ($child["Type"] == "segment") {
                if ($segmentName == $child["Name"]) {
                    $segmentDef = $child;
                    break;
                }
            } else if ($child["Type"] == "group") {
                $def = $this->findProfileSegmentDef($segmentName, $child["segments"]);
                if (count($def) > 0) {
                    $segmentDef = $def;
                    break;
                }
            }
        }
        return $segmentDef;
    }

    /**
     * Get the list of segment names, in profile
     * 
     * @param  array $segGroup
     * @return array of segment names
     */
    private function getProfileListOfSegmentNames($segGroup) {
        $segmentNames = array();
        foreach ($segGroup as $child) {
            if ($child["Type"] == "segment") {
                $segmentNames[] = $child["Name"];
            } else if ($child["Type"] == "group") {
                $segmentNames = array_merge($segmentNames, $this->getProfileListOfSegmentNames($child["segments"]));
            }
        }
        return $segmentNames;
    }

    /**
     * Get the list of segment names, in a segGroup
     * 
     * @param  array $segGroup
     * @return array of segment names
     */
    private function getProfileListOfSegmentNamesInGroup($segGroup) {
        $segmentNames = array();
        foreach ($segGroup["segments"] as $child) {
            if ($child["Type"] == "segment") {
                $segmentNames[] = $child["Name"];
            } else if ($child["Type"] == "group") {
                $segmentNames = array_merge($segmentNames, $this->getProfileListOfSegmentNamesInGroup($child));
            }
        }
        return $segmentNames;
    }

    /**
     * Get the name of firsts segments in group and sub-goup
     * 
     * @param  array $segGroup
     * @return array of segment names
     */
    private function getProfileListOfFirstNamesOfSegmentsInGroup($segGroup) {
        $segmentsNames = array();
        for ($i=0; $i < count($segGroup["segments"]); $i++) {
            if ($i == 0 && $segGroup["segments"][$i]["Type"] == "segment") {
                $segmentsNames[] = $segGroup["segments"][$i]["Name"];
            } else if ($segGroup["segments"][$i]["Type"]== "group") {
                $segmentsNames = array_merge($segmentsNames, $this->getProfileListOfFirstNamesOfSegmentsInGroup($segGroup["segments"][$i]));
            }
        }
        return $segmentsNames;
    }





    /**
     * Return not expected segment to an array
     * 
     * @param string $segmentName
     * @param array  segmentDef
     */
    private function notExpectedSegmentToArray($segmentName, $segmentDef = array()) {
        $segmentArray = array();
        $description = "Segment '$segmentName' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
        if (count($segmentDef) == 0) {
            $segmentDef = $this->findProfileSegmentDef($segmentName, $this->profile);
        }

        if (count($segmentDef) > 0) {
            $segmentArray = $this->validateSegment($segmentDef);
            $segmentArray["hasError"] = true;
            $segmentArray["comments"] = $description;

        }
        return $segmentArray;
    }

    /**
     * Return not defined segment to an array
     * 
     * @param  array $segment
     * @return array $segmentArray
     */
    private function notDefinedSegmentToArray($segment) {
        $segmentName = key($segment);
        $description = "Segment '$segmentName' is not defined in the message profile.";
        $segmentArray = array(
            "Type" => "segment",
            "Name" => "$segmentName",
            "LongName" => "not defined segment",
            "hasError" => true,
            "comments" => $description,
            "fields" => array(),
        );

        foreach ($segment as $fields) {
            // for each field
            $fieldRepeats = array();
            for ($i=1; $i <= count($fields); $i++) {
                $fieldLocation = "$segmentName-$i";
                $fieldRepeats[$i] = $this->notDefinedFieldToArray($fields[$i], $segmentName, $i);
            }
            $segmentArray["fields"] = $fieldRepeats;
        }
        return $segmentArray;
    }

    private function notDefinedFieldToArray($field, $LocName, $fieldLocation) {
        $fieldRepeats = array();
        // for each field repeat
        for ($i=0; $i < count($field); $i++) {
            $fieldRepeats[$i] = array(
                "Type" => "field",
                "Name" => "$LocName.$fieldLocation",
                "LocName" => "$LocName-$fieldLocation",
                "LongName" => "",
                "Datatype" => "UNKNOWN",
                "hasError" => "",
                "comments" => "",
                "value" => $this->msgParseFieldRepeatToString($field[$i]),
            );
            if (is_array($field[$i])) {
                if (count($field[$i]) > 1) {
                    $components = array();
                    for ($j=1; $j <= count($field[$i]); $j++) {
                        $components[$j] = $this->notDefinedComponentToArray($field[$i][$j], "$LocName-$fieldLocation", $j);
                    }
                    $fieldRepeats[$i]["components"] = $components;
                }
            }
        }
        return $fieldRepeats;
    }

    private function notDefinedComponentToArray($component, $LocName, $componentLocation) {
        $componentAry = array(
            "Type" => "component",
            "Name" => "UNKNOWN.$componentLocation",
            "LocName" => "$LocName.$componentLocation",
            "LongName" => "",
            "Datatype" => "UNKNOWN",
            "hasError" => "",
            "comments" => "",
            "value" => $this->msgParseComponentToString($component),
        );
        if (is_array($component)) {
            if (count($component) > 1 ) {
                $subComponents = array();
                for ($i=1; $i <= count($component); $i++) {
                    $subComponents[$i] = $this->notDefinedSubComponentToArray($component[$i], "$LocName.$componentLocation", $i);
                }
                $componentAry["subcomponents"] = $subComponents;
            }
        }
        return $componentAry;
    }

    private function notDefinedSubComponentToArray($subComponent, $LocName, $subComponentLocation) {
        $subComponentAry = array(
            "Type" => "subcomponent",
            "Name" => "UNKNOWN.$subComponentLocation",
            "LocName" => "$LocName.$subComponentLocation",
            "LongName" => "",
            "Datatype" => "UNKNOWN",
            "hasError" => "",
            "comments" => "",
            "value" => $this->msgParseSubComponentToString($subComponent),
        );
        return $subComponentAry;
    }





    /**
     * Return msgData to string
     * 
     * @return string
     */
    public function msgDataToString() {
        // note: add something to highlight gaps
        $messageStr = "";
        if (!empty($this->msgData)) {
            $messageStr = $this->msgDataGroupToString($this->msgData);
        }
        return $messageStr;
    }

    public function msgDataGroupToString($group) {
        $groupStr = "";
        foreach($group["segments"] as $element) {
            if ($element["Type"] == "segment") {
                $segmentStr = $this->msgDataSegmentToString($element);
                $groupStr .= $segmentStr . "\r\n"; // CRLF
            } else if ($element["Type"] == "group") {
                $groupStr .= $this->msgDataGroupToString($element);
            }
        }
        return $groupStr;
    }

    public function msgDataSegmentToString($segment) {
        $segmentName = $segment["Name"];
        $segmentStr = $segmentName . $this->fieldSeparator;
        $start = 1;
        if ($segmentName === "MSH") {
            // MSH-1 is field separator
            $start = 2;
        }
        for ($i=$start; $i <= count($segment["fields"]); $i++) {
            $fieldStr = $this->msgDataFieldToString($segment["fields"][$i]);
            $segmentStr .= $fieldStr;
            if ($i < count($segment["fields"])) {
                $segmentStr .= $this->fieldSeparator;
            }
        }
        return $segmentStr;
    }

    public function msgDataFieldToString($field) {
        $fieldStr = "";
        // for field repeats
        for ($i=0; $i < count($field); $i++) {
            $fieldStr .= $this->msgDataFieldRepeatToString($field[$i]);
            if ($i < (count($field) - 1)) {
                $fieldStr .= $this->fieldRepeatSeparator;
            }
        }
        return $fieldStr;
    }

    public function msgDataFieldRepeatToString($fieldRepeat) {
        $fieldRepeatStr = "";
        if (isset($fieldRepeat["components"])) {
            // component
            for ($i=1; $i <= count($fieldRepeat["components"]); $i++) {
                $fieldRepeatStr .= $this->msgDataComponentToString($fieldRepeat["components"][$i]);
                if ($i < (count($fieldRepeat["components"]))) {
                    $fieldRepeatStr .= $this->componentSeparator;
                }
            }
        }
        else {
            $fieldRepeatStr = $fieldRepeat["value"];
        }
        return $fieldRepeatStr;
    }

    public function msgDataComponentToString($component) {
        $componentStr = "";
        if (isset($component["subcomponents"])) {
            // sub component
            for ($i=1; $i <= count($component["subcomponents"]); $i++) {
                $componentStr .= $this->msgDataSubComponentToString($component["subcomponents"][$i]);
                if ($i < count($component["subcomponents"])) {
                    $componentStr .= $this->subComponentSeparator;
                }
            }
        }
        else {
            $componentStr .= $component["value"];
        }
        return $componentStr;
    }

    public function msgDataSubComponentToString($subComponent) {
        return $subComponent["value"];
    }





    /**
     * Convert message data to XML representation of the HL7 message
     * 
     * @param bool $namespace
     * @return string xml
     */
    public function msgDataToXML($exportURN = true) {
        $xmlDoc = new \DOMDocument("1.0", "UTF-8" );
        $xmlDoc->preserveWhiteSpace = false;
        $xmlDoc->formatOutput = true;
        $xmlDoc->xmlStandalone = false;
        if (!empty($this->msgData)) {
            if ($exportURN) { 
                $rootNode = $xmlDoc->appendChild($xmlDoc->createElementNS('urn:hl7-org:v2xml', $this->messageStructID));
            } else {
                $rootNode = $xmlDoc->appendChild($xmlDoc->createElement($this->messageStructID));
            }
            $this->msgDataGroupToXML($this->msgData, $xmlDoc, $rootNode);
        }
        return $xmlDoc->saveXML();
    }

    private function msgDataGroupToXML($group, $xmlDoc, $xmlNode) {
        foreach($group["segments"] as $element) {
            if ($element["Type"] == "segment") {
                $this->msgDataSegmentToXML($element, $xmlDoc, $xmlNode);
            } else if ($element["Type"] == "group") {
                $segGrpNode = $xmlNode->appendChild($xmlDoc->createElement($element["Name"]));
                $this->msgDataGroupToXML($element, $xmlDoc, $segGrpNode);
            }
        }
    }

    private function msgDataSegmentToXML($segment, $xmlDoc, $xmlNode) {
        $segNode = $xmlNode->appendChild($xmlDoc->createElement($segment["Name"]));
        $start = 1;
        for ($i=$start; $i <= count($segment["fields"]); $i++) {
            $this->msgDataFieldToXML($segment["fields"][$i], $xmlDoc, $segNode);
        }
    }

    private function msgDataFieldToXML($field, $xmlDoc, $xmlNode) {
        // for field repeats
        for ($i=0; $i < count($field); $i++) {
            $this->msgDataFieldRepeatToXML($field[$i], $xmlDoc, $xmlNode);
        }
    }

    private function msgDataFieldRepeatToXML($fieldRepeat, $xmlDoc, $xmlNode) {
        if (isset($fieldRepeat["components"])) {
            $fieldNode = $xmlNode->appendChild($xmlDoc->createElement($fieldRepeat["Name"]));
            // component
            for ($i=1; $i <= count($fieldRepeat["components"]); $i++) {
                $this->msgDataComponentToXML($fieldRepeat["components"][$i], $xmlDoc, $fieldNode);
            }
        }
        else {
            $val = htmlspecialchars($fieldRepeat["value"], ENT_XML1, 'UTF-8');
            if (!empty($val)) {
                $fieldNode = $xmlNode->appendChild($xmlDoc->createElement($fieldRepeat["Name"], $val));
            }
        }
    }

    private function msgDataComponentToXML($component, $xmlDoc, $xmlNode) {
        if (isset($component["subcomponents"])) {
            // sub component
            $componentNode = $xmlNode->appendChild($xmlDoc->createElement($component["Name"]));
            for ($i=1; $i <= count($component["subcomponents"]); $i++) {
                $this->msgDataSubComponentToXML($component["subcomponents"][$i], $xmlDoc, $componentNode);
            }
        }
        else {
            $val = htmlspecialchars($component["value"], ENT_XML1, 'UTF-8');
            if (!empty($val)) {
                $componentNode = $xmlNode->appendChild($xmlDoc->createElement($component["Name"], $val));
            }
        }
    }

    private function msgDataSubComponentToXML($subComponent, $xmlDoc, $xmlNode) {
        $val = htmlspecialchars($subComponent["value"], ENT_XML1, 'UTF-8');
        if (!empty($val)) {
            $subComponentNode = $xmlNode->appendChild($xmlDoc->createElement($subComponent["Name"], $val));
        }
    }

}
