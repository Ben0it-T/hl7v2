<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Skippable nodes and entry segments of a message structure.
 */
final class StructureNodeTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../../profiles-next';

    public function testSegment(): void
    {
        $required = self::segment('PID', 1, 1);
        $this->assertFalse($required->skippable);
        $this->assertSame(['PID'], $required->entrySegments);

        $optional = self::segment('PD1', 0, 1);
        $this->assertTrue($optional->skippable);
        $this->assertSame(['PD1'], $optional->entrySegments);
    }

    public function testOptionalFirstChildDoesNotHideTheNextOne(): void
    {
        // Defect 1: ORDER_OBSERVATION opens with ORC or OBR (C1: OBR without ORC).
        $group = self::group('ORDER_OBSERVATION', 1, null, [
            self::segment('ORC', 0, 1),
            self::segment('OBR', 1, 1),
            self::segment('NTE', 0, null),
        ]);

        $this->assertSame(['ORC', 'OBR'], $group->entrySegments);
        $this->assertFalse($group->skippable);
    }

    public function testOptionalGroupIsSkippable(): void
    {
        $group = self::group('VISIT', 0, 1, [
            self::segment('PV1', 1, 1),
            self::segment('PV2', 0, 1),
        ]);

        $this->assertSame(['PV1'], $group->entrySegments);
        $this->assertTrue($group->skippable);
    }

    public function testRequiredGroupWithOnlyOptionalContentIsSkippable(): void
    {
        $group = self::group('NOTES', 1, 1, [
            self::segment('NTE', 0, null),
            self::segment('CTI', 0, 1),
        ]);

        $this->assertSame(['NTE', 'CTI'], $group->entrySegments);
        $this->assertTrue($group->skippable);
    }

    public function testNestedGroups(): void
    {
        // Defect 1: PATIENT_RESULT opens with PID, ORC or OBR (C5: no PATIENT).
        $patientResult = self::group('PATIENT_RESULT', 1, null, [
            self::group('PATIENT', 0, 1, [
                self::segment('PID', 1, 1),
                self::segment('PD1', 0, 1),
            ]),
            self::group('ORDER_OBSERVATION', 1, null, [
                self::segment('ORC', 0, 1),
                self::segment('OBR', 1, 1),
            ]),
        ]);

        $this->assertSame(['PID', 'ORC', 'OBR'], $patientResult->entrySegments);
        $this->assertFalse($patientResult->skippable);
    }

    public function testEntrySegmentsAreListedOnce(): void
    {
        $group = self::group('COMMENTS', 1, 1, [
            self::segment('NTE', 0, 1),
            self::group('DETAIL', 0, 1, [self::segment('NTE', 1, 1)]),
            self::segment('OBX', 1, 1),
        ]);

        $this->assertSame(['NTE', 'OBX'], $group->entrySegments);
    }

    public function testChoiceGroup(): void
    {
        // ORM_O01 (schemas): one of OBR, RQD, RQ1... per occurrence.
        $choice = self::group('ORDER_CHOICE', 1, 1, [
            self::segment('OBR', 1, 1),
            self::segment('RQD', 1, 1),
            self::segment('RQ1', 1, 1),
        ], true);

        $this->assertSame(['OBR', 'RQD', 'RQ1'], $choice->entrySegments);
        $this->assertFalse($choice->skippable);
    }

    public function testChoiceGroupWithASkippableAlternativeIsSkippable(): void
    {
        $choice = self::group('ORDER_CHOICE', 1, 1, [
            self::segment('OBR', 1, 1),
            self::segment('RQD', 0, 1),
        ], true);

        $this->assertSame(['OBR', 'RQD'], $choice->entrySegments);
        $this->assertTrue($choice->skippable);
    }

    public function testRealOruR01(): void
    {
        $root = ProfileDirectory::open(self::PROFILES . '/json-2.5')->messageProfile('ORU', 'R01', 'ORU_R01')->root;

        $patientResult = $root->children[2];
        $this->assertInstanceOf(GroupNode::class, $patientResult);
        $this->assertSame(['PID', 'ORC', 'OBR'], $patientResult->entrySegments);

        $orderObservation = $patientResult->children[1];
        $this->assertInstanceOf(GroupNode::class, $orderObservation);
        $this->assertSame('ORDER_OBSERVATION', $orderObservation->name);
        $this->assertSame(['ORC', 'OBR'], $orderObservation->entrySegments);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function directories(): iterable
    {
        foreach (['2.3', '2.3.1', '2.4', '2.5', '2.5-IHEPAMFR'] as $profileId) {
            yield $profileId => ["json-$profileId"];
        }
    }

    #[DataProvider('directories')]
    public function testEveryStructureOpensWithMsh(string $directoryName): void
    {
        $directory = ProfileDirectory::open(self::PROFILES . "/$directoryName");

        $files = glob(self::PROFILES . "/$directoryName/structures/*.json") ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            [$code, $event, $structure] = explode('-', basename($file, '.json'), 3);
            $root = $directory->messageProfile($code, $event, $structure)->root;

            $this->assertSame(['MSH'], $root->entrySegments, basename($file));
            $this->assertFalse($root->skippable, basename($file));
        }
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
