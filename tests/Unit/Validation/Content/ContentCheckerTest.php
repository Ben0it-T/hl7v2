<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Content;

use HL7v2\HL7Message;
use HL7v2\Model\Message;
use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\FieldDefinition;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentField;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\Content\ContentChecker;
use HL7v2\Validation\Finding;
use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\Structure\UnplacedSegment;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Content of the segments, field level: usage, count of repetitions as
 * received, HL7 length of each repetition, Explicit null. One finding per
 * cause; each finding attached to its segment element.
 */
final class ContentCheckerTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../../profiles-next';

    /**
     * Fields of the PID segment used by the tests:
     * 1 R [1..1] length 1; 2 O [0..2] length 5; 3 X [0..0]; 4 W; 5 B; 6 C; 7 CE;
     * 8 R [2..3], no length.
     */
    private static function pid(): SegmentDefinition
    {
        $field = static fn (int $position, Usage $usage, int $min, ?int $max, ?int $length = null): SegmentField =>
            new SegmentField($position, $usage, new Cardinality($min, $max), new FieldDefinition(
                "PID.$position", '00000', 'ST', $length, null, "Field $position", '3'
            ));

        return new SegmentDefinition('PID', 'Patient Identification', '3', [
            $field(1, Usage::Required, 1, 1, 1),
            $field(2, Usage::Optional, 0, 2, 5),
            $field(3, Usage::NotSupported, 0, 0),
            $field(4, Usage::Withdrawn, 0, 1),
            $field(5, Usage::Backward, 0, 1),
            $field(6, Usage::Conditional, 0, 1),
            $field(7, Usage::ConditionalOrEmpty, 0, 1),
            $field(8, Usage::Required, 2, 3),
        ]);
    }

    public function testValidSegment(): void
    {
        [$outcome] = $this->check('PID|A|ABCDE~AB||||||X~Y');

        $this->assertSame([], self::summary($outcome));
    }

    public function testRequiredFieldAbsentGivesOneFinding(): void
    {
        [$outcome, $profiled] = $this->check('PID||||||||X~Y');

        $this->assertSame(['Error requiredAbsent PID-1'], self::summary($outcome));
        $this->assertSame($outcome->getFindings(), $profiled->findingsOf($this->pidElement($profiled)));
    }

    public function testRequiredFieldWithEmptyComponentsIsAbsent(): void
    {
        [$outcome] = $this->check('PID|^&^|||||||~');

        $this->assertSame(['Error requiredAbsent PID-1', 'Error requiredAbsent PID-8'], self::summary($outcome));
    }

    public function testExplicitNullSatisfiesRequiredAndHasNoLength(): void
    {
        // "" has 2 characters, beyond the length of PID-1 (1): not checked.
        [$outcome] = $this->check('PID|""|""||||||X~""');

        $this->assertSame([], self::summary($outcome));
    }

    public function testNotAllowedFieldPresentGivesOneFinding(): void
    {
        // Beyond its maximum (0) and with a value: notAllowedPresent only.
        [$outcome] = $this->check('PID|A||X~Y|||||X~Y');

        $this->assertSame(['Error notAllowedPresent PID-3'], self::summary($outcome));
    }

    public function testNotAllowedFieldWithExplicitNull(): void
    {
        [$outcome] = $this->check('PID|A||""|||||X~Y');

        $this->assertSame(['Error notAllowedPresent PID-3'], self::summary($outcome));
    }

    public function testWithdrawnBackwardAndConditionalPresent(): void
    {
        [$outcome] = $this->check('PID|A|||W|B|C|CE|X~Y');

        $this->assertSame([
            'Warning withdrawnPresent PID-4',
            'Info backwardPresent PID-5',
            'Info conditionalPresent PID-6',
            'Info conditionalPresent PID-7',
        ], self::summary($outcome));
    }

    public function testRepetitionsBeyondTheMaximum(): void
    {
        // The empty repetition in the middle counts: 3 repetitions received.
        [$outcome] = $this->check('PID|A|AB~~CD||||||X~Y');

        $this->assertSame(['Error cardinality PID-2'], self::summary($outcome));
        $this->assertStringContainsString('(found 3)', $outcome->getFindings()[0]->message);
    }

    public function testRequiredFieldUnderItsMinimum(): void
    {
        [$outcome] = $this->check('PID|A|||||||X');

        $this->assertSame(['Error cardinality PID-8'], self::summary($outcome));
        $this->assertStringContainsString('at least 2 repetition(s) (found 1)', $outcome->getFindings()[0]->message);
    }

    public function testLengthOfEachRepetition(): void
    {
        [$outcome] = $this->check('PID|AB|ABCDEF~ABCDEFG||||||X~Y');

        $this->assertSame([
            'Error length PID-1',
            'Error length PID-2',
            'Error length PID-2[2]',
        ], self::summary($outcome));
    }

    public function testLengthIsTheHl7Length(): void
    {
        // A\F\BCD: 5 characters of data (\F\ counts 1); ABCD\F\: 5 too;
        // ABCDE\F\: 6. The separators of a repetition count 1 each (A^B&C: 5).
        [$outcome] = $this->check('PID|A|A\F\BCD~ABCD\F\||||||X~Y');
        $this->assertSame([], self::summary($outcome));

        [$outcome] = $this->check('PID|A|ABCDE\F\~A^B&C||||||X~Y');
        $this->assertSame(['Error length PID-2'], self::summary($outcome));
        $this->assertStringContainsString('length (6) exceeds', $outcome->getFindings()[0]->message);
    }

    public function testPassingChecksAreInTheTestReport(): void
    {
        [$outcome] = $this->check('PID|A|AB||||||X~Y');

        $entries = array_map(
            static fn (array $entry): string => "{$entry['Type']} {$entry['Location']} " . ($entry['Result'] ? 'ok' : 'ko'),
            $outcome->getTestReport()
        );
        $this->assertContains('Usage PID-1 ok', $entries);
        $this->assertContains('Cardinality PID-1 ok', $entries);
        $this->assertContains('Length PID-1 ok', $entries);
        $this->assertContains('Usage PID-3 ok', $entries); // X, absent.
        $this->assertContains('Cardinality PID-8 ok', $entries);
        $this->assertNotContains('Length PID-8 ok', $entries); // No length in the profile.
    }

    public function testFindingsHaveThePositionsOfTheSegment(): void
    {
        [$outcome] = $this->check('PID||||||||X~Y');

        $finding = $outcome->getFindings()[0];
        $this->assertSame('PID[1]', $finding->profilePath);
        $this->assertSame(2, $finding->segment);
        $this->assertSame('PID[1]-1[1]', $finding->location?->toLongString());
    }

    public function testNotExpectedSegmentIsCheckedAgainstItsNearestDefinition(): void
    {
        // The second PID, after PV1, has no place: it is judged as a PID.
        [$outcome, $profiled] = $this->check("PID|A|||||||X~Y\rPV1|1\rPID||||||||X~Y");

        $unplaced = $profiled->root->segments()[3];
        $this->assertInstanceOf(UnplacedSegment::class, $unplaced);
        $this->assertSame(['Error requiredAbsent PID[2]-1'], self::summary($outcome, $profiled->findingsOf($unplaced)));
        $this->assertSame(4, $profiled->findingsOf($unplaced)[0]->segment);
        $this->assertNull($profiled->findingsOf($unplaced)[0]->profilePath);
    }

    public function testNotDefinedSegmentIsNotChecked(): void
    {
        [$outcome] = $this->check("PID|A|||||||X~Y\rZZZ|1|2|3");

        $this->assertSame([], self::summary($outcome));
    }

    /**
     * Run the content checks on a message MSH, then the given segments, on
     * the structure MSH, PID, [PV1].
     *
     * @return array{0: ValidationResult, 1: ProfiledMessage}
     */
    private function check(string $segments): array
    {
        $structure = new GroupNode('ROOT', Usage::Required, new Cardinality(1, 1), [
            new SegmentNode(Usage::Required, new Cardinality(1, 1), new SegmentDefinition('MSH', 'MSH', '2', [])),
            new SegmentNode(Usage::Required, new Cardinality(1, 1), self::pid()),
            new SegmentNode(Usage::Optional, new Cardinality(0, 1), new SegmentDefinition('PV1', 'PV1', '3', [])),
        ]);

        $message = $this->parse("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01|1|P|2.5\r" . $segments);
        $result = new ValidationResult();
        $recorder = new CheckRecorder(ProfileDirectory::open(self::PROFILES . '/json-2.5')->rules, $result, 'ADT_A01');
        $profiled = new ProfiledMessage((new StructureMatcher())->match($structure, $message));

        (new ContentChecker())->check($profiled, $message, $recorder);

        return [$result, $profiled];
    }

    private function parse(string $hl7): Message
    {
        $parser = new HL7Message();
        $parser->parse($hl7);
        $message = $parser->getMessage();
        $this->assertNotNull($message);

        return $message;
    }

    private function pidElement(ProfiledMessage $profiled): SegmentOccurrence
    {
        $pid = $profiled->root->segments()[1];
        $this->assertInstanceOf(SegmentOccurrence::class, $pid);

        return $pid;
    }

    /**
     * @param list<Finding>|null $findings Findings to summarize; all the findings of the result by default.
     *
     * @return list<string>
     */
    private static function summary(ValidationResult $result, ?array $findings = null): array
    {
        return array_map(
            static fn (Finding $finding): string => sprintf(
                '%s %s %s',
                $finding->severity->value,
                $finding->type->value,
                $finding->location?->toShortString() ?? '-'
            ),
            $findings ?? $result->getFindings()
        );
    }
}
