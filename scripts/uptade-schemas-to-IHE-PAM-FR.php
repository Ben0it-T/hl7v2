<?php
/**
 * Update json schemas
 * From HL7 2.5 IHE PAM to HL7 2.5 IHE PAM FR 2.11.1
 */

require_once("config.php");

// config
$inputDir  = $uptadeSchemasToIHEPAMFR["inputDir"];
$outputDir = $uptadeSchemasToIHEPAMFR["outputDir"];

if ($inputDir == $outputDir) {
    echo "Error: inputDir and outputDir must be different.";
    exit;
}

/**
 * Load JSON structure schemas
 *
 * @param $filename
 * @return array $data
 */
function loadJsonSchemas($filename) {
    $data = array();
    if (file_exists($filename) && is_file($filename)) {
        $jsonStr = file_get_contents($filename);
        $data = json_decode($jsonStr, true);
    }
    return $data;
}

/**
 * Create directory, if needed
 *
 * @param $directory
 */
function createDirectory($directory) {
    if (!file_exists($directory)) {
        if (!mkdir($directory, 0777, true)) {
            die('Failed to create directories...');
        }
    }
}


// Load json schemas
$messageType = loadJsonSchemas($inputDir . "/messageType.json");
$eventDesc = loadJsonSchemas($inputDir . "/eventDesc.json");
$segmentsSchemas = loadJsonSchemas($inputDir . "/segments/segments.json");
$fieldsSchemas = loadJsonSchemas($inputDir . "/fields/fields.json");
$dataTypesSchemas = loadJsonSchemas($inputDir . "/dataTypes/dataTypes.json");
$structuresSchemas = array();

// Create output directories
createDirectory($outputDir . "/dataTypes");
createDirectory($outputDir . "/fields");
createDirectory($outputDir . "/segments");
createDirectory($outputDir . "/structures");


/**
 * Forbidden fields
 * -------------------------------------
 * Patient Race         : PID-10, NK1-35
 * Patient Religion     : PID-17, NK1-25
 * Patient Ethnic Group : PID-22, NK1-28
 */

// PID segment
foreach ($segmentsSchemas["PID"]["fields"] as $key => $field) {
    if (in_array($field["field"], array("PID.10", "PID.17", "PID.22"))) {
        $segmentsSchemas["PID"]["fields"][$key]["minOccurs"] = "0";
        $segmentsSchemas["PID"]["fields"][$key]["maxOccurs"] = "0";
        $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "X";
    }
}

// NK1 segment
foreach ($segmentsSchemas["NK1"]["fields"] as $key => $field) {
    if (in_array($field["field"], array("NK1.25", "NK1.28", "NK1.35"))) {
        $segmentsSchemas["NK1"]["fields"][$key]["minOccurs"] = "0";
        $segmentsSchemas["NK1"]["fields"][$key]["maxOccurs"] = "0";
        $segmentsSchemas["NK1"]["fields"][$key]["Usage"] = "X";
        break;
    }
}
echo "Forbidden fields: done.<br/>";



/**
 * Create Z-segments
 * -------------------------------------
 * ZFA : Statut DMP du patient
 * ZFP : Situation professionnelle
 * ZFV : Complément d'information sur la venue
 * ZFM : Mouvement PMSI
 * ZFD : Complément démographique
 * ZFS : Mode légal de soins en psychiatrie
 * ZBE : Action sur un mouvement (Movement segment)
 */

//
// ZFA : Statut DMP du patient
//
// Add segment
$ZFAsegment = array(
    "ZFA" => array(
        "fields" => array(
            array("field" => "ZFA.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.3",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.4",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFA.5",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFA.6",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFA.7", "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFA.8", "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFA.9",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.10", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.11", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFA.12", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
        ),
        "LongName" => "Statut DMP",
        "Chapter" => ""
    )
);
// Add fields
$ZFAfields = array(
    "ZFA.1"  => array("Item" => "", "Type" => "ID", "Table" => "IHE-ZFA-1", "LongName" => "Statut du DMP du patient", "maxLength" => "20", "Chapter" => ""),
    "ZFA.2"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil du statut du DMP", "maxLength" => "26", "Chapter" => ""),
    "ZFA.3"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fermeture du DMP du patient", "maxLength" => "26", "Chapter" => ""),
    "ZFA.4"  => array("Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Autorisation d'accès valide au DMP du patient pour l'établissement", "maxLength" => "1", "Chapter" => ""),
    "ZFA.5"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'état de l'autorisation d'accès au DMP du patient pour l'établissement", "maxLength" => "26", "Chapter" => ""),
    "ZFA.6"  => array("Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Opposition du patient à l'accès en mode bris de glace", "maxLength" => "1", "Chapter" => ""),
    "ZFA.7"  => array("Item" => "", "Type" => "ID", "Table" => "HL70136", "LongName" => "Opposition du patient à l'accès en mode centre de régulation", "maxLength" => "1", "Chapter" => ""),
    "ZFA.8"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'état des oppositions du patient", "maxLength" => "26", "Chapter" => ""),
    "ZFA.9"  => array("Item" => "", "Type" => "CWE", "Table" => "IHE-ZFA-9", "LongName" => "Information et opposition à l'alimentation", "maxLength" => "3", "Chapter" => ""),
    "ZFA.10" => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'information et opposition à l'alimentation", "maxLength" => "26", "Chapter" => ""),
    "ZFA.11" => array("Item" => "", "Type" => "CWE", "Table" => "IHE-ZFA-11", "LongName" => "Information et opposition à la consultation du DMP", "maxLength" => "3", "Chapter" => ""),
    "ZFA.12" => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de recueil de l'information et opposition à la consultation", "maxLength" => "26", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFAsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFAfields);

//
// ZFP : Situation professionnelle
//
// Add segment
$ZFPsegment = array(
    "ZFP" => array(
        "fields" => array(
            array("field" => "ZFP.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFP.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
        ),
        "LongName" => "Situation professionnelle",
        "Chapter" => ""
    )
);
// Add fields
$ZFPfields = array(
    "ZFP.1"  => array("Item" => "", "Type" => "ID", "Table" => "IHE-ZFP-1", "LongName" => "Activité socio-professionnelle (nomenclature INSEE)", "maxLength" => "1", "Chapter" => ""),
    "ZFP.2"  => array("Item" => "", "Type" => "ID", "Table" => "IHE-ZFP-2", "LongName" => "Catégorie socio-professionnelle (nomenclature INSEE)", "maxLength" => "2", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFPsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFPfields);

//
// ZFV : Complément d'information sur la venue
//
// Add segment
$ZFVsegment = array(
    "ZFV" => array(
        "fields" => array(
            array("field" => "ZFV.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.3",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZFV.4",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.5",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.6",  "minOccurs" => "0", "maxOccurs" => "2", "Usage" => "O"),
            array("field" => "ZFV.7",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.8",  "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"),
            array("field" => "ZFV.9",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFV.10", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"),
            array("field" => "ZFV.11", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
        ),
        "LongName" => "Complément d'information sur la venue",
        "Chapter" => ""
    )
);
// Add fields
$ZFVfields = array(
    "ZFV.1"  => array("Item" => "", "Type" => "DLD", "Table" => "HL70113", "LongName" => "Etablissement de provenance et date de dernier séjour dans cet établissement", "maxLength" => "47", "Chapter" => ""),
    "ZFV.2"  => array("Item" => "", "Type" => "CE", "Table" => "HL70430", "LongName" => "Mode de transport de sortie", "maxLength" => "250", "Chapter" => ""),
    "ZFV.3"  => array("Item" => "", "Type" => "IS", "Table" => "", "LongName" => "Type de préadmission", "maxLength" => "2", "Chapter" => ""),
    "ZFV.4"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de début de placement (psy)", "maxLength" => "26", "Chapter" => ""),
    "ZFV.5"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fin de placement (psy)", "maxLength" => "26", "Chapter" => ""),
    "ZFV.6"  => array("Item" => "", "Type" => "XAD", "Table" => "IHE-ZFV-6", "LongName" => "Adresse de l'établissement de provenance ou de destination", "maxLength" => "250", "Chapter" => ""),
    "ZFV.7"  => array("Item" => "", "Type" => "CX", "Table" => "", "LongName" => "NDA de l'établissement de provenance", "maxLength" => "250", "Chapter" => ""),
    "ZFV.8"  => array("Item" => "", "Type" => "CX", "Table" => "", "LongName" => "Numéro d'archives", "maxLength" => "250", "Chapter" => ""),
    "ZFV.9"  => array("Item" => "", "Type" => "IS", "Table" => "", "LongName" => "Mode de sortie personnalisé", "maxLength" => "6", "Chapter" => ""),
    "ZFV.10" => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFV-10", "LongName" => "Code RIM-P du mode légal de soin transmis dans le PV2-3", "maxLength" => "2", "Chapter" => ""),
    "ZFV.11" => array("Item" => "", "Type" => "CE", "Table" => "IHE-ZFV-11", "LongName" => "Prise en charge durant le transport", "maxLength" => "250", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFVsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFVfields);

//
// ZFM : Mouvement PMSI
//
// Add segment
$ZFMsegment = array(
    "ZFM" => array(
        "fields" => array(
            array("field" => "ZFM.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFM.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFM.3",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFM.4",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFM.5",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
        ),
        "LongName" => "Mouvement PMSI",
        "Chapter" => ""
    )
);
// Add fields
$ZFMfields = array(
    "ZFM.1"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFM-1", "LongName" => "Mode d'entrée PMSI", "maxLength" => "1", "Chapter" => ""),
    "ZFM.2"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFM-2", "LongName" => "Mode de sortie PMSI", "maxLength" => "1", "Chapter" => ""),
    "ZFM.3"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFM-3-4", "LongName" => "Mode de provenance PMSI", "maxLength" => "1", "Chapter" => ""),
    "ZFM.4"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFM-3-4", "LongName" => "Mode de destination PMSI", "maxLength" => "1", "Chapter" => ""),
    "ZFM.5"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFM-5", "LongName" => "Passage par une structure des Urgences (PMSI)", "maxLength" => "1", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFMsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFMfields);

//
// ZFD : Complément démographique
//
// Add segment
$ZFDsegment = array(
    "ZFD" => array(
        "fields" => array(
            array("field" => "ZFD.1",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFD.2",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFD.3",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFD.4",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFD.5",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFD.6",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFD.7",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFD.8",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
        ),
        "LongName" => "Complément démographique",
        "Chapter" => ""
    )
);
// Add fields
$ZFDfields = array(
    "ZFD.1"  => array("Item" => "", "Type" => "NA", "Table" => "", "LongName" => "Date Lunaire", "maxLength" => "8", "Chapter" => ""),
    "ZFD.2"  => array("Item" => "", "Type" => "NM", "Table" => "", "LongName" => "Nombre de semaines de gestation", "maxLength" => "16", "Chapter" => ""),
    "ZFD.3"  => array("Item" => "", "Type" => "ID", "Table" => "IHE-ZFD-3", "LongName" => "Consentement SMS", "maxLength" => "1", "Chapter" => ""),
    "ZFD.4"  => array("Item" => "", "Type" => "IS", "Table" => "HL70136", "LongName" => "Indicateur de date de naissance corrigée", "maxLength" => "1", "Chapter" => ""),
    "ZFD.5"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFD-5", "LongName" => "Mode d'obtention de l'identité", "maxLength" => "8", "Chapter" => ""),
    "ZFD.6"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date d'interrogation du téléservice INSi", "maxLength" => "26", "Chapter" => ""),
    "ZFD.7"  => array("Item" => "", "Type" => "IS", "Table" => "IHE-ZFD-7", "LongName" => "Type de justificatif d'identité", "maxLength" => "16", "Chapter" => ""),
    "ZFD.8"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date de fin de validité du document", "maxLength" => "26", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFDsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFDfields);

//
// ZFS : Mode légal de soins en psychiatrie
//
// Add segment
$ZFSsegment = array(
    "ZFS" => array(
        "fields" => array(
            array("field" => "ZFS.1",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZFS.2",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZFS.3",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZFS.4",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE"),
            array("field" => "ZFS.5",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZFS.6",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZFS.7",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
            array("field" => "ZFS.8",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
        ),
        "LongName" => "Mode légal de soins en psychiatrie",
        "Chapter" => ""
    )
);
// Add fields
$ZFSfields = array(
    "ZFS.1"  => array("Item" => "", "Type" => "SI", "Table" => "", "LongName" => "Set ID - ZFS", "maxLength" => "4", "Chapter" => ""),
    "ZFS.2"  => array("Item" => "", "Type" => "EI", "Table" => "", "LongName" => "Identifiant du mode légal de soin", "maxLength" => "427", "Chapter" => ""),
    "ZFS.3"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date et heure du début du mode légal de soin", "maxLength" => "26", "Chapter" => ""),
    "ZFS.4"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Date et heure de la fin du mode légal de soin", "maxLength" => "26", "Chapter" => ""),
    "ZFS.5"  => array("Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Action du mode légal de soin", "maxLength" => "6", "Chapter" => ""),
    "ZFS.6"  => array("Item" => "", "Type" => "CWE", "Table" => "IHE-ZFS-6", "LongName" => "Mode légal de soins", "maxLength" => "250", "Chapter" => ""),
    "ZFS.7"  => array("Item" => "", "Type" => "CNE", "Table" => "IHE-ZFS-7", "LongName" => "Code RIM-P du mode légal de soin", "maxLength" => "2", "Chapter" => ""),
    "ZFS.8"  => array("Item" => "", "Type" => "FT", "Table" => "", "LongName" => "Commentaire", "maxLength" => "65536", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZFSsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZFSfields);

//
// ZBE : Action sur un mouvement - Movement segment
//
// Add segment
$ZBEsegment = array(
    "ZBE" => array(
        "fields" => array(
            array("field" => "ZBE.1",  "minOccurs" => "1", "maxOccurs" => "unbounded", "Usage" => "R"),
            array("field" => "ZBE.2",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZBE.3",  "minOccurs" => "0", "maxOccurs" => "0", "Usage" => "X"),
            array("field" => "ZBE.4",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZBE.5",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
            array("field" => "ZBE.6",  "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"),
            array("field" => "ZBE.7", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"),
            array("field" => "ZBE.8", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "C"),
            array("field" => "ZBE.9",  "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
        ),
        "LongName" => "Movement segment",
        "Chapter" => ""
    )
);
// Add fields
$ZBEfields = array(
    "ZBE.1"  => array("Item" => "", "Type" => "EI", "Table" => "", "LongName" => "Movement ID", "maxLength" => "427", "Chapter" => ""),
    "ZBE.2"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "Start of Movement Date/Time", "maxLength" => "26", "Chapter" => ""),
    "ZBE.3"  => array("Item" => "", "Type" => "TS", "Table" => "", "LongName" => "End of Movement Date/Time", "maxLength" => "26", "Chapter" => ""),
    "ZBE.4"  => array("Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Action on the Movement", "maxLength" => "6", "Chapter" => ""),
    "ZBE.5"  => array("Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Indicator Historical movement", "maxLength" => "1", "Chapter" => ""),
    "ZBE.6"  => array("Item" => "", "Type" => "ID", "Table" => "", "LongName" => "Original trigger event code", "maxLength" => "3", "Chapter" => ""),
    "ZBE.7"  => array("Item" => "", "Type" => "XON", "Table" => "", "LongName" => "Ward of medical responsibility in the period starting with this movement", "maxLength" => "250", "Chapter" => ""),
    "ZBE.8"  => array("Item" => "", "Type" => "XON", "Table" => "", "LongName" => "Ward of care responsibility in the period starting with this movement", "maxLength" => "250", "Chapter" => ""),
    "ZBE.9"  => array("Item" => "", "Type" => "CWE", "Table" => "IHE-ZBE-9", "LongName" => "Nature of this movement", "maxLength" => "3", "Chapter" => ""),
);

$segmentsSchemas = array_merge($segmentsSchemas, $ZBEsegment);
$fieldsSchemas = array_merge($fieldsSchemas, $ZBEfields);

echo "Create Z-segments: done.<br/>";


/**
 * Update segments
 * -------------------------------------
 */

//
// MSH segment
//
foreach ($segmentsSchemas["MSH"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'MSH.1':
        case 'MSH.2':
        case 'MSH.3':
        case 'MSH.4':
        case 'MSH.5':
        case 'MSH.6':
        case 'MSH.7':
        case 'MSH.9':
        case 'MSH.10':
        case 'MSH.11':
        case 'MSH.12':
            $segmentsSchemas["MSH"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["MSH"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["MSH"]["fields"][$key]["Usage"] = "R";
            break;

        case 'MSH.8':
        case 'MSH.14':
        case 'MSH.20':
            $segmentsSchemas["MSH"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["MSH"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["MSH"]["fields"][$key]["Usage"] = "X";
            break;

        case 'MSH.17':
        case 'MSH.19':
        case 'MSH.21':
            $segmentsSchemas["MSH"]["fields"][$key]["Usage"] = "RE";
            break;

        case 'MSH.18':
            $segmentsSchemas["MSH"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["MSH"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["MSH"]["fields"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
// 
// EVN segment
//
foreach ($segmentsSchemas["EVN"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'EVN.1':
            $segmentsSchemas["EVN"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["EVN"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["EVN"]["fields"][$key]["Usage"] = "X";
            break;

        case 'EVN.3':
        case 'EVN.6':
            $segmentsSchemas["EVN"]["fields"][$key]["Usage"] = "C";
            break;

        case 'EVN.7':
            $segmentsSchemas["EVN"]["fields"][$key]["Usage"] = "RE";
            break;

        
        default:
            break;
    }
}
//
// PID segment
//
foreach ($segmentsSchemas["PID"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'PID.2':
        case 'PID.4':
        case 'PID.9':
        case 'PID.10':
        case 'PID.12':
        case 'PID.17':
        case 'PID.19':
        case 'PID.20':
        case 'PID.22':
        case 'PID.28':
            $segmentsSchemas["PID"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["PID"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "X";
            break;

        case 'PID.7':
        case 'PID.8':
        case 'PID.11':
        case 'PID.18':
        case 'PID.25':
        case 'PID.33':
        case 'PID.35':
        case 'PID.36':
            $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "C";
            break;

        case 'PID.31':
            $segmentsSchemas["PID"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["PID"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "CE";
            break;

        case 'PID.32':
            $segmentsSchemas["PID"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["PID"]["fields"][$key]["maxOccurs"] = "unbounded";
            $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "R";
            break;
        
        case 'PID.38':
            $segmentsSchemas["PID"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["PID"]["fields"][$key]["maxOccurs"] = "2";
            $segmentsSchemas["PID"]["fields"][$key]["Usage"] = "O";
            break;

        default:
            break;
    }
}
//
// ROL segment
//
foreach ($segmentsSchemas["ROL"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'ROL.1':
            // ROL-1 – Role Instance ID. This field is in fact optional in the context of ADT messages.
            $segmentsSchemas["ROL"]["fields"][$key]["Usage"] = "O";
            break;

        case 'ROL.9':
        case 'ROL.11':
            $segmentsSchemas["ROL"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["ROL"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["ROL"]["fields"][$key]["Usage"] = "O";
            break;
        
        default:
            break;
    }
}
//
// NK1 segment
//
foreach ($segmentsSchemas["NK1"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'NK1.25':
        case 'NK1.28':
        case 'NK1.35':
            $segmentsSchemas["NK1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["NK1"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["NK1"]["fields"][$key]["Usage"] = "X";
            break;
        
        case 'NK1.33':
            $segmentsSchemas["NK1"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["NK1"]["fields"][$key]["maxOccurs"] = "unbounded";
            $segmentsSchemas["NK1"]["fields"][$key]["Usage"] = "R";
            break;

        default:
            break;
    }
}
$fieldsSchemas["NK1.11"]["Table"] = "HL70327,HL70328";
//
// PV1 segment
//
foreach ($segmentsSchemas["PV1"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'PV1.3':
        case 'PV1.5':
        case 'PV1.6':
        case 'PV1.11':
        case 'PV1.19':
        case 'PV1.42':
            $segmentsSchemas["PV1"]["fields"][$key]["Usage"] = "C";
            break;

        case 'PV1.9':
        case 'PV1.40':
        case 'PV1.52':
            $segmentsSchemas["PV1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["PV1"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["PV1"]["fields"][$key]["Usage"] = "X";
            break;

        case 'PV1.45':
            $segmentsSchemas["PV1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["PV1"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["PV1"]["fields"][$key]["Usage"] = "O";
            break;

        default:
            break;
    }
}
$fieldsSchemas["PV1.9"]["Table"] = "";
$fieldsSchemas["PV1.52"]["Table"] = "";
//
// PV2 segment
//
foreach ($segmentsSchemas["PV2"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'PV2.1':
        case 'PV2.47':
            $segmentsSchemas["PV2"]["fields"][$key]["Usage"] = "C";
            break;

        case 'PV2.18':
            $segmentsSchemas["PV2"]["fields"][$key]["Usage"] = "RE";
            break;

        default:
            break;
    }
}
$fieldsSchemas["PV2.3"]["Table"] = "IHE-PV2-3";
//
// ACC segment
//
foreach ($segmentsSchemas["ACC"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'ACC.1':
            $segmentsSchemas["ACC"]["fields"][$key]["Usage"] = "RE";
            break;

        case 'ACC.2':
            $segmentsSchemas["ACC"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["ACC"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["ACC"]["fields"][$key]["Usage"] = "R";
            break;

        case 'ACC.4':
            $segmentsSchemas["ACC"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["ACC"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["ACC"]["fields"][$key]["Usage"] = "X";
            break;

        case 'ACC.':
            $segmentsSchemas["ACC"]["fields"][$key]["Usage"] = "RE";
            break;

        default:
            break;
    }
}
$fieldsSchemas["ACC.4"]["Table"] = "";
//
// IN1 segment
//
foreach ($segmentsSchemas["IN1"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'IN1.3':
            $segmentsSchemas["IN1"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["IN1"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["IN1"]["fields"][$key]["Usage"] = "R";
            break;

        case 'IN1.12':
        case 'IN1.13':
        case 'IN1.15':
        case 'IN1.16':
        case 'IN1.19':
        case 'IN1.20':
        case 'IN1.31':
        case 'IN1.35':
        case 'IN1.45':
        case 'IN1.49':
            $segmentsSchemas["IN1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["IN1"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["IN1"]["fields"][$key]["Usage"] = "RE";
            break;

        case 'IN1.17':
            $segmentsSchemas["IN1"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["IN1"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["IN1"]["fields"][$key]["Usage"] = "R";
            break;

        case 'IN1.36':
            $segmentsSchemas["IN1"]["fields"][$key]["Usage"] = "C";
            break;

        default:
            break;
    }
}
$fieldsSchemas["IN1.35"]["maxLength"] = "20";
//
// IN2 segment
//
foreach ($segmentsSchemas["IN2"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'IN2.63':
            $segmentsSchemas["IN2"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["IN2"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["IN2"]["fields"][$key]["Usage"] = "RE";
            break;

        default:
            break;
    }
}
//
// IN3 segment
//
foreach ($segmentsSchemas["IN3"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'IN3.5':
            $segmentsSchemas["IN3"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["IN3"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["IN3"]["fields"][$key]["Usage"] = "RE";
            break;
        
        default:
            break;
    }
}
//
// GT1 segment
//
foreach ($segmentsSchemas["GT1"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'GT1.4':
        case 'GT1.8':
        case 'GT1.9':
        case 'GT1.33':
        case 'GT1.34':
        case 'GT1.35':
        case 'GT1.38':
        case 'GT1.39':
        case 'GT1.40':
        case 'GT1.41':
        case 'GT1.42':
        case 'GT1.44':
        case 'GT1.52':
        case 'GT1.55':
            $segmentsSchemas["GT1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["GT1"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["GT1"]["fields"][$key]["Usage"] = "X";
            break;

        case 'GT1.29':
        case 'GT1.51':
            $segmentsSchemas["GT1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["GT1"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["GT1"]["fields"][$key]["Usage"] = "O";
            break;
        
        default:
            break;
    }
}
//
// OBX segment
//
foreach ($segmentsSchemas["OBX"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'OBX.1':
        case 'OBX.2':
        case 'OBX.16':
            $segmentsSchemas["OBX"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["OBX"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["OBX"]["fields"][$key]["Usage"] = "R";
            break;

        case 'OBX.5':
            $segmentsSchemas["OBX"]["fields"][$key]["minOccurs"] = "1";
            $segmentsSchemas["OBX"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["OBX"]["fields"][$key]["Usage"] = "C";
            break;

        case 'OBX.6':
            $segmentsSchemas["OBX"]["fields"][$key]["Usage"] = "C";
            break;

        case 'OBX.8':
        case 'OBX.10':
        case 'OBX.17':
        case 'OBX.18':
            $segmentsSchemas["OBX"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["OBX"]["fields"][$key]["maxOccurs"] = "1";
            $segmentsSchemas["OBX"]["fields"][$key]["Usage"] = "O";
            break;

        case 'OBX.14':
            $segmentsSchemas["OBX"]["fields"][$key]["Usage"] = "RE";
            break;
        
        default:
            break;
    }
}
//
// AL1
//
foreach ($segmentsSchemas["AL1"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'AL1.6':
            $segmentsSchemas["AL1"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["AL1"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["AL1"]["fields"][$key]["Usage"] = "X";
            break;

        default:
            break;
    }
}
//
// MRG
//
foreach ($segmentsSchemas["MRG"]["fields"] as $key => $field) {
    switch ($field["field"]) {
        case 'MRG.2':
        case 'MRG.4':
        case 'MRG.5':
        case 'MRG.6':
            $segmentsSchemas["MRG"]["fields"][$key]["minOccurs"] = "0";
            $segmentsSchemas["MRG"]["fields"][$key]["maxOccurs"] = "0";
            $segmentsSchemas["MRG"]["fields"][$key]["Usage"] = "X";
            break;

        default:
            break;
    }
}

echo "Update segments: done.<br/>";


/**
 * Update data types
 * -------------------------------------
 */

//
// CX
//
foreach ($dataTypesSchemas["CX"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'CX.4':
            $dataTypesSchemas["CX"]["components"][$key]["minOccurs"] = "1";
            $dataTypesSchemas["CX"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["CX"]["components"][$key]["Usage"] = "R";
            break;

        case 'CX.5':
            $dataTypesSchemas["CX"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["CX"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["CX"]["components"][$key]["Usage"] = "RE";
            break;

        case 'CX.7':
            $dataTypesSchemas["CX"]["components"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
$dataTypesSchemas["CX.1"]["maxLength"] = "128";
//
// EI
//
foreach ($dataTypesSchemas["EI"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'EI.1':
            $dataTypesSchemas["EI"]["components"][$key]["minOccurs"] = "1";
            $dataTypesSchemas["EI"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["EI"]["components"][$key]["Usage"] = "R";
            break;

        case 'EI.2':
        case 'EI.3':
        case 'EI.4':
            $dataTypesSchemas["EI"]["components"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
$dataTypesSchemas["EI.1"]["maxLength"] = "128";
//
// HD
//
foreach ($dataTypesSchemas["HD"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'HD.1':
            $dataTypesSchemas["HD"]["components"][$key]["minOccurs"] = "1";
            $dataTypesSchemas["HD"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["HD"]["components"][$key]["Usage"] = "R";
            break;

        case 'HD.2':
        case 'HD.3':
            $dataTypesSchemas["HD"]["components"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
//
// PL
//
foreach ($dataTypesSchemas["PL"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'PL.6':
            $dataTypesSchemas["PL"]["components"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
//
// TS
//
foreach ($dataTypesSchemas["TS"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'TS.2':
            $dataTypesSchemas["TS"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["TS"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["TS"]["components"][$key]["Usage"] = "X";
            break;
        
        default:
            break;
    }
}
//
// VID
//
foreach ($dataTypesSchemas["VID"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'VID.1':
        case 'VID.2':
        case 'VID.3':
            $dataTypesSchemas["VID"]["components"][$key]["minOccurs"] = "1";
            $dataTypesSchemas["VID"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["VID"]["components"][$key]["Usage"] = "R";
            break;

        default:
            break;
    }
}
//
// XAD
//
foreach ($dataTypesSchemas["XAD"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'XAD.12':
            $dataTypesSchemas["XAD"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XAD"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["XAD"]["components"][$key]["Usage"] = "X";
            break;
        
        default:
            break;
    }
}
//
// SAD
//
foreach ($dataTypesSchemas["SAD"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'SAD.2':
        case 'SAD.3':
            $dataTypesSchemas["SAD"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["SAD"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["SAD"]["components"][$key]["Usage"] = "X";
            break;
        
        default:
            break;
    }
}
//
// XCN
//
foreach ($dataTypesSchemas["XCN"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'XCN.1':
        case 'XCN.2':
        case 'XCN.3':
        case 'XCN.9':
            $dataTypesSchemas["XCN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XCN"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XCN"]["components"][$key]["Usage"] = "RE";
            break;

        case 'XCN.5':
        case 'XCN.7':
        case 'XCN.8':
        case 'XCN.11':
        case 'XCN.12':
        case 'XCN.15':
        case 'XCN.16':
        case 'XCN.17':
        case 'XCN.18':
        case 'XCN.19':
        case 'XCN.20':
        case 'XCN.21':
        case 'XCN.22':
        case 'XCN.23':
            $dataTypesSchemas["XCN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XCN"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["XCN"]["components"][$key]["Usage"] = "X";
            break;

        case 'XCN.10':
        case 'XCN.13':
            $dataTypesSchemas["XCN"]["components"][$key]["Usage"] = "C";
            break;
        
        default:
            break;
    }
}
$dataTypesSchemas["XCN.1"]["maxLength"] = "199";
//
// XON
//
foreach ($dataTypesSchemas["XON"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'XON.1':
        case 'XON.6':
        case 'XON.7':
        case 'XON.10':
            $dataTypesSchemas["XON"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XON"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XON"]["components"][$key]["Usage"] = "RE";
            break;

        case 'XON.2':
        case 'XON.3':
        case 'XON.4':
        case 'XON.5':
        case 'XON.8':
        case 'XON.9':
            $dataTypesSchemas["XON"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XON"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["XON"]["components"][$key]["Usage"] = "X";
            break;

        default:
            break;
    }
}
$dataTypesSchemas["XON.10"]["maxLength"] = "64";
//
// XPN
//
foreach ($dataTypesSchemas["XPN"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'XPN.1':
            $dataTypesSchemas["XPN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XPN"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XPN"]["components"][$key]["Usage"] = "RE";
            break;

        case 'XPN.2':
        case 'XPN.3':
            $dataTypesSchemas["XPN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XPN"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XPN"]["components"][$key]["Usage"] = "C";
            break;

        case 'XPN.4':
        case 'XPN.6':
        case 'XPN.8':
        case 'XPN.9':
        case 'XPN.10':
        case 'XPN.11':
        case 'XPN.12':
        case 'XPN.13':
        case 'XPN.14':
            $dataTypesSchemas["XPN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XPN"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["XPN"]["components"][$key]["Usage"] = "X";
            break;

        case 'XPN.7':
            $dataTypesSchemas["XPN"]["components"][$key]["minOccurs"] = "1";
            $dataTypesSchemas["XPN"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XPN"]["components"][$key]["Usage"] = "R";
            break;

        default:
            break;
    }
}
$dataTypesSchemas["XPN.2"]["maxLength"] = "194";
$dataTypesSchemas["XPN.3"]["maxLength"] = "194";
//
// XTN
//
foreach ($dataTypesSchemas["XTN"]["components"] as $key => $component) {
    switch ($component["dataType"]) {
        case 'XTN.1':
        case 'XTN.5':
        case 'XTN.6':
        case 'XTN.7':
        case 'XTN.8':
        case 'XTN.10':
        case 'XTN.11':
            $dataTypesSchemas["XTN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XTN"]["components"][$key]["maxOccurs"] = "0";
            $dataTypesSchemas["XTN"]["components"][$key]["Usage"] = "X";
            break;

        case 'XTN.4':
        case 'XTN.12':
            $dataTypesSchemas["XTN"]["components"][$key]["minOccurs"] = "0";
            $dataTypesSchemas["XTN"]["components"][$key]["maxOccurs"] = "1";
            $dataTypesSchemas["XTN"]["components"][$key]["Usage"] = "C";
            break;

        default:
            break;
    }
}

echo "Update data types: done.<br/>";

/**
 * Update structures
 * -------------------------------------
 *
 */

$messageStructures = array();
$messageDesc = array();
$ADTevents = array(
    "A01","A02","A03","A04","A05","A06","A07","A09",
    "A10","A11","A12","A13","A14","A15","A16",
    "A21","A22","A25","A26","A27","A28",
    "A31","A32","A33","A38",
    "A40","A44","A47","A49",
    "A52","A53","A54","A55",
    "Z99"
);

$SIUevents = array(
    "S12","S14","S15","S26"
);

// Create event desc.
foreach ($eventDesc as $type => $event) {
    $messageDesc[$type] = array();
    foreach ($event as $eventName => $desc) {
        if ($type == "ADT" && ! in_array($eventName, $ADTevents)) {
            continue;
        }
        else if ($type == "SIU" && ! in_array($eventName, $SIUevents)) {
            continue;
        }
        $messageDesc[$type][$eventName] = $desc;
    }
}
echo "Create event desc.: done.<br/>";


// Create message structures
foreach ($messageType as $type => $event) {
    $messageStructures[$type] = array();
    foreach ($event as $eventName => $strucureId) {
        if ($type == "ADT" && ! in_array($eventName, $ADTevents)) {
            continue;
        }
        else if ($type == "SIU" && ! in_array($eventName, $SIUevents)) {
            continue;
        }
        
        if (file_exists($inputDir . "/structures/" . $strucureId . ".json")) {
            $structureName = "$type-$eventName-$strucureId";
            $messageStructures[$type][$eventName] = $structureName;
            copy($inputDir . "/structures/" . $strucureId . ".json", $outputDir . "/structures/" . $structureName . ".json");
        }
    }
}
echo "Create message structures: done.<br/>";


// Update message structures
foreach ($messageStructures as $type => $event) {
    foreach ($event as $eventName => $strucureId) {
        if (file_exists($outputDir . "/structures/" . $strucureId . ".json")) {
            $msgStruct = loadJsonSchemas($outputDir . "/structures/" . $strucureId . ".json");
            
            // Update INSURANCE group (IN3)
            if (isset($msgStruct["INSURANCE"])) {
                foreach ($msgStruct["INSURANCE"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'IN3':
                                $msgStruct["INSURANCE"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["INSURANCE"]["elements"][$key]["maxOccurs"] = "1";
                                $msgStruct["INSURANCE"]["elements"][$key]["Usage"] = "O";
                                break;

                            default:
                                break;
                        }
                    }
                }
            }

            //
            // ITI-30
            //

            // ADT^A28^ADT_A05
            // ADT^A31^ADT_A05
            if (in_array($eventName, array("A28", "A31"))) {
                $ROL = 0;
                $ZFA = array("segment" => "ZFA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE");
                $ZFD = array("segment" => "ZFD", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "RE");
                $ZFS = array("segment" => "ZFS", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "C");
                foreach ($msgStruct["ADT_A05"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'NK1':
                                $msgStruct["ADT_A05"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A05"]["elements"][$key]["maxOccurs"] = "unbounded";
                                $msgStruct["ADT_A05"]["elements"][$key]["Usage"] = "RE";
                                break;

                            case 'PV2':
                                $msgStruct["ADT_A05"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A05"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A05"]["elements"][$key]["Usage"] = "X";
                                break;

                            case 'ROL':
                                $ROL++;
                                if ($ROL == 2) {
                                    $msgStruct["ADT_A05"]["elements"][$key]["minOccurs"] = "0";
                                    $msgStruct["ADT_A05"]["elements"][$key]["maxOccurs"] = "0";
                                    $msgStruct["ADT_A05"]["elements"][$key]["Usage"] = "X";
                                }
                                break;

                            default:
                                break;
                        }
                    }
                }
                array_splice($msgStruct["ADT_A05"]["elements"], 9, 0, array($ZFA, $ZFD, $ZFS));
            }

            // ADT^A40^ADT_A39
            if (in_array($eventName, array("A40"))) {
                foreach ($msgStruct["ADT_A39"]["elements"] as $key => $element) {
                    if (isset($element["group"])) {
                        switch ($element["group"]) {
                            case 'PATIENT':
                                $msgStruct["ADT_A39"]["elements"][$key]["minOccurs"] = "1";
                                $msgStruct["ADT_A39"]["elements"][$key]["maxOccurs"] = "1";
                                $msgStruct["ADT_A39"]["elements"][$key]["Usage"] = "R";
                                break;

                            default:
                                break;
                        }
                    }
                }
                foreach ($msgStruct["PATIENT"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'PV1':
                                $msgStruct["PATIENT"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["PATIENT"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["PATIENT"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
            }

            // ADT^A47^ADT_A30
            if (in_array($eventName, array("A47"))) {
                $msgStruct = array(
                    "PATIENT" => array(
                        "elements" => array(
                            0 => array("segment" => "PID", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
                            1 => array("segment" => "PD1", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O"),
                            2 => array("segment" => "MRG", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R")
                        )
                    ),
                    "ADT_A30" => array(
                        "elements" => array(
                            0 => array("segment" => "MSH", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
                            1 => array("segment" => "SFT", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O"),
                            2 => array("segment" => "EVN", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
                            3 => array("group" => "PATIENT", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R"),
                        )
                    )
                );
            }


            //
            // ITI-31
            //
            $ZBE = array("segment" => "ZBE", "minOccurs" => "1", "maxOccurs" => "1", "Usage" => "R");
            $ZFA = array("segment" => "ZFA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
            $ZFP = array("segment" => "ZFP", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
            $ZFV = array("segment" => "ZFV", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
            $ZFM = array("segment" => "ZFM", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
            $ZFD = array("segment" => "ZFD", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
            $ZFS = array("segment" => "ZFS", "minOccurs" => "0", "maxOccurs" => "unbounded", "Usage" => "O");

            // ADT^A01^ADT_A01
            // ADT^A04^ADT_A01
            // ADT^Z99^ADT_A01
            if (in_array($eventName, array("A01", "A04", "Z99"))) {
                array_splice($msgStruct["ADT_A01"]["elements"], 9, 0, array($ZBE, $ZFA, $ZFP, $ZFV, $ZFM, $ZFD));
            }

            // ADT^A01^ADT_A01
            // ADT^A04^ADT_A01
            if (in_array($eventName, array("A01", "A04"))) {
                $ROL = 0;
                foreach ($msgStruct["ADT_A01"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'ROL':
                                $ROL++;
                                if ($ROL == 1) {
                                    $msgStruct["ADT_A01"]["elements"][$key]["minOccurs"] = "0";
                                    $msgStruct["ADT_A01"]["elements"][$key]["maxOccurs"] = "unbounded";
                                    $msgStruct["ADT_A01"]["elements"][$key]["Usage"] = "RE";
                                }
                                break;

                            default:
                                break;
                        }
                    }
                }
            }


            // ADT^A09^ADT_A09
            if (in_array($eventName, array("A09"))) {
                array_splice( $msgStruct["ADT_A09"]["elements"], 7, 0, array($ZBE));
            }

            // ADT^A10^ADT_A09
            // ADT^A11^ADT_A09
            if (in_array($eventName, array("A10", "A11"))) {
                foreach ($msgStruct["ADT_A09"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'DG1':
                                $msgStruct["ADT_A09"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A09"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A09"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
                array_splice( $msgStruct["ADT_A09"]["elements"], 7, 0, array($ZBE));
            }

            // ADT^A03^ADT_A03
            if (in_array($eventName, array("A03"))) {
                foreach ($msgStruct["ADT_A03"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'PV2':
                                $msgStruct["ADT_A03"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A03"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A03"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
                array_splice( $msgStruct["ADT_A03"]["elements"], 9, 0, array($ZBE, $ZFV, $ZFM));
            }

            // ADT^A13^ADT_A01
            if (in_array($eventName, array("A13"))) {
                array_splice($msgStruct["ADT_A01"]["elements"], 9, 0, array($ZBE));
            }

            // ADT^A05^ADT_A05
            // ADT^A14^ADT_A05
            if (in_array($eventName, array("A05", "A14"))) {
                foreach ($msgStruct["ADT_A05"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'PV2':
                                $msgStruct["ADT_A05"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A05"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A05"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
                $PDA = array("segment" => "PDA", "minOccurs" => "0", "maxOccurs" => "1", "Usage" => "O");
                array_splice($msgStruct["ADT_A05"]["elements"], 9, 0, array($ZBE, $ZFA, $ZFP, $ZFV, $ZFM, $ZFD));
                array_splice($msgStruct["ADT_A05"]["elements"], count($msgStruct["ADT_A05"]["elements"]), 0, array($PDA));
            }

            // ADT^A38^ADT_A38
            if (in_array($eventName, array("A38"))) {
                array_splice($msgStruct["ADT_A38"]["elements"], 7, 0, array($ZBE));
            }

            // ADT^A06^ADT_A06
            // ADT^A07^ADT_A06
            if (in_array($eventName, array("A06", "A07"))) {
                foreach ($msgStruct["ADT_A06"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'MRG':
                                $msgStruct["ADT_A06"]["elements"][$key]["Usage"] = "C";
                                break;

                            case 'PV2':
                                $msgStruct["ADT_A06"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A06"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A06"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
            }

            // ADT^A06^ADT_A06
            if (in_array($eventName, array("A06"))) {
                array_splice($msgStruct["ADT_A06"]["elements"], 10, 0, array($ZBE, $ZFM));
            }
            // ADT^A07^ADT_A06
            if (in_array($eventName, array("A07"))) {
                array_splice($msgStruct["ADT_A06"]["elements"], 10, 0, array($ZBE));
            }

            // ADT^A02^ADT_A02
            if (in_array($eventName, array("A02"))) {
                array_splice($msgStruct["ADT_A02"]["elements"], 8, 0, array($ZBE, $ZFV, $ZFM));
            }

            // ADT^A12^ADT_A12
            if (in_array($eventName, array("A12"))) {
                foreach ($msgStruct["ADT_A12"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'DG1':
                                $msgStruct["ADT_A12"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A12"]["elements"][$key]["maxOccurs"] = "0";
                                $msgStruct["ADT_A12"]["elements"][$key]["Usage"] = "X";
                                break;

                            default:
                                break;
                        }
                    }
                }
                array_splice($msgStruct["ADT_A12"]["elements"], 7, 0, array($ZBE));
            }

            // ADT^A21^ADT_A21
            if (in_array($eventName, array("A21"))) {
                array_splice($msgStruct["ADT_A21"]["elements"], 7, 0, array($ZBE, $ZFV, $ZFM));
            }

            // ADT^A22^ADT_A21
            if (in_array($eventName, array("A22"))) {
                array_splice($msgStruct["ADT_A21"]["elements"], 7, 0, array($ZBE, $ZFM));
            }

            // ADT^A25^ADT_A21
            // ADT^A26^ADT_A21
            // ADT^A27^ADT_A21
            // ADT^A32^ADT_A21
            // ADT^A33^ADT_A21
            if (in_array($eventName, array("A25", "A26", "A27", "A32", "A33"))) {
                array_splice($msgStruct["ADT_A21"]["elements"], 7, 0, array($ZBE));
            }

            // ADT^A15^ADT_A15
            if (in_array($eventName, array("A15"))) {
                array_splice($msgStruct["ADT_A15"]["elements"], 8, 0, array($ZBE));
            }

            // ADT^A16^ADT_A16
            if (in_array($eventName, array("A16"))) {
                foreach ($msgStruct["ADT_A16"]["elements"] as $key => $element) {
                    if (isset($element["segment"])) {
                        switch ($element["segment"]) {
                            case 'PV2':
                                $msgStruct["ADT_A16"]["elements"][$key]["minOccurs"] = "0";
                                $msgStruct["ADT_A16"]["elements"][$key]["maxOccurs"] = "1";
                                $msgStruct["ADT_A16"]["elements"][$key]["Usage"] = "RE";
                                break;

                            default:
                                break;
                        }
                    }
                }
                array_splice($msgStruct["ADT_A16"]["elements"], 9, 0, array($ZBE));
            }

            // ADT^A54^ADT_A54
            if (in_array($eventName, array("A54"))) {
                array_splice($msgStruct["ADT_A54"]["elements"], 8, 0, array($ZBE));
            }

            // ADT^A52^ADT_A52
            // ADT^A53^ADT_A52
            // ADT^A55^ADT_A52
            if (in_array($eventName, array("A52", "A53", "A55"))) {
                array_splice($msgStruct["ADT_A52"]["elements"], 7, 0, array($ZBE));
            }

            file_put_contents($outputDir . "/structures/" . $strucureId . ".json", json_encode($msgStruct, JSON_PRETTY_PRINT));
        }
    }
}

echo "Update message structures: done.<br/>";




ksort($segmentsSchemas);
ksort($fieldsSchemas);
ksort($dataTypesSchemas);
file_put_contents($outputDir . "/segments/segments.json", json_encode($segmentsSchemas, JSON_PRETTY_PRINT));
file_put_contents($outputDir . "/fields/fields.json", json_encode($fieldsSchemas, JSON_PRETTY_PRINT));
file_put_contents($outputDir . "/dataTypes/dataTypes.json", json_encode($dataTypesSchemas, JSON_PRETTY_PRINT));
file_put_contents($outputDir . "/messageType.json", json_encode($messageStructures, JSON_PRETTY_PRINT));
file_put_contents($outputDir . "/eventDesc.json", json_encode($messageDesc, JSON_PRETTY_PRINT));

echo "Done.";
