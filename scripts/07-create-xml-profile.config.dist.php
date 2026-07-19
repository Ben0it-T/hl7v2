<?php

declare(strict_types=1);

return [
    'HL7Version' => '2.5',

    'inputDir'  => '../schemas/json-2.5-IHEPAMFR',
    'outputDir' => '../profiles/xml-2.5-IHEPAMFR',

    'msgType' => [
        'ACK',
        'ADT',
        'SIU',
    ],

    'ignoreEvents' => ['A08'],

    'fieldsConstraints' => true,
];
