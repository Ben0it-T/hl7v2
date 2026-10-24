<?php

declare(strict_types=1);

/**
 * Clean XSD schemas.
 *
 * Formats every .xsd file of the input directory: removes unnecessary whitespace and indents the XML.
 * Files are rewritten in place.
 *
 * HL7’s Version 2.x XSD schemas : https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185
 *
 * Usage:
 * php 01-xsd-schemas-cleaner.php \
 *     --input-dir=<directory>
 *
 * Example:
 * php 01-xsd-schemas-cleaner.php \
 *     --input-dir=../schemas/hl7v2org/2.5
 *
 * php 01-xsd-schemas-cleaner.php \
 *     --input-dir=../schemas/hl7v2xsd/2.5
 */

$options = getopt('', [
    'input-dir:',
]);

$inputDir = $options['input-dir'] ?? null;

if (!is_string($inputDir) || $inputDir === '') {
    fwrite(
        STDERR,
        "Usage: php 01-xsd-schemas-cleaner.php --input-dir=<directory>\n"
    );
    exit(1);
}

if (!is_dir($inputDir)) {
    throw new RuntimeException(
        "Directory not found: {$inputDir}"
    );
}

$files = scandir($inputDir, SCANDIR_SORT_ASCENDING);

if ($files === false) {
    throw new RuntimeException(
        "Unable to read directory: {$inputDir}"
    );
}

//
// CLEAN XSD SCHEMAS
//

foreach ($files as $file) {
    $filename = $inputDir . DIRECTORY_SEPARATOR . $file;

    if (!is_file($filename)) {
        continue;
    }

    if (!str_ends_with($file, '.xsd')) {
        continue;
    }

    echo "- {$file}\n";

    $document = new DOMDocument();
    $document->preserveWhiteSpace = false;
    $document->formatOutput = true;

    if (!$document->load($filename)) {
        throw new RuntimeException(
            "Unable to load XML file: {$filename}"
        );
    }

    $document->save($filename);
}

echo "Done.\n";
