<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use RuntimeException;

/**
 * One corpus case: a forged HL7 message and its case file.
 *
 * Layout: messages/json-<profile>/<structure>/<id>.hl7 and <id>.json.
 * The profile directory name gives the profile the message is validated
 * against (profiles/json-<profile>).
 */
final class CorpusCase
{
    /**
     * @param list<string> $tests
     * @param list<string> $references
     * @param list<array<string, mixed>> $expectedFindings
     */
    private function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $message,
        public readonly string $title,
        public readonly array $tests,
        public readonly array $references,
        public readonly ?bool $expectedValid,
        public readonly array $expectedFindings,
        public readonly ?string $knownBug,
        public readonly ?string $location = null
    ) {
    }

    /**
     * Load every case below a messages directory.
     *
     * @return array<string, CorpusCase> Cases keyed by id.
     */
    public static function loadAll(string $messagesDir): array
    {
        $cases = [];
        $files = glob($messagesDir . '/json-*/*/*.hl7') ?: [];
        sort($files);

        foreach ($files as $file) {
            $case = self::load($messagesDir, $file);
            $cases[$case->id] = $case;
        }

        return $cases;
    }

    private static function load(string $messagesDir, string $hl7File): self
    {
        $id = substr($hl7File, strlen($messagesDir) + 1, -strlen('.hl7'));
        $profileDir = explode('/', $id)[0];
        $jsonFile = substr($hl7File, 0, -strlen('.hl7')) . '.json';

        $message = file_get_contents($hl7File);
        $json = is_file($jsonFile) ? file_get_contents($jsonFile) : false;
        if ($message === false || $json === false) {
            throw new RuntimeException("Case $id: message or case file missing.");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException("Case $id: invalid case file.");
        }

        return self::fromArray($id, substr($profileDir, strlen('json-')), $message, $data);
    }

    /**
     * Build a case from the content of a case file.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(string $id, string $profileId, string $message, array $data): self
    {
        if (!isset($data['title']) || !is_array($data['expected'] ?? null) || !array_key_exists('valid', $data['expected'])) {
            throw new RuntimeException("Case $id: 'title' and 'expected.valid' are required.");
        }

        return new self(
            $id,
            $profileId,
            $message,
            (string) $data['title'],
            array_values(array_map('strval', $data['tests'] ?? [])),
            array_values(array_map('strval', $data['references'] ?? [])),
            $data['expected']['valid'] === null ? null : (bool) $data['expected']['valid'],
            array_values($data['expected']['findings'] ?? []),
            isset($data['knownBug']) ? (string) $data['knownBug'] : null,
            isset($data['location']) ? (string) $data['location'] : null
        );
    }
}
