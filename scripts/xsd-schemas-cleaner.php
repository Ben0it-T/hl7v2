<?php
/**
 * Clean xsd schemas
 * Formats output with indentation and extra space.
 * HL7’s Version 2.x xsd schemas : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 */

require_once("config.php");

// config
$dir = $xsdSchemasCleaner["inputDir"];

// clean xsd schemas
$files = scandir($dir, SCANDIR_SORT_ASCENDING);
foreach ($files as $file) {
    if (is_file($dir . "/" . $file)) {
        if (substr($file, -4) == ".xsd") {
            echo "- $file" . "<br/>";
            $xmlDoc = new DOMDocument();
            $xmlDoc->preserveWhiteSpace = false;
            $xmlDoc->formatOutput = true;
            $xmlDoc->load($dir . "/" . $file);
            $xmlDoc->save($dir . "/" . $file);
        }
    }
}
echo "Done.";
