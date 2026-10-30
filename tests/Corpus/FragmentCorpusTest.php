<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Runs isolated values (fragments) through the current validator.
 *
 * Each value of fragments/json-<profile>/<datatype>.json replaces a field of
 * the carrier message carriers/json-<profile>.hl7, at the location given by
 * the fragment file (for example PID-5). The carrier alone is valid.
 */
final class FragmentCorpusTest extends CorpusTestCase
{
    private const FRAGMENTS = __DIR__ . '/fragments';
    private const CARRIERS = __DIR__ . '/carriers';
    private const SNAPSHOTS = __DIR__ . '/snapshots/fragments';

    /**
     * @return iterable<string, array{CorpusCase}>
     */
    public static function cases(): iterable
    {
        $files = glob(self::FRAGMENTS . '/json-*/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $profileDir = basename(dirname($file));
            $datatype = basename($file, '.json');
            $carrier = file_get_contents(self::CARRIERS . '/' . $profileDir . '.hl7');
            $json = file_get_contents($file);
            if ($carrier === false || $json === false) {
                throw new RuntimeException("Fragments $profileDir/$datatype: carrier or fragment file missing.");
            }

            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['location'], $data['cases']) || !is_array($data['cases'])) {
                throw new RuntimeException("Fragments $profileDir/$datatype: 'location' and 'cases' are required.");
            }

            foreach ($data['cases'] as $index => $fragment) {
                $id = sprintf('%s/%s#%d', $profileDir, $datatype, $index + 1);
                yield $id => [CorpusCase::fromArray(
                    $id,
                    substr($profileDir, strlen('json-')),
                    self::inject($carrier, (string) $data['location'], (string) $fragment['value']),
                    [
                        'title'      => sprintf('%s = %s', $data['location'], $fragment['value']),
                        'tests'      => $fragment['tests'] ?? [],
                        'references' => $data['references'] ?? [],
                        'expected'   => $fragment['expected'] ?? [],
                        'knownBug'   => $fragment['knownBug'] ?? null,
                        'location'   => $data['location'],
                    ]
                )];
            }
        }
    }

    #[DataProvider('cases')]
    public function testValidity(CorpusCase $case): void
    {
        $this->assertNotNull($case->expectedValid, 'A fragment must state expected.valid.');
        $this->assertExpectedValidity($case, $this->validate($case));
    }

    #[DataProvider('cases')]
    public function testSnapshot(CorpusCase $case): void
    {
        $this->assertSnapshots(
            $this->validateMessage($case),
            self::SNAPSHOTS . '/' . str_replace('#', '/', $case->id),
            $case->location
        );
    }

    /**
     * Replace a field (first occurrence of the segment) of the carrier message.
     */
    private static function inject(string $carrier, string $location, string $value): string
    {
        if (preg_match('/^([A-Z][A-Z0-9]{2})-(\d+)$/', $location, $m) !== 1) {
            throw new RuntimeException("Unsupported fragment location: $location");
        }
        [, $segmentName, $field] = $m;

        $segments = explode("\r", $carrier);
        foreach ($segments as $i => $segment) {
            $fields = explode('|', $segment);
            if ($fields[0] !== $segmentName) {
                continue;
            }
            // MSH-1 is the field separator itself: MSH-n is at index n - 1.
            $index = $segmentName === 'MSH' ? (int) $field - 1 : (int) $field;
            $fields = array_pad($fields, $index + 1, '');
            $fields[$index] = $value;
            $segments[$i] = implode('|', $fields);

            return implode("\r", $segments);
        }

        throw new RuntimeException("Segment $segmentName not found in the carrier message.");
    }
}
