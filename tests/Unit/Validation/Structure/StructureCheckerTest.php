<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Structure;

use HL7v2\HL7Message;
use HL7v2\Model\Message;
use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\Finding;
use HL7v2\Validation\Structure\StructureChecker;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Usage and cardinality of the groups and segments judged on the real
 * counts of the tree of StructureMatcher, and segments without place
 * (step 7.5). One finding per cause.
 */
final class StructureCheckerTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../../profiles-next';

    public function testValidMessagesGiveNoFinding(): void
    {
        $this->assertFindings($this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'EVN', 'PID', 'PV1'], []);
        // C1 and C5: rejected by the old validator.
        $oru = $this->profile('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $this->assertFindings($oru, ['MSH', 'PID', 'OBR', 'OBX'], []);
        $this->assertFindings($oru, ['MSH', 'ORC', 'OBR', 'OBX'], []);
    }

    public function testRequiredSegmentAbsentGivesOneFinding(): void
    {
        // Fiche required-segment-missing: R absent, no second finding for [1..1].
        $this->assertFindings($this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'EVN', 'PID'], [
            'Error requiredAbsent path=PV1 location=- segment=-',
        ]);
    }

    public function testRequiredZSegmentAbsent(): void
    {
        // Fiche zbe-missing (IHE PAM FR).
        $result = $this->check($this->profile('json-2.5-IHEPAMFR', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'EVN', 'PID', 'PV1']);

        $this->assertSame(['Error requiredAbsent path=ZBE location=- segment=-'], self::summary($result));
    }

    public function testRepetitionBeyondMaximumIsOnTheSegmentInExcess(): void
    {
        // Fiche segment-repeated-beyond-max (Q215).
        $this->assertFindings($this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'EVN', 'PID', 'PD1', 'PD1', 'PV1'], [
            'Error cardinality path=PD1[2] location=PD1[2] segment=5',
        ]);
    }

    public function testAbsentNodeHasAPathWithoutFinalIndex(): void
    {
        // C4: PID after the order opens PATIENT_RESULT[2], without ORDER_OBSERVATION.
        $this->assertFindings($this->profile('json-2.5', 'ORU', 'R01', 'ORU_R01'), ['MSH', 'ORC', 'OBR', 'OBX', 'PID'], [
            'Error requiredAbsent path=PATIENT_RESULT[2]/ORDER_OBSERVATION location=- segment=-',
        ]);
    }

    public function testSegmentsWithoutPlace(): void
    {
        $adt = $this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01');
        $this->assertFindings($adt, ['MSH', 'EVN', 'PID', 'ZZA', 'PV1'], [
            'Warning zSegmentNotDefined path=- location=ZZA segment=4',
        ]);
        $this->assertFindings($adt, ['MSH', 'EVN', 'PID', 'ORC', 'PV1'], [
            'Error segmentNotDefined path=- location=ORC segment=4',
        ]);
        // Fiche not-expected-pv1: met in the open OBSERVATION occurrence.
        $this->assertFindings($this->profile('json-2.5', 'ORU', 'R01', 'ORU_R01'), ['MSH', 'PID', 'ORC', 'OBR', 'OBX', 'PV1'], [
            'Error segmentNotExpected path=PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1] location=PV1 segment=6',
        ]);
    }

    public function testRanksCountTheSegmentsOfTheSameName(): void
    {
        $this->assertFindings($this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'EVN', 'PID', 'ZZA', 'PV1', 'ZZA'], [
            'Warning zSegmentNotDefined path=- location=ZZA segment=4',
            'Warning zSegmentNotDefined path=- location=ZZA[2] segment=6',
        ]);
    }

    public function testRequiredGroupWithOptionalContentCanBeSkipped(): void
    {
        // 7.2: its absence is not "R absent".
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::group('G', 1, 1, [self::segment('NTE', 0, 1)]),
            self::segment('PID', 1, 1),
        ]);

        $result = $this->check($root, ['MSH', 'PID']);

        $this->assertSame([], $result->getFindings());
        $this->assertContains(['Location' => 'G', 'Description' => "Group 'G' usage is R; absent.", 'Type' => 'Usage', 'Result' => true], $result->getTestReport());
    }

    public function testNotAllowedSegmentGivesOneFindingPerOccurrence(): void
    {
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::segment('PD1', 0, 0, Usage::NotSupported),
            self::segment('PID', 1, 1),
        ]);

        $this->assertFindings($root, ['MSH', 'PD1', 'PD1', 'PID'], [
            'Error notAllowedPresent path=PD1[1] location=PD1 segment=2',
            'Error notAllowedPresent path=PD1[2] location=PD1[2] segment=3',
        ]);
    }

    public function testMinimumNotMet(): void
    {
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::segment('NTE', 2, 3),
        ]);

        $result = $this->check($root, ['MSH', 'NTE']);

        $this->assertSame(['Error cardinality path=NTE location=- segment=-'], self::summary($result));
        $this->assertStringContainsString('Must have at least 2 repetition(s) (found 1)', $result->getFindings()[0]->message);
    }

    public function testGroupBeyondMaximumIsOnItsFirstSegment(): void
    {
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::group('G', 0, 1, [self::segment('OBR', 1, 1), self::segment('OBX', 0, null)]),
        ]);

        $this->assertFindings($root, ['MSH', 'OBR', 'OBX', 'OBR'], [
            'Error cardinality path=G[2] location=- segment=4',
        ]);
    }

    public function testUsageFindingsOnTheFirstOccurrenceOnly(): void
    {
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::segment('NTE', 0, null, Usage::Withdrawn),
            self::segment('ROL', 0, null, Usage::Backward),
            self::segment('OBX', 0, null, Usage::ConditionalOrEmpty),
        ]);

        $this->assertFindings($root, ['MSH', 'NTE', 'NTE', 'ROL', 'ROL', 'OBX', 'OBX'], [
            'Warning withdrawnPresent path=NTE[1] location=NTE segment=2',
            'Info backwardPresent path=ROL[1] location=ROL segment=4',
            'Info conditionalPresent path=OBX[1] location=OBX segment=6',
        ]);
    }

    public function testAlternativesNotTakenAreNotJudged(): void
    {
        $root = self::group('ROOT', 1, 1, [
            self::segment('MSH', 1, 1),
            self::group('CHOICE', 1, 1, [
                self::segment('RXO', 1, 1),
                self::segment('ODS', 1, 1),
            ], true),
        ]);

        $this->assertFindings($root, ['MSH', 'ODS'], []);
        $this->assertFindings($root, ['MSH'], [
            'Error requiredAbsent path=CHOICE location=- segment=-',
        ]);
    }

    public function testFindingsInMessageOrder(): void
    {
        $this->assertFindings($this->profile('json-2.5', 'ADT', 'A01', 'ADT_A01'), ['MSH', 'ZZA', 'PID', 'PD1', 'PD1', 'ZZB'], [
            'Warning zSegmentNotDefined path=- location=ZZA segment=2',
            'Error requiredAbsent path=EVN location=- segment=-',
            'Error cardinality path=PD1[2] location=PD1[2] segment=5',
            'Warning zSegmentNotDefined path=- location=ZZB segment=6',
            'Error requiredAbsent path=PV1 location=- segment=-',
        ]);
    }

    /**
     * @param list<string> $segments
     * @param list<string> $expected
     */
    private function assertFindings(GroupNode $root, array $segments, array $expected): void
    {
        $this->assertSame($expected, self::summary($this->check($root, $segments)));
    }

    /**
     * @param list<string> $segments
     */
    private function check(GroupNode $root, array $segments): ValidationResult
    {
        $result = new ValidationResult();
        $rules = ProfileDirectory::open(self::PROFILES . '/json-2.5')->rules;
        $occurrence = (new StructureMatcher())->match($root, $this->message($segments));
        (new StructureChecker())->check($occurrence, new CheckRecorder($rules, $result, 'TEST'));

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function summary(ValidationResult $result): array
    {
        return array_map(
            static fn (Finding $finding): string => sprintf(
                '%s %s path=%s location=%s segment=%s',
                $finding->severity->value,
                $finding->type->value,
                $finding->profilePath ?? '-',
                $finding->location?->toShortString() ?? '-',
                $finding->segment ?? '-'
            ),
            $result->getFindings()
        );
    }

    private function profile(string $directory, string $code, string $event, string $structure): GroupNode
    {
        return ProfileDirectory::open(self::PROFILES . "/$directory")->messageProfile($code, $event, $structure)->root;
    }

    /**
     * Build a message from segment names (MSH first); fields do not matter.
     *
     * @param list<string> $segments
     */
    private function message(array $segments): Message
    {
        $lines = ['MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01|1|P|2.5'];
        foreach (array_slice($segments, 1) as $name) {
            $lines[] = "$name|1";
        }

        $hl7 = new HL7Message();
        $hl7->parse(implode("\r", $lines));
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        return $message;
    }

    private static function segment(string $name, int $min, ?int $max, ?Usage $usage = null): SegmentNode
    {
        return new SegmentNode(
            $usage ?? ($min === 0 ? Usage::Optional : Usage::Required),
            new Cardinality($min, $max),
            new SegmentDefinition($name, $name, '', [])
        );
    }

    /**
     * @param list<StructureNode> $children
     */
    private static function group(string $name, int $min, ?int $max, array $children, bool $choice = false): GroupNode
    {
        return new GroupNode(
            $name,
            $min === 0 ? Usage::Optional : Usage::Required,
            new Cardinality($min, $max),
            $children,
            $choice
        );
    }
}
