<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation;

use HL7v2\HL7Message;
use HL7v2\Validation\Finding;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

final class ValidatorFindingsTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles';

    private const MSH = "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
        . "EVN|A01|20260101120000\r";

    public function testWarningsOnlyKeepTheMessageValid(): void
    {
        $result = $this->validate(
            "PID|1||1^^^AUTH^PI||DOE^JANE||19800214|Z\r"
            . "PV1|1|I\r"
            . "ZZA|1"
        );

        $this->assertSame(
            [
                ['Warning', 'tableUser', 'PID-8', 3],
                ['Warning', 'zSegmentNotDefined', 'ZZA', 5],
            ],
            $this->summary($result)
        );
        $this->assertTrue($result->isValid());
        $this->assertSame(0, $result->getErrorCount());
    }

    public function testFindingsAreLocatedOnTheSegmentRankAndTheRepetition(): void
    {
        $result = $this->validate(
            "PID|1||1^^^AUTH^PI~2^^^AUTH^QQ||DOE^JANE\r"
            . "NK1|1|DOE^JOHN|SPO\r"
            . "NK1|2|DOE^ANN|ZZZ\r"
            . "PV1|1|I"
        );

        $this->assertSame(
            [
                ['Error', 'tableHL7', 'PID-3[2].5', 3],
                ['Warning', 'tableUser', 'NK1[2]-3', 5],
            ],
            $this->summary($result)
        );
        $this->assertFalse($result->isValid());
        $this->assertSame(1, $result->getErrorCount());
    }

    public function testSegmentLevelFindingIsLocatedOnTheSegment(): void
    {
        $result = $this->validate(
            "PID|1||1^^^AUTH^PI||DOE^JANE\r"
            . "PD1|\r"
            . "PD1|\r"
            . "PV1|1|I"
        );

        $this->assertSame([['Error', 'cardinality', 'PD1', 4]], $this->summary($result));
    }

    public function testAbsentSegmentHasNoPosition(): void
    {
        $result = $this->validate("PID|1||1^^^AUTH^PI||DOE^JANE");

        $this->assertSame(
            [
                ['Error', 'requiredAbsent', null, null],
                ['Error', 'cardinality', null, null],
            ],
            $this->summary($result)
        );
    }

    public function testFindingSeverityIsInTheTestReport(): void
    {
        $result = $this->validate(
            "PID|1||1^^^AUTH^PI||DOE^JANE||19800214|Z\r"
            . "PV1|1|I"
        );

        $entries = array_values(array_filter(
            $result->getTestReport(),
            static fn (array $entry): bool => $entry['Location'] === 'PID-8' && $entry['Type'] === 'Table'
        ));

        $this->assertCount(1, $entries);
        $this->assertFalse($entries[0]['Result']);
        $this->assertSame('Warning', $entries[0]['Severity']);
    }

    public function testValidationReportFlagsFollowTheSeverity(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(
            self::MSH
            . "PID|1||1^^^AUTH^PI~2^^^AUTH^QQ||DOE^JANE||19800214|Z\r"
            . "PV1|1|I\r"
            . "ZZA|1"
        );
        $report = $hl7->validate(self::PROFILES)->getValidationReport();

        // elementError and elementSeverity, by name; a component error is not
        // carried by its field (PID.3 (Rep. 2)).
        $this->assertSame(
            [
                'PID.3 (Rep. 1)' => [false, null],
                'PID.3 (Rep. 2)' => [false, null],
                'PID.8'          => [false, 'Warning'],
                'ZZA'            => [false, 'Warning'],
            ],
            $this->flags($report, ['PID.3 (Rep. 1)', 'PID.3 (Rep. 2)', 'PID.8', 'ZZA'])
        );
        $this->assertContains(
            ['PID-3.5', 'QQ', true, 'Error'],
            array_map(
                static fn (array $entry): array => [
                    $entry['location'] ?? null,
                    $entry['elementValue'] ?? null,
                    $entry['elementError'],
                    $entry['elementSeverity'],
                ],
                $report
            )
        );

        $pid8 = $hl7->getProfiledMessage()['segments'][2]['fields'][8][0] ?? null;
        $this->assertIsArray($pid8);
        $this->assertSame('PID-8', $pid8['LocName']);
        $this->assertFalse($pid8['hasError']);
    }

    private function validate(string $segments): ValidationResult
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::MSH . $segments);

        return $hl7->validate(self::PROFILES);
    }

    /**
     * @param array<int, array<string, mixed>> $report
     * @param list<string> $names
     *
     * @return array<string, array{mixed, mixed}> elementError and elementSeverity, by name.
     */
    private function flags(array $report, array $names): array
    {
        $flags = [];
        foreach ($report as $entry) {
            if (in_array($entry['name'], $names, true)) {
                $flags[(string) $entry['name']] = [$entry['elementError'], $entry['elementSeverity']];
            }
        }
        ksort($flags);

        return $flags;
    }

    /**
     * @return list<array{string, string, ?string, ?int}> Severity, type, short location
     *                                                   and segment position of the
     *                                                   findings other than Info.
     */
    private function summary(ValidationResult $result): array
    {
        $summary = [];
        foreach ($result->getFindings() as $finding) {
            if ($finding->severity->value === 'Info') {
                continue;
            }
            $summary[] = $this->row($finding);
        }

        return $summary;
    }

    /**
     * @return array{string, string, ?string, ?int}
     */
    private function row(Finding $finding): array
    {
        return [
            $finding->severity->value,
            $finding->type->value,
            $finding->location?->toShortString(),
            $finding->segment,
        ];
    }
}
