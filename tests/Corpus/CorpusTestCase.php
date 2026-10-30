<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use HL7v2\HL7Message;
use Throwable;
use HL7v2\Profile\HL7TableLoader;
use HL7v2\Profile\ProfileLoader;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Validates corpus cases with the current validator, against the profiles
 * in profiles/ (old format), and compares its outputs with snapshots.
 *
 * Snapshots record what the current code does, not what the specification
 * requires (see expected and knownBug in the case files). Run the suite with
 * UPDATE_SNAPSHOTS=1 to write them; a missing snapshot makes the test fail.
 */
abstract class CorpusTestCase extends TestCase
{
    protected const PROFILES = __DIR__ . '/../../profiles';

    protected function validate(CorpusCase $case): ValidationResult
    {
        $result = $this->validateMessage($case)->getValidationResult();
        $this->assertNotNull($result);

        return $result;
    }

    /**
     * Parse and validate the message of a case.
     */
    protected function validateMessage(CorpusCase $case): HL7Message
    {
        $hl7 = new HL7Message();
        $hl7->parse($case->message);
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        $profile = (new ProfileLoader(self::PROFILES))->load(
            $case->profileId,
            $message->getMessageCode(),
            $message->getTriggerEvent(),
            $message->getStructure()
        );
        $tables = (new HL7TableLoader(self::PROFILES))->load($case->profileId);
        $hl7->validateWith($profile, $tables);

        return $hl7;
    }

    /**
     * Compare the outputs of the validated message with the snapshots of
     * the directory. With a location (for example PID-5), only the TestReport
     * entries of that field are kept, and no other output is compared.
     */
    protected function assertSnapshots(HL7Message $hl7, string $dir, ?string $location = null): void
    {
        $result = $hl7->getValidationResult();
        $this->assertNotNull($result);

        $report = $result->getTestReport();
        if ($location !== null) {
            $report = array_values(array_filter(
                $report,
                static fn (array $entry): bool => in_array(
                    true,
                    [$entry['Location'] === $location, str_starts_with((string) $entry['Location'], $location . '.')],
                    true
                )
            ));
        }

        $outputs = ['test-report.json' => fn (): string => $this->json($report)];
        if ($location === null) {
            $outputs += [
                'validation-report.json' => fn (): string => $this->json($result->getValidationReport()),
                'profiled-message.json'  => fn (): string => $this->json($hl7->getProfiledMessage()),
                'message.hl7'            => fn (): string => $hl7->toHl7(),
                'structural.xml'         => fn (): string => $hl7->toStructuralXml(),
                'datatype.xml'           => fn (): string => $hl7->toDatatypeXml(),
            ];
        }

        foreach ($outputs as $file => $output) {
            try {
                $actual = $output();
            } catch (Throwable $e) {
                $actual = sprintf("EXCEPTION %s: %s\n", get_class($e), $e->getMessage());
            }
            $this->assertSnapshot($dir . '/' . $file, $actual);
        }
    }

    protected function assertSnapshot(string $file, string $actual): void
    {
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0777, true)) {
                $this->fail("Cannot create the snapshot directory of $file");
            }
            file_put_contents($file, $actual);
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists($file, 'Snapshot missing: run the suite with UPDATE_SNAPSHOTS=1.');
        $this->assertSame((string) file_get_contents($file), $actual, "Snapshot differs: $file");
    }

    /**
     * Invalid UTF-8 (a message read in another charset) is substituted, so
     * the snapshot stays readable and shows the change once the parser
     * converts to UTF-8.
     *
     * @param array<mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * Compare the validity with the expected one. When they differ and the
     * case documents a known bug, the test is marked incomplete instead of
     * failing, so the bug is never frozen as expected.
     */
    protected function assertExpectedValidity(CorpusCase $case, ValidationResult $result): void
    {
        if ($result->isValid() !== $case->expectedValid && $case->knownBug !== null) {
            $this->markTestIncomplete('Known bug: ' . $case->knownBug);
        }

        $this->assertSame(
            $case->expectedValid,
            $result->isValid(),
            $case->title . "\n" . $this->describe($result->getTestReport())
        );
    }

    /**
     * @param array<int, array<string, mixed>> $report
     */
    private function describe(array $report): string
    {
        $lines = [];
        foreach ($report as $entry) {
            if (($entry['Result'] ?? true) === false) {
                $lines[] = sprintf('- %s: %s', (string) ($entry['Location'] ?? ''), (string) ($entry['Description'] ?? ''));
            }
        }

        return implode("\n", $lines);
    }
}
