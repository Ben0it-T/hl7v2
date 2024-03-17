<?php
/**
 * Generate json schemas from xsd schemas
 * HL7’s Version 2.x xsd schemas : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 * 
 * - segments.xsd
 * - fields.xsd$
 * - datatypes.xsd
 * - structures ACK.xsd, ADT_XXX.xsd, SIU_XXX.xsd, ...
 */

require_once("config.php");

// config
$inputDir  = $xsdSchemasToJsonSchemas["inputDir"];
$outputDir = $xsdSchemasToJsonSchemas["outputDir"];
$todoList  = $xsdSchemasToJsonSchemas["todoList"];

if ($inputDir == $outputDir) {
    echo "Error: inputDir and outputDir must be different.";
    exit;
}

/**
 * Load xsd schemas
 * 
 * @param string $filename
 * @return DOMDocument object  
 * 
 */
function load_xsd_schemas($filename) {
    global $inputDir;
    if (file_exists($inputDir . "/" . $filename) && is_file($inputDir . "/" . $filename)) {
        $xmlDoc = new DOMDocument();
        $xmlDoc->preserveWhiteSpace = false;
        if ($xmlDoc->load($inputDir . "/" . $filename)) {
            return $xmlDoc;
        }
        else {
            echo "Error: fail to load $filename.";
        }
    }
    else {
        echo "Error: $filename does not exist or is not a regular file.";
        exit;
    }
}

/**
 * Write JSON representation to file
 * 
 * @param string name
 * @param array  $data
 * @param string $directory
 */
function save_json_schemas($name, $data, $directory) {
    if (!file_exists($directory)) {
        mkdir($directory);
    }
    file_put_contents($directory . "/" . $name . ".json", json_encode($data, JSON_PRETTY_PRINT));
    
}


// SEGMENTS
// filename: segments.xsd
if ($todoList["segments"]) {
    $segments = array();
    $segmentsXmlDoc = load_xsd_schemas("segments.xsd");
    $xpath = new DOMXpath($segmentsXmlDoc);
    $contextNode = $segmentsXmlDoc->getElementsByTagName('xsd:schema')->item(0);

    // xsd:element
    $nodes = $xpath->query('xsd:element[@name]', $contextNode);
    $elements = array();
    foreach ($nodes as $node) {
        $name = sprintf("%s", $node->getAttribute("name"));
        $type = sprintf("%s", $node->getAttribute("type"));
        $elements[$type] = $name;
    }

    // xsd:complexType
    $cnt = 0;
    $nodes = $xpath->query('xsd:complexType[@name]', $contextNode);
    foreach ($nodes as $node) {
        $name = $elements[$node->getAttribute("name")];
        $data = array();
        if (strlen($name) == 3 && count($node->getElementsByTagName('sequence')) > 0) {
            $cnt++;
            $sequence = $node->getElementsByTagName('sequence')->item(0);
            $fields = array();
            foreach ($sequence->getElementsByTagName('element') as $elmt) {
                $ref = sprintf("%s", $elmt->getAttribute("ref"));
                $minOccurs = sprintf("%s", $elmt->getAttribute("minOccurs"));
                $maxOccurs = sprintf("%s", $elmt->getAttribute("maxOccurs"));
                $fields[] = array(
                    "field" => $ref,
                    "minOccurs" => $minOccurs,
                    "maxOccurs" => $maxOccurs,
                );
            }
            $data["fields"] = $fields;
            $segments[$name] = $data;

            // Export segment in a json file
            // save_json_schemas($name, $data, $outputDir . "/segments");
        }
    }
    // Export all segments in a single json file
    ksort($segments);
    save_json_schemas("segments", $segments, $outputDir . "/segments");
    echo "- Segments: " . $cnt . ".<br/>";
}


// FIELDS
// filename: fields.xsd
if ($todoList["fields"]) {
    $fields = array();
    $fieldsXmlDoc = load_xsd_schemas("fields.xsd");
    $xpath = new DOMXpath($fieldsXmlDoc);
    $contextNode = $fieldsXmlDoc->getElementsByTagName('xsd:schema')->item(0);

    // xsd:element
    $nodes = $xpath->query('xsd:element[@name]', $contextNode);
    $elements = array();
    foreach ($nodes as $node) {
        $name = sprintf("%s", $node->getAttribute("name"));
        $type = sprintf("%s", $node->getAttribute("type"));
        $elements[$type] = $name;
    }

    // xsd:complexType
    $nodes = $xpath->query('xsd:complexType[@name]', $contextNode);
    $attributes = array();
    foreach ($nodes as $node) {
        $type = sprintf("%s", $node->getAttribute("name"));
        $name = $elements[$type];
        $data = array();

        $attributeGroup = $xpath->query('xsd:complexType[@name="'.$type.'"]//xsd:attributeGroup[@ref]', $contextNode);
        if ($attributeGroup->length > 0) {
            $attributeName = sprintf("%s", $attributeGroup->item(0)->getAttribute("ref"));
            $attributes[$attributeName] = $name;
        } 
    }

    // xsd:attributeGroup
    $cnt = 0;
    $nodes = $xpath->query('xsd:attributeGroup[@name]', $contextNode);
    foreach ($nodes as $node) {
        $cnt++;
        $name = $attributes[$node->getAttribute("name")];
        $data = array(
            "Item" => "",
            "Type" => "",
            "Table" => "",
            "LongName" => "",
            "maxLength" => "",
        );
        foreach ($node->getElementsByTagName('attribute') as $elmt) {
            $attrName = sprintf("%s", $elmt->getAttribute("name"));
            $attrFixed = sprintf("%s", $elmt->getAttribute("fixed"));
            $data[$attrName] = $attrFixed;
        }
        $fields[$name] = $data;

        // Export field in a json file
        //save_json_schemas($name, $data, $outputDir . "/fields");
    }
    
    // Export all fields in a single json file
    ksort($fields);
    save_json_schemas("fields", $fields, $outputDir . "/fields");
    echo "- Fields: " . $cnt . ".<br/>";
}


// DATATYPES & COMPONENTS
// filename: datatypes.xsd
if ($todoList["dataTypes"]) {
    $dataTypes = array();
    $datatypesXmlDoc = load_xsd_schemas("datatypes.xsd");
    $xpath = new DOMXpath($datatypesXmlDoc);
    $contextNode = $datatypesXmlDoc->getElementsByTagName('xsd:schema')->item(0);
    $cnt = 0;

    // xsd:element
    $nodes = $xpath->query('xsd:element[@name]', $contextNode);
    $elements = array();
    foreach ($nodes as $node) {
        $name = sprintf("%s", $node->getAttribute("name"));
        $type = sprintf("%s", $node->getAttribute("type"));
        $elements[$type] = $name;
    }

    // xsd:simpleType (primitives datatypes)
    $nodes = $xpath->query('xsd:simpleType[@name]', $contextNode);
    $data = array("dataType" => "STRING");
    foreach ($nodes as $node) {
        $cnt++;
        $name = sprintf("%s", $node->getAttribute("name"));
        $dataTypes[$name] = $data;

        // Export dataType in a json file
        //save_json_schemas($name, $data, $outputDir . "/dataTypes");
    }

    // xsd:complexType (xsd:complexType name=)
    $nodes = $xpath->query('xsd:complexType[@name]', $contextNode);
    $componentsRef = array();
    foreach ($nodes as $node) {
        $name = sprintf("%s", $node->getAttribute("name"));
        
        // primitive datatype extension escapeType/varies
        if (in_array($name, array("escapeType", "varies"))) {
            $cnt++;
            $data = array("dataType" => "STRING");
            $dataTypes[$name] = $data;

            // Export dataType in a json file
            //save_json_schemas($name, $data, $outputDir . "/dataTypes");
            continue;
        }

        // primitive datatypes
        $primitiveDatatypes = $xpath->query('xsd:complexType[@name="'.$name.'"]/xsd:sequence/xsd:element[@name]', $contextNode);
        if ($primitiveDatatypes->length ==1) {
            $cnt++;
            $data = array("dataType" => "STRING");
            $dataTypes[$name] = $data;

            // Export dataType in a json file
            //save_json_schemas($name, $data, $outputDir . "/dataTypes");
            continue;
        }
        else if ($primitiveDatatypes->length > 1) {
            echo "Need to check <xsd:complexType name=\"".$name."\" ...<br/>";
        }

        // composite datatypes
        $compositeDatatypes = $xpath->query('xsd:complexType[@name="'.$name.'"]/xsd:sequence/xsd:element[@ref]', $contextNode);
        if ($compositeDatatypes->length > 0) {
            $cnt++;
            $data = array();
            $components = array();
            foreach ($compositeDatatypes as $elmt) {
                $ref = sprintf("%s", $elmt->getAttribute("ref"));
                $minOccurs = sprintf("%s", $elmt->getAttribute("minOccurs"));
                $maxOccurs = sprintf("%s", $elmt->getAttribute("maxOccurs"));
                $components[] = array(
                    "dataType" => $ref,
                    "minOccurs" => $minOccurs,
                    "maxOccurs" => $maxOccurs,
                );
            }
            $data["components"] = $components;
            $dataTypes[$name] = $data;

            // Export dataType in a json file
            //save_json_schemas($name, $data, $outputDir . "/dataTypes");
        }

        // components attributeGroup ref
        if (isset($elements[$name])) {
            $attributeGroup = $xpath->query('xsd:complexType[@name="'.$name.'"]//xsd:attributeGroup[@ref]', $contextNode);
            if ($attributeGroup->length > 0) {
                $attributeName = sprintf("%s", $attributeGroup->item(0)->getAttribute("ref"));
                $componentsRef[$attributeName] = $name;
            }
        }
    }

    // xsd:attributeGroup
    $nodes = $xpath->query('xsd:attributeGroup[@name]', $contextNode);
    foreach ($nodes as $node) {
        if (isset($componentsRef[$node->getAttribute("name")])) {
            $ref = $componentsRef[$node->getAttribute("name")];
            if (isset($elements[$ref])) {
                $cnt++;
                $name = $elements[$ref];
                $data = array(
                    "Type" => "",
                    "Table" => "",
                    "LongName" => "",
                    "maxLength" => "",
                );
                foreach ($node->getElementsByTagName('attribute') as $elmt) {
                    $attrName = sprintf("%s", $elmt->getAttribute("name"));
                    $attrFixed = sprintf("%s", $elmt->getAttribute("fixed"));
                    $data[$attrName] = $attrFixed;
                }
                $dataTypes[$name] = $data;

                // Export dataType in a json file
                //save_json_schemas($name, $data, $outputDir . "/dataTypes");
            }
        }
    }
    // Export all dataTypes in a single json file
    ksort($dataTypes);
    save_json_schemas("dataTypes", $dataTypes, $outputDir . "/dataTypes");
    echo "- Datatypes & Components: " . $cnt . ".<br/>";
}


/**
 * STRUCTURES
 * filenames: ACK.xsd, ADT_XXX.xsd, SIU_XXX.xsd
 */
if ($todoList["structures"]) {
    $cnt = 0;
    $files = scandir($inputDir, SCANDIR_SORT_ASCENDING);
    foreach ($files as $file) {
        if (is_file($inputDir . "/" . $file)) {
            $ignore = array("batch.xsd", "datatypes.xsd", "fields.xsd", "messages.xsd", "segments.xsd");
            if (substr($file, -4) == ".xsd" && !in_array($file, $ignore)) {
                $cnt++;
                $structureName = substr($file, 0, -4);
                $structureXmlDoc = load_xsd_schemas($file);
                $xpath = new DOMXpath($structureXmlDoc);
                $contextNode = $structureXmlDoc->getElementsByTagName('xsd:schema')->item(0);
                
                // xsd:element
                $nodes = $xpath->query('xsd:element[@name]', $contextNode);
                $elements = array();
                foreach ($nodes as $node) {
                    $name = sprintf("%s", $node->getAttribute("name"));
                    $type = sprintf("%s", $node->getAttribute("type"));
                    $elements[$type] = $name;
                }

                // xsd:complexType
                $nodes = $xpath->query('xsd:complexType[@name]', $contextNode);
                $structureArray = array();
                foreach ($nodes as $node) {
                    $fullname = $node->getAttribute("name");
                    $name = $elements[$fullname];
                    $nameParts = explode(".", $name);
                    $groupName = (count($nameParts) > 1) ? $nameParts[1] : $name;
                    $structureArray[$groupName] = array("elements" => array());

                    $elmts = $xpath->query('xsd:complexType[@name="'.$fullname.'"]//xsd:element[@ref]', $contextNode);
                    if ($elmts->length > 0) {
                        foreach ($elmts as $elmt) {
                            $ref = sprintf("%s", $elmt->getAttribute("ref"));
                            $refParts = explode(".", $ref);
                            $theRef = (count($refParts) > 1) ? $refParts[1] : $ref;
                            $minOccurs = sprintf("%s", $elmt->getAttribute("minOccurs"));
                            $maxOccurs = sprintf("%s", $elmt->getAttribute("maxOccurs"));
                            $type = (strlen($ref) > 3) ? "group" : "segment";
                            $structureArray[$groupName]["elements"][] = array(
                                $type => $theRef,
                                "minOccurs" => $minOccurs,
                                "maxOccurs" => $maxOccurs,
                            );
                        }
                    }
                }
                save_json_schemas($structureName, $structureArray, $outputDir . "/structures");
            }
        }
    }
    echo "- Structures: " . $cnt . ".<br/>";
}
echo "Done.";
