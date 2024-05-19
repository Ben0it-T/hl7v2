<?php
/**
 * Update json schemas from Appendix A
 * HL7 Messaging Standard Version 2.x : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 * - Data element names (fields)
 * - Segments
 */

require_once("config.php");

// config
$appendixDir = $jsonSchemasUpdateFromAppendixA["appendixDir"];
$jsonDir     = $jsonSchemasUpdateFromAppendixA["jsonDir"];
$todoList    = $jsonSchemasUpdateFromAppendixA["todoList"];


/**
 * Load csv file
 * 
 * @param string $filename
 * @return array $data
 */
function load_csv($filename) {
    $separator = ";";
    $enclosure = '"';
    $data = array();
    if (file_exists($filename) && is_file($filename)) {
        if (($handle = fopen($filename, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, $separator, $enclosure)) !== FALSE) {
                $data[] = $row;
            }
            fclose($handle);
            return $data;
        }
    }
    else {
        echo "Error: $filename does not exist or is not a regular file.";
        exit;
    }
}


/**
 * Write JSON schemas to file
 * 
 * @param string $filename
 * @param array  $data
 * @param string $directory
 */
function save_json_schemas($filename, $data, $directory) {
    if (!file_exists($directory)) {
        mkdir($directory);
    }
    file_put_contents($directory . "/" . $filename, json_encode($data, JSON_PRETTY_PRINT));
}


// Data element names (fields)
if ($todoList["dataElements"]) {
    // load Appendix A data element names (fields)
    $rows = load_csv($appendixDir . "/data-element-names.csv");
    $colSeg = array_column($rows, 2);
    $colSeq = array_column($rows, 3);
    array_multisort($colSeg, SORT_ASC, $colSeq, SORT_ASC, $rows);
    
    // load json fields shemas
    $jsonString = file_get_contents($jsonDir . "/fields/fields.json");
    $dataFields = json_decode($jsonString, true);

    $cnt = 0;
    foreach ($rows as $row) {
        list($description, $item, $segmt, $seq, $len, $dt, $rep, $table, $chapter) = $row;
        if ($segmt == "Seg" && $seq == "Seq#") {
            continue;
        }
        $fieldName = trim($segmt) . "." . trim($seq);
        if (isset($dataFields[$fieldName])) {
            $cnt++;
            // Update if needed
            if (trim($dt) != "" && strtolower(trim(substr($dt,0,5))) != "varie" && $dataFields[$fieldName]["Type"] != trim($dt)) {
                $dataFields[$fieldName]["Type"] = trim($dt);
            }
            if (trim($item) != "" && $dataFields[$fieldName]["Item"] != trim($item)) {
               //$dataFields[$fieldName]["Item"] = trim($item);
               $dataFields[$fieldName]["Item"] = sprintf('%05d', trim($item));
            }
            if (trim($table) != "" && $dataFields[$fieldName]["Table"] != "HL7".trim($table)) {
                //$dataFields[$fieldName]["Table"] = "HL7".trim($table);
                $dataFields[$fieldName]["Table"] = "HL7" . (sprintf('%05d', trim($table)));
            }
            if (trim($len) != "" && $dataFields[$fieldName]["maxLength"] != trim($len)) {
                $dataFields[$fieldName]["maxLength"] = trim($len);
            }
            // Add chapter
            $dataFields[$fieldName]["Chapter"] = trim($chapter);
        }
        else {
            echo "Error: field $fieldName not found.<br/>";
        }
    }
    save_json_schemas("fields.json", $dataFields, $jsonDir . "/fields/");
    echo "- Fields: " . $cnt . ".<br/>";
}


// Segments
if ($todoList["segments"]) {
    // load Appendix A segments
    $rows = load_csv($appendixDir . "/segments.csv");

    // load json fields shemas
    $jsonString = file_get_contents($jsonDir . "/segments/segments.json");
    $dataSegments = json_decode($jsonString, true);

    $cnt = 0;
    foreach ($rows as $row) {
        list($segment, $description, $chapter) = $row;
        if ($segment == "Segment" && $description == "Description") {
            continue;
        }
        $segmentName = trim($segment);
        if (isset($dataSegments[$segmentName])) {
            $cnt++;
            $dataSegments[$segmentName]["LongName"] = trim($description);
            $dataSegments[$segmentName]["Chapter"] = trim($chapter);
            //save_json_schemas($jsonFile, $jsonData, $jsonDir . "/segments/");
        }
        else {
            echo "- Info: segment $segmentName not found.<br/>";
        }
    }
    save_json_schemas("segments.json", $dataSegments, $jsonDir . "/segments/");
    echo "- Segments: " . $cnt . ".<br/>";
}
echo "Done.";
