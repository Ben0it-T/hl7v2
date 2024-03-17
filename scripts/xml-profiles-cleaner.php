<?php
/**
 * Clean xml profiles
 * Remove extra line breaks in ImpNote and SubComponent.
 * Formats output with indentation and extra space.
 */

require_once("config.php");

// config
$dir = $xmlProfilesCleaner["inputDir"];
$files = scandir($dir, SCANDIR_SORT_ASCENDING);

foreach ($files as $file) {
    if (is_file($dir . "/" . $file)) {
        if (substr($file, -4) == ".xml") { 
            $xmlDoc = new DOMDocument();
            $xmlDoc->preserveWhiteSpace = false;
            $xmlDoc->formatOutput = true;
            $xmlDoc->load($dir . "/" . $file);
            $xpath = new DOMXpath($xmlDoc);

            if ($xmlDoc->getElementsByTagName("HL7v2xStaticDef")->length == 1) {
                // Profile
                $HL7v2xStaticDef = $xmlDoc->getElementsByTagName("HL7v2xStaticDef")->item(0);
                $MsgType = sprintf("%s", $HL7v2xStaticDef->getAttribute("MsgType"));
                $EventType = sprintf("%s", $HL7v2xStaticDef->getAttribute("EventType"));
                $MsgStructID = sprintf("%s", $HL7v2xStaticDef->getAttribute("MsgStructID"));
                echo "- $file ($MsgType^$EventType^$MsgStructID)<br/>";
                
                // clean ImpNote
                $elements = $xpath->query('//ImpNote');
                foreach ($elements as $entry) { 
                    $str = sprintf("%s", $entry->nodeValue);
                    $str = preg_replace("/[\n\r]/", " ", $str);
                    $str = preg_replace('/\s+/', ' ', $str);
                    $entry->nodeValue = trim($str);
                }

                /*
                // clean SubComponent
                $elements = $xpath->query('//SubComponent');
                foreach ($elements as $entry) { 
                    $isEmpty = ($entry->childNodes->length) === 0 ? true : false;
                    if (!$isEmpty) {
                        foreach ($entry->childNodes as $node) {
                            if ($node->nodeType === 3) {
                                $node->nodeValue = '';
                            }
                        }
                    }
                }
                */
            }
            else {
                // Table
                echo "- $file<br/>";
            }
            $xmlDoc->save($dir . "/" . $file);
        }
    }
}
echo "Done.";
