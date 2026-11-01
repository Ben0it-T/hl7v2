<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation;

use HL7v2\HL7Message;
use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Validation\Finding;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\Severity;
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\StructureMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Findings attached to the elements of the Profiled message.
 */
final class ProfiledMessageTest extends TestCase
{
    public function testFindingsOfEachElementInOrder(): void
    {
        [$root, $msh, $pid] = $this->tree();
        $profiled = new ProfiledMessage($root);

        $first = self::finding(Severity::Warning, FindingType::WithdrawnPresent);
        $second = self::finding(Severity::Error, FindingType::Cardinality);
        $other = self::finding(Severity::Error, FindingType::RequiredAbsent);
        $profiled->attach($pid, $first);
        $profiled->attach($root, $other);
        $profiled->attach($pid, $second);

        $this->assertSame($root, $profiled->root);
        $this->assertSame([$first, $second], $profiled->findingsOf($pid));
        $this->assertSame([$other], $profiled->findingsOf($root));
        $this->assertSame([], $profiled->findingsOf($msh));
    }

    public function testHasErrorReadsOnlyTheElementItsOwnFindings(): void
    {
        [$root, $msh, $pid] = $this->tree();
        $profiled = new ProfiledMessage($root);

        $profiled->attach($msh, self::finding(Severity::Warning, FindingType::ZSegmentNotDefined));
        $profiled->attach($msh, self::finding(Severity::Info, FindingType::BackwardPresent));
        $profiled->attach($pid, self::finding(Severity::Error, FindingType::NotAllowedPresent));

        $this->assertFalse($profiled->hasError($msh));
        $this->assertTrue($profiled->hasError($pid));
        // An Error of a child is not an Error of its parent.
        $this->assertFalse($profiled->hasError($root));
    }

    /**
     * A tree MSH, PID with its two segment occurrences.
     *
     * @return array{0: GroupOccurrence, 1: SegmentOccurrence, 2: SegmentOccurrence}
     */
    private function tree(): array
    {
        $structure = new GroupNode('ROOT', Usage::Required, new Cardinality(1, 1), [
            new SegmentNode(Usage::Required, new Cardinality(1, 1), new SegmentDefinition('MSH', 'MSH', '', [])),
            new SegmentNode(Usage::Required, new Cardinality(1, 1), new SegmentDefinition('PID', 'PID', '', [])),
        ]);

        $hl7 = new HL7Message();
        $hl7->parse("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01|1|P|2.5\rPID|1");
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        $root = (new StructureMatcher())->match($structure, $message);
        [$msh, $pid] = $root->items;
        $this->assertInstanceOf(SegmentOccurrence::class, $msh);
        $this->assertInstanceOf(SegmentOccurrence::class, $pid);

        return [$root, $msh, $pid];
    }

    private static function finding(Severity $severity, FindingType $type): Finding
    {
        return new Finding($severity, $type, $type->value);
    }
}
