<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\Profile\Definition\DataTypeDefinition;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\DefinitionReader;
use HL7v2\Profile\MessageProfile;
use HL7v2\Profile\Rules;

/**
 * A self-contained profile directory in the normalized format:
 * profiles-next/json-2.5-IHEPAMFR.
 *
 * The common files are read once, when the directory is opened; a message
 * structure is read on request. No cache: a directory opens in a few
 * milliseconds, and a batch reuses the opened directory.
 *
 * Example:
 *     $directory = ProfileDirectory::open('profiles-next/json-2.5');
 *     $profile = $directory->messageProfile('ORU', 'R01', 'ORU_R01');
 */
final readonly class ProfileDirectory
{
    /**
     * @param array<string, string> $documents Source documents by id (PAMFR, DTFR...).
     * @param array<string, array<string, string>> $messageTypes Structure by message code and trigger event.
     * @param array<string, SegmentDefinition> $segments
     * @param array<string, DataTypeDefinition> $dataTypes
     */
    private function __construct(
        public string $path,
        public string $name,
        public string $hl7Version,
        public string $base,
        public array $documents,
        public Rules $rules,
        private array $messageTypes,
        private array $segments,
        private array $dataTypes
    ) {
        //
    }

    /**
     * Open a profile directory and read its common files.
     *
     * @throws HL7Exception If the directory or a file is missing or malformed.
     */
    public static function open(string $path): self
    {
        $path = rtrim($path, '/');
        if (!is_dir($path)) {
            throw new HL7Exception("Profile directory not found: $path");
        }

        $info = DefinitionReader::profileInfo(self::readJson("$path/profile.json"), "$path/profile.json");
        $dataTypes = DefinitionReader::dataTypes(self::readJson("$path/dataTypes.json"), "$path/dataTypes.json");
        $fields = DefinitionReader::fields(self::readJson("$path/fields.json"), $dataTypes, "$path/fields.json");
        $segments = DefinitionReader::segments(self::readJson("$path/segments.json"), $fields, "$path/segments.json");

        return new self(
            $path,
            $info['name'],
            $info['hl7Version'],
            $info['base'],
            $info['documents'],
            Rules::fromArray(self::readJson("$path/rules.json"), "$path/rules.json"),
            DefinitionReader::messageTypes(self::readJson("$path/messageType.json"), "$path/messageType.json"),
            $segments,
            $dataTypes
        );
    }

    /**
     * Load the message profile of a message.
     *
     * Structure: MSH-9.3, else ACK for an acknowledgement, else messageType.json.
     * A single ACK profile, chosen by structure, whatever the trigger event.
     *
     * @throws HL7Exception If the message is unknown or its structure file
     *                      is missing or malformed.
     */
    public function messageProfile(string $messageCode, string $triggerEvent, string $structure = ''): MessageProfile
    {
        if ($structure === '') {
            $structure = $messageCode === 'ACK'
                ? 'ACK'
                : ($this->messageTypes[$messageCode][$triggerEvent]
                    ?? throw new HL7Exception(sprintf('Message structure not found for %s^%s.', $messageCode, $triggerEvent)));
        }

        $name = $messageCode === 'ACK'
            ? "ACK-ACK-$structure"
            : "$messageCode-$triggerEvent-$structure";

        $file = "{$this->path}/structures/$name.json";
        if (!is_file($file)) {
            throw new HL7Exception("Profile not found: $name");
        }

        return new MessageProfile(
            $name,
            $structure,
            DefinitionReader::structure(self::readJson($file), $structure, $this->segments, $file),
            $this
        );
    }

    /**
     * Get a segment definition of segments.json (without message overrides).
     */
    public function segment(string $name): ?SegmentDefinition
    {
        return $this->segments[$name] ?? null;
    }

    public function dataType(string $name): ?DataTypeDefinition
    {
        return $this->dataTypes[$name] ?? null;
    }

    /**
     * @return array<mixed, mixed>
     *
     * @throws HL7Exception
     */
    private static function readJson(string $file): array
    {
        if (!is_file($file)) {
            throw new HL7Exception("File not found: $file");
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new HL7Exception("Unable to read file: $file");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new HL7Exception("Invalid JSON file: $file");
        }

        return $data;
    }
}
