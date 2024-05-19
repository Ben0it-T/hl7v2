<?php
/**
 * Update json schemas
 * Only from HL7 v2.3.1, v2.4, v2.5, v2.5.1 messaging schemas to Sun_HL7v2xsd
 * 
 * dataTypes:
 *  - components: minOccurs, maxOccurs
 *  - datatypes: Table, LongName, maxLength
 *  - fields: Item, Table, LongName, maxLength
 *  - segments: minOccurs, maxOccurs
 *  - structures: minOccurs, maxOccurs
 */

require_once("config.php");

// config
$sourceDir = $jsonSchemasUpdateFromOldSchemas["sourceDir"];
$targetDir = $jsonSchemasUpdateFromOldSchemas["targetDir"];
$todoList  = $jsonSchemasUpdateFromOldSchemas["todoList"];

if ($sourceDir == $targetDir) {
    echo "Error: sourceDir and targetDir must be different.";
    exit;
}

// dataTypes
if ($todoList["dataTypes"]) {
    echo "<b>dataTypes:</b><br/>";
    $jsonSource = file_get_contents($sourceDir . "/dataTypes/dataTypes.json");
    $dataSource = json_decode($jsonSource, true);
    $jsonTarget = file_get_contents($targetDir . "/dataTypes/dataTypes.json");
    $dataTarget = json_decode($jsonTarget, true);

    $hasChanged = false;
    $changes = "";

    foreach ($dataSource as $dtKey => $dtVal) {
        if (isset($dtVal["components"])) {
            // components
            foreach ($dtVal["components"] as $component) {
                $dataType = $component["dataType"];
                $minOccurs = $component["minOccurs"];
                $maxOccurs = $component["maxOccurs"];
                if (isset($dataTarget[$dtKey])) {
                    for ($i=0; $i < count($dataTarget[$dtKey]["components"]); $i++) {
                        if ($dataTarget[$dtKey]["components"][$i]["dataType"] == $dataType) {
                            if ($dataTarget[$dtKey]["components"][$i]["minOccurs"] != $minOccurs) {
                                $hasChanged = true;
                                $changes .= $dataType . ": minOccurs (" . $dataTarget[$dtKey]["components"][$i]["minOccurs"] . " -> " . $minOccurs. ")<br/>";
                                $dataTarget[$dtKey]["components"][$i]["minOccurs"] = $minOccurs;
                            }
                            if ($dataTarget[$dtKey]["components"][$i]["maxOccurs"] != $maxOccurs) {
                                $hasChanged = true;
                                $changes .= $dataType . ": maxOccurs (" . $dataTarget[$dtKey]["components"][$i]["maxOccurs"] . " -> " . $maxOccurs. ")<br/>";
                                $dataTarget[$dtKey]["components"][$i]["maxOccurs"] = $maxOccurs;
                            }
                            break;
                        }
                    }
                }
            }
        }
        else {
            // datatypes
            foreach ($dtVal as $key => $val) {
                if (isset($dataTarget[$dtKey])) {
                    if ($val != "" && $dataTarget[$dtKey][$key] != $val) {
                        $hasChanged = true;
                        $changes .= $dtKey . ": " . $key . " (". $dataTarget[$dtKey][$key] . " -> " . $val . ")<br/>";
                        $dataTarget[$dtKey][$key] = $val;
                    }
                }
            }
        }
    }
    if ($hasChanged) {
        echo $changes . "<br/>";
        file_put_contents($targetDir . "/dataTypes/dataTypes.json", json_encode($dataTarget, JSON_PRETTY_PRINT));
    }
}


// fields
if( $todoList["fields"] ) {
    echo "<b>Fields:</b><br/>";
    $jsonSource = file_get_contents($sourceDir . "/fields/fields.json");
    $dataSource = json_decode($jsonSource, true);
    $jsonTarget = file_get_contents($targetDir . "/fields/fields.json");
    $dataTarget = json_decode($jsonTarget, true);

    $hasChanged = false;
    $changes = "";

    foreach ($dataSource as $fieldKey => $fieldVal) {
        //echo "$fieldKey <br/>";
        foreach ($fieldVal as $key => $val) {
            if ($val != "" && $dataTarget[$fieldKey][$key] != $val) {
                //echo "- $key<br>";
                $hasChanged = true;
                $changes .= $fieldKey . ": " . $key . " (". $dataTarget[$fieldKey][$key] . " -> " . $val . ")<br/>";
                $dataTarget[$fieldKey][$key] = $val;
            }
        }
    }
    if ($hasChanged) {
        echo $changes . "<br/>";
        file_put_contents($targetDir . "/fields/fields.json", json_encode($dataTarget, JSON_PRETTY_PRINT));
    }
}


// segments
if ($todoList["segments"]) {
    echo "<b>Segments:</b><br/>";
    $jsonSource = file_get_contents($sourceDir . "/segments/segments.json");
    $dataSource = json_decode($jsonSource, true);
    $jsonTarget = file_get_contents($targetDir . "/segments/segments.json");
    $dataTarget = json_decode($jsonTarget, true);

    $hasChanged = false;
    $changes = "";

    foreach ($dataSource as $segKey => $segVal) {
        if (isset($segVal["fields"])) {
            // fields
            foreach ($segVal["fields"] as $field) {
                $fieldName = $field["field"];
                $minOccurs = $field["minOccurs"];
                $maxOccurs = $field["maxOccurs"];
                for ($i=0; $i < count($dataTarget[$segKey]["fields"]); $i++) {
                    if ($dataTarget[$segKey]["fields"][$i]["field"] == $fieldName) {
                        if ($dataTarget[$segKey]["fields"][$i]["minOccurs"] != $minOccurs) {
                            $hasChanged = true;
                            $changes .= $fieldName . ": minOccurs (" . $dataTarget[$segKey]["fields"][$i]["minOccurs"] . " -> " . $minOccurs. ")<br/>";
                            $dataTarget[$segKey]["fields"][$i]["minOccurs"] = $minOccurs;
                        }
                        if ($dataTarget[$segKey]["fields"][$i]["maxOccurs"] != $maxOccurs) {
                            $hasChanged = true;
                            $changes .= $fieldName . ": maxOccurs (" . $dataTarget[$segKey]["fields"][$i]["maxOccurs"] . " -> " . $maxOccurs. ")<br/>";
                            $dataTarget[$segKey]["fields"][$i]["maxOccurs"] = $maxOccurs;
                        }
                        break;
                    }
                }
            }
        }
    }
    if ($hasChanged) {
        echo $changes . "<br/>";
        file_put_contents($targetDir . "/segments/segments.json", json_encode($dataTarget, JSON_PRETTY_PRINT));
    }
}

// structures
if ($todoList["structures"]) {
    echo "<b>Structures:</b><br/>";
    $files = scandir($sourceDir . "/structures", SCANDIR_SORT_ASCENDING);
    foreach ($files as $file ) {
        if (is_file($sourceDir . "/structures/" . $file) && is_file($targetDir . "/structures/" . $file)) {
            $hasChanged = false;
            $changes = "";
            $jsonSource = file_get_contents($sourceDir . "/structures/" . $file);
            $dataSource = json_decode($jsonSource, true);
            $jsonTarget = file_get_contents($targetDir . "/structures/" . $file);
            $dataTarget = json_decode($jsonTarget, true);

            foreach ($dataSource as $key => $group) {
                foreach ($group["elements"] as $element) {
                    $segType = (isset($element["segment"])) ? "segment" : "group";
                    $segVal = $element[$segType];
                    $minOccurs = $element["minOccurs"];
                    $maxOccurs = $element["maxOccurs"];
                    
                    if (isset($dataTarget[$key]["elements"])) {
                        for ($i=0; $i < count($dataTarget[$key]["elements"]); $i++) {
                            $theType = (isset($dataTarget[$key]["elements"][$i]["segment"])) ? "segment" : "group";
                            $theVal = $dataTarget[$key]["elements"][$i][$theType];
                            if ($segVal == $theVal) {
                                if ($dataTarget[$key]["elements"][$i]["minOccurs"] != $minOccurs) {
                                    $hasChanged = true;
                                    $changes .= $segVal . ": minOccurs (" . $dataTarget[$key]["elements"][$i]["minOccurs"] . " -> " . $minOccurs. ")<br/>";
                                    $dataTarget[$key]["elements"][$i]["minOccurs"] = $minOccurs;
                                }
                                if ($dataTarget[$key]["elements"][$i]["maxOccurs"] != $maxOccurs) {
                                    $hasChanged = true;
                                    $changes .= $segVal . ": maxOccurs (" . $dataTarget[$key]["elements"][$i]["maxOccurs"] . " -> " . $maxOccurs. ")<br/>";
                                    $dataTarget[$key]["elements"][$i]["maxOccurs"] = $maxOccurs;
                                }
                                break;
                            }
                        }
                    }
                }
            }
            if ($hasChanged) {
                echo "- $file : updated.<br/>";
                echo $changes . "<br/>";
                file_put_contents($targetDir . "/structures/" . $file, json_encode($dataTarget, JSON_PRETTY_PRINT));
            }
        }
    }
}
echo "Done.";
