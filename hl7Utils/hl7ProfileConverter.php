<?php
declare(strict_types=1);
namespace HL7;

/**
 * Simple profile converter
 * Converts XML profile to JSON profile
 * 
 */
class ProfileConverter {
    
    public int  $indent;
    public bool $pretty;
    
    /**
     * Create a new instance of the HL7 Parser
     * 
     * @param bool $pretty
     * @param int  $indent indentation level
     */
    public function __construct($pretty = false, $indent = 4) {
        $this->setPretty($pretty);
        $this->setIndentationLevel($indent);
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
     * Concert XML profile to JSON profile
     * 
     * @param object $xmlProfile (simplexml object)
     * @return json string
     */
    public function toJson($xmlProfile) {
        $data = array();
        foreach ($xmlProfile->HL7v2xStaticDef->children() as $child) {
            $childName = $child->getName();
            if ($childName == "Segment") {
                $data[] =  $this->addSegment($child);
            }
            else if ($childName == "SegGroup") {
                $data[] = $this->addSegGroup($child);
            } 
        }

        if ($this->pretty) {
            if ($this->indent == 4) {
                $json = json_encode($data, JSON_PRETTY_PRINT);
            } else {
                $json = preg_replace_callback(
                    '/^(?: {4})+/m',
                    function($m) {
                        return str_repeat(' ', $this->indent * (strlen($m[0]) / 4));
                    },
                    json_encode($data, JSON_PRETTY_PRINT)
                );
            }
        } else {
            $json = json_encode($data);
        }

        return $json;
    }

    /**
     * Add SegGroup
     * 
     * @param object $SegGroup
     * @return array
     */
    private function addSegGroup($SegGroup) {
        $attributes = $this->getSegGroupAttr($SegGroup);
        $group = array();
        foreach ($SegGroup->children() as $child) {
            $childName = $child->getName();
            if ($childName == "Segment") {
                $group[] = $this->addSegment($child);
            }
            else if ($childName == "SegGroup") {
                $group[] = $this->addSegGroup($child);
            }
        }
        $segGroup = array_merge($attributes, array("segments" => $group));
        return $segGroup;
    }

    /**
     * Add Segment
     * 
     * @param object $Segment
     * @return array
     */
    private function addSegment($Segment) {
        $attributes = $this->getSegmentAttr($Segment);
        $fields = array();
        $location = 0;
        foreach ($Segment->Field as $Field) {
            $location++;
            $fields[] = $this->addField($Field, $attributes["Name"], $location);
        }
        return array_merge($attributes, array("fields" =>  $fields));
    }

    /**
     * Add Field
     * 
     * @param object $Field
     * @param string $name
     * @param integer $location
     * @return array
     */
    private function addField($Field, $name="", $location=0) {
        $attributes = $this->getFieldAttributes($Field, $name, $location);
        // If dataType has Components
        if (isset($Field->Component)) {
            $components = array();
            $cnt=0;
            foreach ($Field->Component as $Component) {
                $cnt++;
                $components[] = $this->addComponent($Component, $attributes["Datatype"], $cnt);
            }
            $theField = array_merge($attributes, array("components" => $components));
        } else {
            $theField = $attributes;
        }
        return $theField;
    }

    /**
     * Add Component
     * 
     * @param object $Component
     * @param string $datatype
     * @param integer $location
     * @return array
     */
    private function addComponent($Component, $datatype="", $location=0) {
        $attributes = $this->getComponentAttributes($Component, $datatype, $location);
        // If dataType has SubComponents
        if (isset($Component->SubComponent)) {
            $subcomponents = array();
            $cnt=0;
            foreach ($Component->SubComponent as $SubComponent) {
                $cnt++;
                $subcomponents[] = $this->addSubComponent($SubComponent, $attributes["Type"], $cnt);
            }
            $theComponent = array_merge($attributes, array("components" => $subcomponents));
        } else {
            $theComponent = $attributes;
        }
        return $theComponent;
    }

    /**
     * Add SubComponent
     * 
     * @param object $SubComponent
     * @param string $datatype
     * @param integer $location
     * @return array
     */
    private function addSubComponent($SubComponent, $datatype="", $location=0) {
        $attributes = $this->getComponentAttributes($SubComponent, $datatype, $location);
        return $attributes;
    }

    /**
     * Get SegGroup attributes
     * 
     * @param object $SegGroup
     * @return array
     */
    private function getSegGroupAttr($SegGroup) {
        return array(
            "Type" => "group",
            "Name" => (isset($SegGroup['Name']) ? sprintf("%s", $SegGroup['Name']) : ""),
            "Usage" => (isset($SegGroup['Usage']) ? sprintf("%s", $SegGroup['Usage']) : ""),
            "Min" => (isset($SegGroup['Min']) ? sprintf("%s", $SegGroup['Min']) : "0"),
            "Max" => (isset($SegGroup['Max']) ? sprintf("%s", $SegGroup['Max']) : "0"),
            "LongName" => (isset($SegGroup['LongName']) ? sprintf("%s", $SegGroup['LongName']) : ""),
        );
    }

    /**
     * Get Segment attributes
     * 
     * @param object $Segment
     * @return array
     */
    private function getSegmentAttr($Segment) {
        return array(
            "Type" => "segment",
            "Name" => (isset($Segment['Name']) ? sprintf("%s", $Segment['Name']) : ""),
            "Usage" => (isset($Segment['Usage']) ? sprintf("%s", $Segment['Usage']) : ""),
            "Min" => (isset($Segment['Min']) ? sprintf("%s", $Segment['Min']) : "0"),
            "Max" => (isset($Segment['Max']) ? sprintf("%s", $Segment['Max']) : "0"),
            "LongName" => (isset($Segment['LongName']) ? sprintf("%s", $Segment['LongName']) : ""),
            "Chapter" => (isset($Segment->Reference) ? sprintf("%s", $Segment->Reference) : ""),
        );
    }

    /**
     * Get Field attributes
     * 
     * @param array $Field
     * @param string $name
     * @param integer $location
     * @return array
     */
    private function getFieldAttributes($Field, $name="", $location=0) {
        return array(
            "Name" => "$name.$location",
            "Usage" => (isset($Field['Usage']) ? sprintf("%s", $Field['Usage']) : ""),
            "Min" => (isset($Field['Min']) ? sprintf("%s", $Field['Min']) : "0"),
            "Max" => (isset($Field['Max']) ? sprintf("%s", $Field['Max']) : "0"),
            "Item" => (isset($Field['ItemNo']) ? sprintf("%s", $Field['ItemNo']) : ""),
            "Datatype" => (isset($Field['Datatype']) ? sprintf("%s", $Field['Datatype']) : ""),
            "Length" => (isset($Field['Length']) ? sprintf("%s", $Field['Length']) : ""),
            "Table" => (isset($Field['Table']) ? sprintf("%s", $Field['Table']) : ""),
            "LongName" => (isset($Field['Name']) ? sprintf("%s", $Field['Name']) : ""),
            "Chapter" => (isset($Field->Reference) ? sprintf("%s", $Field->Reference) : ""),
        );
    }

    /**
     * Get Component & SubComponent attributes
     * 
     * @param array $Component
     * @param string $name
     * @param integer $location
     * @return array
     */
    private function getComponentAttributes($Component, $name="", $location=0) {
        // To do : Min Max
        $usage = (isset($Component['Usage']) ? sprintf("%s", $Component['Usage']) : "");
        switch ($usage) {
            case 'R':
                $min = "1";
                $max = "1";
                break;
            
            case 'X':
                $min = "0";
                $max = "0";
                break;

            default:
                $min = "0";
                $max = "1";
                break;
        }
        return array(
            "Name" => "$name.$location",
            "Usage" => $usage,
            "Min" => $min,
            "Max" => $max,
            "Type" => (isset($Component['Datatype']) ? sprintf("%s", $Component['Datatype']) : ""),
            "Table" => (isset($Component['Table']) ? sprintf("%s", $Component['Table']) : ""),
            "LongName" => (isset($Component['Name']) ? sprintf("%s", $Component['Name']) : ""),
            "maxLength" => (isset($Component['Length']) ? sprintf("%s", $Component['Length']) : ""),
        );
    }
}
