<?php

declare(strict_types=1);

return [
    'inputDir' => '../schemas/json-2.5-IHEPAMFR',
    'outputDir' => '../profiles/json-2.5-IHEPAMFR',

    'msgType' => [
        'ACK',
        'ADT',
        'SIU',
    ],

    'ignoreEvents' => ['A08'],

    'fieldsConstraints' => true,

    'indent' => 2,

    'pretty' => true,
];
