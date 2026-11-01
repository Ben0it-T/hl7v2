<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use HL7v2\HL7Message;
use Throwable;
use HL7v2\Profile\HL7TableLoader;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Profile\ProfileLoader;
use HL7v2\Validation\Finding;
use HL7v2\Validation\MessageValidator;
use HL7v2\Validation\Severity;
use HL7v2\Validation\ValidationOutcome;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Validates corpus cases with the current validator, against the profiles
 * in profiles/ (old format), and compares its outputs with snapshots.
 *
 * Shadow mode: the new validator (MessageValidator, profiles in
 * profiles-next/) runs on the same cases; its findings are compared with
 * the expected ones within what it checks so far, and recorded in
 * findings-next.json.
 *
 * Snapshots record what the current code does, not what the specification
 * requires (see expected and knownBug in the case files). Run the suite with
 * UPDATE_SNAPSHOTS=1 to write them; a missing snapshot makes the test fail.
 */
abstract class CorpusTestCase extends TestCase
{
    protected const PROFILES = __DIR__ . '/../../profiles';
    protected const PROFILES_NEXT = __DIR__ . '/../../profiles-next';

    /**
     * Profile directories of profiles-next/ already opened, by profile id.
     *
     * @var array<string, ProfileDirectory>
     */
    private static array $directories = [];

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
     * Parse the message of a case and validate it with the new validator,
     * against profiles-next/json-<profile>.
     */
    protected function validateNext(CorpusCase $case): ValidationOutcome
    {
        $hl7 = new HL7Message();
        $hl7->parse($case->message);
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        $directory = self::$directories[$case->profileId]
            ??= ProfileDirectory::open(self::PROFILES_NEXT . '/json-' . $case->profileId);
        $profile = $directory->messageProfile(
            $message->getMessageCode(),
            $message->getTriggerEvent(),
            $message->getStructure()
        );

        return (new MessageValidator())->validate($message, $profile);
    }

    /**
     * Compare the findings of the new validator with the snapshot
     * findings-next.json of the directory (every finding, Infos included).
     */
    protected function assertNextSnapshot(ValidationOutcome $outcome, string $dir): void
    {
        $this->assertSnapshot($dir . '/findings-next.json', $this->json(array_map(
            static fn (Finding $finding): array => $finding->toArray(),
            $outcome->result->getFindings()
        )));
    }

    /**
     * Compare the findings of the new validator with the expected ones,
     * within what it checks so far: the findings on the structure, that is
     * without location or located on a segment (PD1[2]), Errors and Warnings
     * only. The path is compared when the case gives it. knownBug describes
     * the current validator, not the new one: any difference fails.
     */
    protected function assertExpectedStructureFindings(CorpusCase $case, ValidationOutcome $outcome): void
    {
        $onSegment = static fn (?string $location): bool => $location === null
            || preg_match('/^[A-Z][A-Z0-9]{2}(\[\d+\])?$/', $location) === 1;

        $unexpected = array_values(array_filter(
            $outcome->result->getFindings(),
            static fn (Finding $finding): bool => $finding->severity !== Severity::Info
                && $onSegment($finding->location?->toShortString())
        ));
        $expectedFindings = array_values(array_filter(
            $case->expectedFindings,
            static fn (array $expected): bool => $onSegment(isset($expected['location']) ? (string) $expected['location'] : null)
        ));

        $missing = [];
        foreach ($expectedFindings as $expected) {
            foreach ($unexpected as $i => $finding) {
                if ($this->findingMatches($expected, $finding)
                    && (!array_key_exists('path', $expected) || $expected['path'] === $finding->profilePath)
                ) {
                    unset($unexpected[$i]);
                    continue 2;
                }
            }
            $missing[] = $expected;
        }

        $this->assertTrue(
            $missing === [] && $unexpected === [],
            sprintf(
                "%s\nMissing findings:\n%s\nUnexpected findings:\n%s",
                $case->title,
                implode("\n", array_map(
                    static fn (array $expected): string => '- ' . json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $missing
                )),
                implode("\n", array_map(
                    static fn (Finding $finding): string => sprintf(
                        '- %s %s %s %s (segment %s): %s',
                        $finding->severity->value,
                        $finding->type->value,
                        $finding->profilePath ?? '-',
                        $finding->location?->toShortString() ?? '-',
                        $finding->segment ?? '-',
                        $finding->message
                    ),
                    $unexpected
                ))
            )
        );
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
                'findings.json'          => fn (): string => $this->json(array_map(
                    static fn (Finding $finding): array => $finding->toArray(),
                    $hl7->getFindings()
                )),
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
        // No assertSame(): on large snapshots, the diff computed by PHPUnit
        // takes minutes. The differences are read with review-snapshots.php.
        $this->assertTrue(
            (string) file_get_contents($file) === $actual,
            "Snapshot differs: $file\nWrite the new snapshots with UPDATE_SNAPSHOTS=1, then review them with php tests/Corpus/review-snapshots.php."
        );
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
     * Compare the findings with the expected ones, Errors and Warnings only
     * (Infos describe the tool, not the specification). Each expected finding
     * must match one actual finding of the same severity, on the positions it
     * gives: location, and segment when given; an expected finding with only
     * a path matches a finding without location (the path is not compared:
     * the current validator does not give it). An unexpected Error or Warning
     * fails the test. When the findings differ and the case documents a known
     * bug, the test is marked incomplete instead of failing.
     */
    protected function assertExpectedFindings(CorpusCase $case, ValidationResult $result): void
    {
        $unexpected = array_values(array_filter(
            $result->getFindings(),
            static fn (Finding $finding): bool => $finding->severity !== Severity::Info
        ));

        $missing = [];
        foreach ($case->expectedFindings as $expected) {
            foreach ($unexpected as $i => $finding) {
                if ($this->findingMatches($expected, $finding)) {
                    unset($unexpected[$i]);
                    continue 2;
                }
            }
            $missing[] = $expected;
        }

        if (($missing !== [] || $unexpected !== []) && $case->knownBug !== null) {
            $this->markTestIncomplete('Known bug: ' . $case->knownBug);
        }

        $this->assertTrue(
            $missing === [] && $unexpected === [],
            sprintf(
                "%s\nMissing findings:\n%s\nUnexpected findings:\n%s",
                $case->title,
                implode("\n", array_map(
                    static fn (array $expected): string => '- ' . json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $missing
                )),
                implode("\n", array_map(
                    static fn (Finding $finding): string => sprintf(
                        '- %s %s %s (segment %s): %s',
                        $finding->severity->value,
                        $finding->type->value,
                        $finding->location?->toShortString() ?? '-',
                        $finding->segment ?? '-',
                        $finding->message
                    ),
                    $unexpected
                ))
            )
        );
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function findingMatches(array $expected, Finding $finding): bool
    {
        $location = $finding->location?->toShortString();

        if (($expected['severity'] ?? null) !== $finding->severity->value) {
            return false;
        }
        if (array_key_exists('location', $expected) && $expected['location'] !== $location) {
            return false;
        }
        if (array_key_exists('segment', $expected) && $expected['segment'] !== $finding->segment) {
            return false;
        }
        if (!array_key_exists('location', $expected) && !array_key_exists('segment', $expected)) {
            return $location === null;
        }

        return true;
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
