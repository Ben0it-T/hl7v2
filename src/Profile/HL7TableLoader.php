<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Profile\HL7Tables;
use HL7v2\Exception\HL7Exception;

class HL7TableLoader
{
    private string $profilePath;

    public function __construct(string $profilePath)
    {
        $this->profilePath = rtrim($profilePath, '/');
    }

    /**
     * Load HL7 tables.
     *
     * @return HL7Tables
     * @throws HL7Exception
     */
    public function load(string $versionId): HL7Tables
    {
        $filename = sprintf('%s/json-%s/hl7tables.json',$this->profilePath, $versionId);
        if (!is_file($filename)) {
            throw new HL7Exception(
                "HL7 tables not found: $filename"
            );
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            throw new HL7Exception(
                "Unable to read file: $filename"
            );
        }

        $tables = json_decode($content, true);
        if (!is_array($tables)) {
            throw new HL7Exception(
                "Invalid HL7 tables file: $filename"
            );
        }

        return new HL7Tables($tables);
    }
}
