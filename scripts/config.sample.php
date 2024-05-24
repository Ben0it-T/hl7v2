<?php

// xml-profiles-cleaner
$xmlProfilesCleaner = array(
    "inputDir"  => "../profiles/gazelle",
);

// xsd-schemas-cleaner
$xsdSchemasCleaner = array(
    "inputDir" => "../schemas/hl7-xml-v2.5"
    //"inputDir" => "../schemas/hl7v2xsd/2.5"
);

// xsd-schemas-to-json-schemas
$xsdSchemasToJsonSchemas = array(
    "inputDir"  => "../schemas/hl7-xml-v2.5",
    "outputDir" => "../schemas/json-2.5-org",

    //"inputDir"  => "../schemas/hl7v2xsd/2.5",
    //"outputDir" => "../schemas/json-2.5-sun",

    "todoList"  => array(
        "dataTypes" => true,
        "fields" => true,
        "segments" => true,
        "structures" => true,
    )
);

// json-schemas-update-from-old-schemas
$jsonSchemasUpdateFromOldSchemas = array(
    "sourceDir" => "../schemas/json-2.5-org",
    "targetDir" => "../schemas/json-2.5-sun",
    "todoList"  => array(
        "dataTypes" => true,
        "fields" => true,
        "segments" => true,
        "structures" => true,
    )
);

// json-schemas-update-from-appendix-a
$jsonSchemasUpdateFromAppendixA = array(
    "appendixDir" => "../schemas/appendixA-2.5",
    "jsonDir"     => "../schemas/json-2.5-sun",
    "todoList"    => array(
        "dataElements" => true,
        "segments" => true,
    )
);

// uptade-schemas-to-IHE-PAM-FR
$uptadeSchemasToIHEPAMFR = array(
    "inputDir"  => "../schemas/json-2.5",
    "outputDir" => "../schemas/json-2.5-IHEPAMFR",
);

// create-json-profile
$createJsonProfile = array(
    "inputDir"  => "../schemas/json-2.5-IHEPAMFR",
    "outputDir" => "../profiles/json-2.5",
    "msgType"   => array("ACK", "ADT", "SIU"),
    "ignoreEvents" => array("A08"),
    "fieldsConstraints" => true,
    "indent" => 2,
    "pretty" => true
);

// create-xml-profile
$createXmlProfile = array(
    "HL7Version" => "2.5",
    "inputDir"   => "../schemas/json-2.5-IHEPAMFR",
    "outputDir"  => "../profiles/xml-2.5",
    "msgType"    => array("ACK", "ADT", "SIU"),
    "ignoreEvents" => array("A08"),
    "fieldsConstraints" => true,
);
