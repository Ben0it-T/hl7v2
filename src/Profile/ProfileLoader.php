<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Profile\Profile;
use HL7v2\Exception\HL7Exception;

class ProfileLoader
{
    private string $profilePath;

    public function __construct(string $profilePath)
    {
        $this->profilePath = rtrim($profilePath, '/');
    }

    /**
     * Load HL7 message profile.
     *
     * @return Profile
     * @throws HL7Exception
     */
    public function load(
        string $versionId,
        string $messageCode,
        string $triggerEvent,
        string $structure
    ): Profile {

        if ($structure === '') {
            $structure = $this->resolveStructure(
                $versionId,
                $messageCode,
                $triggerEvent
            );
        }

        $profileName = sprintf('%s-%s-%s', $messageCode, $triggerEvent, $structure);

        $filename = sprintf( '%s/json-%s/%s.json', $this->profilePath, $versionId, $profileName);
        if (!is_file($filename)) {
            throw new HL7Exception(
                "Profile not found: $profileName"
            );
        }

        $profile = $this->readJsonFile($filename);

        return new Profile($profile);
    }

    /**
     * Resolve structure from messageType.json.
     *
     * @throws HL7Exception
     */
    private function resolveStructure(
        string $versionId,
        string $messageCode,
        string $triggerEvent
    ): string {

        $filename = sprintf('%s/json-%s/messageType.json', $this->profilePath, $versionId);
        if (!is_file($filename)) {
            throw new HL7Exception(
                'messageType.json not found.'
            );
        }

        $definition = $this->readJsonFile($filename);

        if (!isset($definition[$messageCode]) || !isset($definition[$messageCode][$triggerEvent])) {
            throw new HL7Exception(
                sprintf('Message structure not found for %s^%s.', $messageCode, $triggerEvent)
            );
        }

        return $definition[$messageCode][$triggerEvent];
    }

    /**
     * Read JSON file.
     *
     * @param string $filename
     * @return array<mixed,mixed>
     * @throws HL7Exception
     */
    private function readJsonFile(string $filename): array
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new HL7Exception(
                "Unable to read file: $filename"
            );
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new HL7Exception(
                "Invalid JSON file: $filename"
            );
        }

        return $data;
    }
}
