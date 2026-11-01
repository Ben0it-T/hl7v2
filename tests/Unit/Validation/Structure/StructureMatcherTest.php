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
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\Structure\UnplacedSegment;
use PHPUnit\Framework\TestCase;

/**
 * Placement of the segments of a message on the nodes of its message
 * structure. Only segment names matter here.
 */
final class StructureMatcherTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../../profiles-next';

    public function testC1OrderWithoutOrc(): void
    {
        $this->assertPlacement('json-2.5', 'ORU', 'R01', 'ORU_R01', ['MSH', 'PID', 'OBR', 'OBX'], [
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
        ]);
    }

    public function testC5ResultWithoutPatient(): void
    {
        $this->assertPlacement('json-2.5', 'ORU', 'R01', 'ORU_R01', ['MSH', 'ORC', 'OBR', 'OBX'], [
            'MSH[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/ORC[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
        ]);
    }

    public function testC6ThreeNteNodes(): void
    {
        $root = $this->structure('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'PID', 'NTE', 'ORC', 'OBR', 'NTE', 'OBX', 'NTE']));

        $this->assertSame([
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/NTE[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/ORC[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/NTE[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/NTE[1]',
        ], self::placement($occurrence));

        // Each NTE is attached to its own node of the profile, not to a name.
        $ntes = array_values(array_filter(
            $occurrence->segments(),
            static fn (SegmentOccurrence|UnplacedSegment $item): bool => $item->segment->getName() === 'NTE'
        ));
        $this->assertCount(3, $ntes);
        $nodes = array_map(static fn (SegmentOccurrence|UnplacedSegment $item): ?SegmentNode => $item instanceof SegmentOccurrence ? $item->node : null, $ntes);
        $this->assertNotSame($nodes[0], $nodes[1]);
        $this->assertNotSame($nodes[1], $nodes[2]);
        $this->assertNotSame($nodes[0], $nodes[2]);
    }

    public function testRepetitionsAreCountedAndGreedy(): void
    {
        // The second OBR opens a new ORDER_OBSERVATION in the same
        // PATIENT_RESULT (innermost element first).
        $this->assertPlacement('json-2.5', 'ORU', 'R01', 'ORU_R01', ['MSH', 'PID', 'OBR', 'OBX', 'OBX', 'OBR', 'OBX'], [
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[2]/OBX[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[2]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[2]/OBSERVATION[1]/OBX[1]',
        ]);
    }

    public function testNewPatientOpensANewPatientResult(): void
    {
        $this->assertPlacement('json-2.5', 'ORU', 'R01', 'ORU_R01', ['MSH', 'PID', 'OBR', 'PID', 'OBR'], [
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[2]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[2]/ORDER_OBSERVATION[1]/OBR[1]',
        ]);
    }

    public function testRepeatedSegment(): void
    {
        $this->assertPlacement('json-2.5', 'ORU', 'R01', 'ORU_R01', ['MSH', 'PID', 'NTE', 'NTE', 'OBR'], [
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/NTE[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/NTE[2]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
        ]);
    }

    public function testOccurrencesOfANode(): void
    {
        $root = $this->structure('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'PID', 'OBR', 'PID', 'OBR']));

        $patientResult = $root->children[2];
        $this->assertCount(2, $occurrence->occurrencesOf($patientResult));
        $this->assertCount(0, $occurrence->occurrencesOf($root->children[1]));
    }

    public function testSiblingsSharingASegmentName(): void
    {
        // ADT_A01: ROL after PD1 and ROL after PV2 are two nodes.
        $root = $this->structure('json-2.5', 'ADT', 'A01', 'ADT_A01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'EVN', 'PID', 'ROL', 'PV1', 'ROL']));

        $this->assertSame(
            ['MSH[1]', 'EVN[1]', 'PID[1]', 'ROL[1]', 'PV1[1]', 'ROL2[1]'],
            self::placement($occurrence)
        );

        $rols = array_values(array_filter($root->children, static fn (StructureNode $node): bool => $node->name === 'ROL'));
        $this->assertCount(2, $rols);
        $this->assertCount(1, $occurrence->occurrencesOf($rols[0]));
        $this->assertCount(1, $occurrence->occurrencesOf($rols[1]));
    }

    public function testIhePamFrA47WithPatientGroup(): void
    {
        $this->assertPlacement('json-2.5-IHEPAMFR', 'ADT', 'A47', 'ADT_A30', ['MSH', 'EVN', 'PID', 'MRG'], [
            'MSH[1]',
            'EVN[1]',
            'PATIENT[1]/PID[1]',
            'PATIENT[1]/MRG[1]',
        ]);
    }

    public function testIhePamFrA49WithoutPatientGroup(): void
    {
        $this->assertPlacement('json-2.5-IHEPAMFR', 'ADT', 'A49', 'ADT_A30', ['MSH', 'EVN', 'PID', 'MRG'], [
            'MSH[1]',
            'EVN[1]',
            'PID[1]',
            'MRG[1]',
        ]);
    }

    public function testChoiceGroup(): void
    {
        // ORM_O01 (schemas): one of OBR, RQD... per occurrence.
        $root = self::group('ORM_TEST', 1, 1, [
            self::segment('MSH', 1, 1),
            self::group('ORDER', 1, null, [
                self::segment('OBR', 1, 1),
                self::segment('RQD', 1, 1),
            ], true),
        ]);

        $this->assertSame(
            ['MSH[1]', 'ORDER[1]/OBR[1]', 'ORDER[2]/RQD[1]', 'ORDER[3]/OBR[1]'],
            self::placement((new StructureMatcher())->match($root, $this->message(['MSH', 'OBR', 'RQD', 'OBR'])))
        );
    }

    public function testNotDefinedSegmentStaysWhereItWasMet(): void
    {
        // Unknown to the structure: consumed, the walk stays on its position.
        $this->assertPlacement('json-2.5', 'ADT', 'A01', 'ADT_A01', ['MSH', 'EVN', 'PID', 'ZZZ', 'PV1'], [
            'MSH[1]',
            'EVN[1]',
            'PID[1]',
            'notDefined ZZZ (4)',
            'PV1[1]',
        ]);
    }

    public function testNotDefinedNonZSegment(): void
    {
        // Corpus: segment-not-defined (ORC is not in ADT_A01).
        $this->assertPlacement('json-2.5', 'ADT', 'A01', 'ADT_A01', ['MSH', 'EVN', 'PID', 'PV1', 'ORC'], [
            'MSH[1]',
            'EVN[1]',
            'PID[1]',
            'PV1[1]',
            'notDefined ORC (5)',
        ]);
    }

    public function testNotDefinedZSegment(): void
    {
        // Corpus: z-segment-not-defined.
        $this->assertPlacement('json-2.5', 'ADT', 'A01', 'ADT_A01', ['MSH', 'EVN', 'PID', 'PV1', 'ZZA'], [
            'MSH[1]',
            'EVN[1]',
            'PID[1]',
            'PV1[1]',
            'notDefined ZZA (5)',
        ]);
    }

    public function testNotExpectedSegmentNearestPreviousDefinition(): void
    {
        // Corpus: not-expected-pv1. PV1 opens nothing after OBX (choice A of
        // 7.4: entry segments only, no PATIENT_RESULT[2] opened through a leaf).
        $root = $this->structure('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'PID', 'ORC', 'OBR', 'OBX', 'PV1']));

        $this->assertSame([
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/ORC[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
            'notExpected PV1 (6)',
        ], self::placement($occurrence));

        $pv1 = $this->unplacedAt($occurrence, 6);
        $this->assertSame('PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]', $pv1->path);
        // No PV1 after the position: the previous definition.
        $this->assertSame(self::node($root, ['PATIENT_RESULT', 'PATIENT', 'VISIT', 'PV1']), $pv1->nearest);
    }

    public function testNotExpectedSegmentNearestNextDefinition(): void
    {
        // PV2 without PV1: VISIT opens with PV1 only, PV2 is not expected and
        // is checked against the next definition, VISIT/PV2.
        $root = $this->structure('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'PID', 'PV2', 'OBR']));

        $this->assertSame([
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'notExpected PV2 (3)',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
        ], self::placement($occurrence));

        $pv2 = $this->unplacedAt($occurrence, 3);
        $this->assertSame('PATIENT_RESULT[1]/PATIENT[1]', $pv2->path);
        $this->assertSame(self::node($root, ['PATIENT_RESULT', 'PATIENT', 'VISIT', 'PV2']), $pv2->nearest);
    }

    public function testUnknownSegmentInsideAGroupKeepsThePosition(): void
    {
        $root = $this->structure('json-2.5', 'ORU', 'R01', 'ORU_R01');
        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'PID', 'OBR', 'OBX', 'ZZZ', 'NTE']));

        $this->assertSame([
            'MSH[1]',
            'PATIENT_RESULT[1]/PATIENT[1]/PID[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBR[1]',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/OBX[1]',
            'notDefined ZZZ (5)',
            'PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]/NTE[1]',
        ], self::placement($occurrence));

        $zzz = $this->unplacedAt($occurrence, 5);
        $this->assertSame('PATIENT_RESULT[1]/ORDER_OBSERVATION[1]/OBSERVATION[1]', $zzz->path);
        $this->assertNull($zzz->nearest);
    }

    public function testSegmentRepeatedBeyondMaximumIsPlaced(): void
    {
        // Corpus segment-repeated-beyond-max: PD1 [0..1] twice, the
        // second is PD1[2] (cardinality judged in 7.5).
        $this->assertPlacement('json-2.5', 'ADT', 'A01', 'ADT_A01', ['MSH', 'EVN', 'PID', 'PD1', 'PD1', 'PV1'], [
            'MSH[1]',
            'EVN[1]',
            'PID[1]',
            'PD1[1]',
            'PD1[2]',
            'PV1[1]',
        ]);
    }

    public function testGroupRepeatedBeyondMaximumIsPlaced(): void
    {
        // PATIENT [1..1] of A47 twice; the innermost full element
        // that the segment opens takes it.
        $this->assertPlacement('json-2.5-IHEPAMFR', 'ADT', 'A47', 'ADT_A30', ['MSH', 'EVN', 'PID', 'MRG', 'PID', 'MRG'], [
            'MSH[1]',
            'EVN[1]',
            'PATIENT[1]/PID[1]',
            'PATIENT[1]/MRG[1]',
            'PATIENT[2]/PID[1]',
            'PATIENT[2]/MRG[1]',
        ]);
    }

    public function testNotSupportedSegmentIsPlacedOnItsNode(): void
    {
        // A segment X [0..0] present is placed on its node (7.5 reports
        // notAllowedPresent), not taken as not expected.
        $root = self::group('TEST', 1, 1, [
            self::segment('MSH', 1, 1),
            self::segment('ZXS', 0, 0),
            self::segment('PID', 1, 1),
        ]);

        $this->assertSame(
            ['MSH[1]', 'ZXS[1]', 'PID[1]'],
            self::placement((new StructureMatcher())->match($root, $this->message(['MSH', 'ZXS', 'PID'])))
        );
    }

    public function testGroupThatCannotTakeItsEntrySegmentDoesNotLoop(): void
    {
        // ZXX [0..0] is an entry segment of NOT_USED, but an occurrence of
        // NOT_USED cannot hold it: the empty occurrence is dropped, and ZXX,
        // known to the structure, is not expected.
        $root = self::group('TEST', 1, 1, [
            self::segment('MSH', 1, 1),
            self::group('NOT_USED', 0, null, [
                self::segment('ZXX', 0, 0),
                self::segment('ZYY', 0, 1),
            ]),
        ]);

        $occurrence = (new StructureMatcher())->match($root, $this->message(['MSH', 'ZXX']));

        $this->assertSame(['MSH[1]', 'notExpected ZXX (2)'], self::placement($occurrence));
        $this->assertCount(0, $occurrence->occurrencesOf($root->children[1]));
    }

    public function testPositionsAreOneBased(): void
    {
        $root = $this->structure('json-2.5', 'ADT', 'A01', 'ADT_A01');
        $segments = (new StructureMatcher())->match($root, $this->message(['MSH', 'EVN', 'PID', 'PV1']))->segments();

        $this->assertSame([1, 2, 3, 4], array_map(static fn (SegmentOccurrence|UnplacedSegment $item): int => $item->position, $segments));
    }

    /**
     * @param list<string> $segments Segment names, MSH first.
     * @param list<string> $expected
     */
    private function assertPlacement(string $directory, string $code, string $event, string $structure, array $segments, array $expected): void
    {
        $occurrence = (new StructureMatcher())->match(
            $this->structure($directory, $code, $event, $structure),
            $this->message($segments)
        );

        $this->assertSame($expected, self::placement($occurrence));
    }

    private function structure(string $directory, string $code, string $event, string $structure): GroupNode
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

    /**
     * Path of each segment in message order; "<reason> NAME (position)" for
     * a segment without place (notDefined, notExpected).
     *
     * @return list<string>
     */
    private static function placement(GroupOccurrence $occurrence): array
    {
        return array_map(
            static fn (SegmentOccurrence|UnplacedSegment $item): string => $item instanceof SegmentOccurrence
                ? $item->path
                : sprintf('%s %s (%d)', $item->reason->value, $item->segment->getName(), $item->position),
            $occurrence->segments()
        );
    }

    private function unplacedAt(GroupOccurrence $occurrence, int $position): UnplacedSegment
    {
        foreach ($occurrence->segments() as $item) {
            if ($item instanceof UnplacedSegment && $item->position === $position) {
                return $item;
            }
        }

        $this->fail("No unplaced segment at position $position.");
    }

    /**
     * Node of the structure reached by child names (first child of each name).
     *
     * @param list<string> $names
     */
    private static function node(GroupNode $group, array $names): StructureNode
    {
        $node = $group;
        foreach ($names as $name) {
            if (!$node instanceof GroupNode) {
                throw new \OutOfBoundsException("$name: not in a group.");
            }
            $children = array_values(array_filter($node->children, static fn (StructureNode $child): bool => $child->name === $name));
            $node = $children[0] ?? throw new \OutOfBoundsException("$name: not found.");
        }

        return $node;
    }

    private static function segment(string $name, int $min, ?int $max): SegmentNode
    {
        return new SegmentNode(
            $min === 0 ? Usage::Optional : Usage::Required,
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
