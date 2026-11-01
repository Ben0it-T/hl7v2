<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation;

use HL7v2\Model\Location;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Severity;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Single recording method of the checks (Q196, step 7.5): a TestReport entry
 * for each check, a Finding with the severity of rules.json for each check
 * that gives one.
 */
final class CheckRecorderTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles-next';

    public function testPassedCheckGivesOnlyATestReportEntry(): void
    {
        [$recorder, $result] = $this->recorder();

        $finding = $recorder->record(new Check('Usage', true, "Segment 'PID' usage is R.", null, new Location('PID'), 'PID[1]', 3));

        $this->assertNull($finding);
        $this->assertSame([], $result->getFindings());
        $this->assertSame([[
            'Location'    => 'PID',
            'Description' => "Segment 'PID' usage is R.",
            'Type'        => 'Usage',
            'Result'      => true,
        ]], $result->getTestReport());
    }

    public function testFailedCheckGivesAFindingWithTheSeverityOfTheRules(): void
    {
        [$recorder, $result] = $this->recorder();

        $finding = $recorder->record(new Check(
            'Structure', false, "Segment 'ZZA' is not defined in the message structure.",
            FindingType::ZSegmentNotDefined, new Location('ZZA', 2), null, 7
        ));

        $this->assertNotNull($finding);
        $this->assertSame([$finding], $result->getFindings());
        $this->assertSame([
            'severity'    => 'Warning',
            'type'        => 'zSegmentNotDefined',
            'location'    => 'ZZA[2]',
            'profilePath' => null,
            'segment'     => 7,
            'message'     => "Segment 'ZZA' is not defined in the message structure.",
        ], $finding->toArray());
        $this->assertSame('Warning', $result->getTestReport()[0]['Severity']);
        $this->assertFalse($result->getTestReport()[0]['Result']);
        $this->assertTrue($result->isValid());
    }

    public function testPassedCheckMayGiveAnInfo(): void
    {
        [$recorder, $result] = $this->recorder();

        $finding = $recorder->record(new Check('Conditional', true, 'C present.', FindingType::ConditionalPresent));

        $this->assertSame(Severity::Info, $finding?->severity);
        $this->assertTrue($result->getTestReport()[0]['Result']);
        $this->assertSame('Info', $result->getTestReport()[0]['Severity']);
    }

    public function testTestReportLocationFallsBackOnPathThenStructure(): void
    {
        [$recorder, $result] = $this->recorder();

        $recorder->record(new Check('Required element', false, 'PV1 required.', FindingType::RequiredAbsent, null, 'PV1'));
        $recorder->record(new Check('Usage', true, 'No position.'));

        $this->assertSame(['PV1', 'ADT_A01'], array_column($result->getTestReport(), 'Location'));
        $this->assertSame(1, $result->getErrorCount());
    }

    public function testFailedCheckWithoutFindingIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Check('Usage', false, 'Failed without finding.');
    }

    /**
     * @return array{0: CheckRecorder, 1: ValidationResult}
     */
    private function recorder(): array
    {
        $result = new ValidationResult();
        $rules = ProfileDirectory::open(self::PROFILES . '/json-2.5')->rules;

        return [new CheckRecorder($rules, $result, 'ADT_A01'), $result];
    }
}
