<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation;

use HL7v2\Model\Location;
use HL7v2\Validation\Finding;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FindingTest extends TestCase
{
    private const PROFILES = [
        __DIR__ . '/../../../profiles',
        __DIR__ . '/../../../profiles-next',
    ];

    public function testFindingOnAnElement(): void
    {
        $finding = new Finding(
            Severity::Error,
            FindingType::RequiredAbsent,
            "Field 'Patient Identifier List' (PID.3) is required.",
            Location::parse('PID-3'),
            'PID',
            3
        );

        $this->assertTrue($finding->isError());
        $this->assertSame(
            [
                'severity'    => 'Error',
                'type'        => 'requiredAbsent',
                'location'    => 'PID-3',
                'profilePath' => 'PID',
                'segment'     => 3,
                'message'     => "Field 'Patient Identifier List' (PID.3) is required.",
            ],
            $finding->toArray()
        );
    }

    public function testFindingWithoutPosition(): void
    {
        $finding = new Finding(Severity::Error, FindingType::ParseIssue, 'The message is not valid UTF-8.');

        $this->assertNull($finding->location);
        $this->assertNull($finding->profilePath);
        $this->assertNull($finding->segment);
        $this->assertNull($finding->toArray()['location']);
    }

    public function testOnlyErrorsAreErrors(): void
    {
        $this->assertFalse((new Finding(Severity::Warning, FindingType::ZSegmentNotDefined, 'ZZA'))->isError());
        $this->assertFalse((new Finding(Severity::Info, FindingType::TableUnavailable, '0010'))->isError());
    }

    public function testSeverityValuesAreTheOnesOfRulesJson(): void
    {
        $this->assertSame(['Error', 'Warning', 'Info'], array_map(
            static fn (Severity $severity): string => $severity->value,
            Severity::cases()
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rulesFiles(): iterable
    {
        foreach (self::PROFILES as $profiles) {
            foreach (glob($profiles . '/json-*/rules.json') ?: [] as $file) {
                yield basename($profiles) . '/' . basename(dirname($file)) => [$file];
            }
        }
    }

    /**
     * Every rules.json gives a severity to every finding type, and to nothing else.
     */
    #[DataProvider('rulesFiles')]
    public function testRulesJsonCoversEveryFindingType(string $file): void
    {
        $rules = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($rules);
        $this->assertIsArray($rules['severities'] ?? null);

        $types = array_map(static fn (FindingType $type): string => $type->value, FindingType::cases());
        $this->assertSame($types, array_keys($rules['severities']), 'Same keys, in the order of FindingType.');

        foreach ($rules['severities'] as $type => $severity) {
            $this->assertNotNull(Severity::tryFrom((string) $severity), "Unknown severity for $type.");
        }
    }
}
