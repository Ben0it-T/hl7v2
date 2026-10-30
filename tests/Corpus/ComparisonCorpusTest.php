<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use HL7v2\Comparator\ComparisonResult;
use HL7v2\HL7Message;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

/**
 * Compares pairs of messages with the current Comparator.
 *
 * Layout: comparisons/<id>/left.hl7, right.hl7 and case.json (ignore rules,
 * expected result, known bug). The expected result states whether the
 * messages are identical and lists the differences (Location notation,
 * 1-based); expected.error means the ignore rules must be refused.
 *
 * Until the Comparator uses the Location notation, only the outcome
 * (identical, number of differences, error) is compared with the expected
 * result; the differences themselves are recorded in snapshots.
 */
final class ComparisonCorpusTest extends CorpusTestCase
{
    private const COMPARISONS = __DIR__ . '/comparisons';
    private const SNAPSHOTS = __DIR__ . '/snapshots/comparisons';

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        $dirs = glob(self::COMPARISONS . '/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);

        foreach ($dirs as $dir) {
            $json = file_get_contents($dir . '/case.json');
            $case = $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($case) || !isset($case['title']) || !is_array($case['expected'] ?? null)) {
                throw new RuntimeException('Comparison ' . basename($dir) . ": 'title' and 'expected' are required.");
            }
            yield basename($dir) => [basename($dir), $case];
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('cases')]
    public function testResult(string $id, array $case): void
    {
        $expected = $case['expected'];
        $knownBug = isset($case['knownBug']) ? (string) $case['knownBug'] : null;

        try {
            $result = $this->compare($id, $case);
            $actual = ['error' => false, 'identical' => $result->isIdentical(), 'differences' => $result->countDifferences()];
        } catch (Throwable $e) {
            $actual = ['error' => true, 'identical' => null, 'differences' => null];
        }

        $wanted = ($expected['error'] ?? false) === true
            ? ['error' => true, 'identical' => null, 'differences' => null]
            : ['error' => false, 'identical' => (bool) $expected['identical'], 'differences' => count($expected['differences'] ?? [])];

        if ($actual !== $wanted && $knownBug !== null) {
            $this->markTestIncomplete('Known bug: ' . $knownBug);
        }
        $this->assertSame($wanted, $actual, (string) $case['title']);
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('cases')]
    public function testSnapshot(string $id, array $case): void
    {
        try {
            $result = $this->compare($id, $case);
            $differences = [];
            foreach ($result->getDifferences() as $difference) {
                $differences[] = [
                    'path'  => $difference->getPath(),
                    'type'  => $difference->getType()->name,
                    'left'  => $difference->getLeft(),
                    'right' => $difference->getRight(),
                ];
            }
            $actual = json_encode(
                ['comparedPaths' => $result->getComparedPaths(), 'differences' => $differences],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (Throwable $e) {
            $actual = sprintf("EXCEPTION %s: %s\n", get_class($e), $e->getMessage());
        }

        $this->assertSnapshot(self::SNAPSHOTS . '/' . $id . '/differences.json', $actual);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function compare(string $id, array $case): ComparisonResult
    {
        $left = new HL7Message();
        $left->parse((string) file_get_contents(self::COMPARISONS . "/$id/left.hl7"));
        $right = new HL7Message();
        $right->parse((string) file_get_contents(self::COMPARISONS . "/$id/right.hl7"));

        return $left->compare($right, array_map('strval', $case['ignore'] ?? []));
    }
}
