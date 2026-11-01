<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Location;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedReason;
use HL7v2\Validation\Structure\UnplacedSegment;

/**
 * Judges the tree of StructureMatcher: usage and cardinality of the
 * groups and segments on their real counts, and the segments without place.
 * One finding per cause:
 * - absent node that cannot be skipped: requiredAbsent only (no second
 *   finding for its minimum); a required group whose content is all
 *   optional can be skipped, its absence is no finding;
 * - X present: notAllowedPresent on each occurrence (no cardinality finding);
 * - repetition beyond the maximum: cardinality on that occurrence;
 * - present, fewer than the minimum: cardinality on the node;
 * - W, B, C or CE present: one finding on the first occurrence.
 * In a choice group, the alternatives not taken are not judged.
 */
final class StructureChecker
{
    private CheckRecorder $recorder;

    /**
     * Rank of each segment among the segments of the same name, by position.
     *
     * @var array<int, int>
     */
    private array $ranks = [];

    public function check(GroupOccurrence $root, CheckRecorder $recorder): void
    {
        $this->recorder = $recorder;
        $this->ranks = [];

        $counts = [];
        foreach ($root->segments() as $item) {
            $name = $item->segment->getName();
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $this->ranks[$item->position] = $counts[$name];
        }

        $this->checkOccurrence($root);
    }

    /**
     * Check one group occurrence, in message order.
     */
    private function checkOccurrence(GroupOccurrence $occurrence): void
    {
        $children = $occurrence->node->children;
        $pathNames = $occurrence->node->childPathNames();
        $next = 0; // Children before $next are closed.

        foreach ($occurrence->items as $item) {
            if ($item instanceof UnplacedSegment) {
                $this->checkUnplaced($item);
                continue;
            }

            $index = array_search($item->node, $children, true);
            if (!is_int($index) || $index < $next) {
                throw new \LogicException("Node {$item->node->name} out of profile order.");
            }
            for (; $next < $index; $next++) {
                $this->close($occurrence, $children[$next], $pathNames[$next]);
            }

            $this->checkPresent($item);

            if ($item instanceof GroupOccurrence) {
                $this->checkOccurrence($item);
            }
        }

        for (; $next < count($children); $next++) {
            $this->close($occurrence, $children[$next], $pathNames[$next]);
        }
    }

    /**
     * Check an occurrence of a node: usage on the first one, maximum on each.
     */
    private function checkPresent(GroupOccurrence|SegmentOccurrence $item): void
    {
        $node = $item->node;
        $label = self::label($node);
        [$location, $segment] = $this->position($item);

        if ($node->usage === Usage::NotSupported) {
            $this->recorder->record(new Check(
                'Element not allowed', false, "$label is not allowed.",
                FindingType::NotAllowedPresent, $location, $item->path, $segment
            ));

            return;
        }

        if ($item->repetition === 1) {
            [$type, $finding, $description] = match ($node->usage) {
                Usage::Withdrawn => ['Element not allowed', FindingType::WithdrawnPresent, "$label is withdrawn and should not be used."],
                Usage::Backward => ['Usage', FindingType::BackwardPresent, "$label is retained for backward compatibility."],
                Usage::Conditional, Usage::ConditionalOrEmpty => ['Conditional', FindingType::ConditionalPresent,
                    "$label usage is {$node->usage->value}: the condition is not evaluated (Q13)."],
                default => ['Usage', null, "$label usage is {$node->usage->value}."],
            };
            $this->recorder->record(new Check(
                $type, $finding !== FindingType::WithdrawnPresent, $description,
                $finding, $location, $item->path, $segment
            ));
        }

        $max = $node->cardinality->max;
        if ($max !== null && $item->repetition > $max) {
            $this->recorder->record(new Check(
                'Cardinality', false,
                "$label cardinality is " . self::cardinality($node) . ": repeated beyond its maximum ($max).",
                FindingType::Cardinality, $location, $item->path, $segment
            ));
        }
    }

    /**
     * Judge the count of a child once the walk is past it.
     */
    private function close(GroupOccurrence $parent, StructureNode $child, string $pathName): void
    {
        $count = count($parent->occurrencesOf($child));
        $path = ($parent->path === '' ? '' : "{$parent->path}/") . $pathName; // Absent: no index (Q213).
        $label = self::label($child);
        $cardinality = self::cardinality($child);

        if ($count === 0) {
            if ($parent->node->choice) {
                return; // Alternative not taken.
            }
            if ($child->skippable) {
                $this->recorder->record(new Check('Usage', true, "$label usage is {$child->usage->value}; absent.", null, null, $path));

                return;
            }
            $finding = $child->usage === Usage::Required ? FindingType::RequiredAbsent : FindingType::Cardinality;
            $this->recorder->record(new Check(
                'Required element', false, "$label is required ({$child->usage->value}, $cardinality).",
                $finding, null, $path
            ));

            return;
        }

        $max = $child->cardinality->max;
        if ($child->usage === Usage::NotSupported || ($max !== null && $count > $max)) {
            return; // Reported on each occurrence in excess.
        }

        $result = $count >= $child->cardinality->min;
        $this->recorder->record(new Check(
            'Cardinality', $result,
            $result
                ? "$label cardinality is $cardinality. Found $count time(s)."
                : "$label cardinality is $cardinality. Must have at least {$child->cardinality->min} repetition(s) (found $count).",
            $result ? null : FindingType::Cardinality, null, $path
        ));
    }

    /**
     * Check a segment without place: not defined (Z or not), or not expected.
     */
    private function checkUnplaced(UnplacedSegment $item): void
    {
        $name = $item->segment->getName();
        $location = new Location($name, $this->ranks[$item->position]);
        $path = $item->path === '' ? null : $item->path;

        [$finding, $description] = match ($item->reason) {
            UnplacedReason::NotDefined => [
                str_starts_with($name, 'Z') ? FindingType::ZSegmentNotDefined : FindingType::SegmentNotDefined,
                "Segment '$name' is not defined in the message structure.",
            ],
            UnplacedReason::NotExpected => [
                FindingType::SegmentNotExpected,
                "Segment '$name' is not expected at this position.",
            ],
        };

        $this->recorder->record(new Check('Structure', false, $description, $finding, $location, $path, $item->position));
    }

    /**
     * Positions of an occurrence: a segment has its Location; a group has
     * none (not an element of the message), and the position of its first segment.
     *
     * @return array{0: Location|null, 1: int}
     */
    private function position(GroupOccurrence|SegmentOccurrence $item): array
    {
        if ($item instanceof SegmentOccurrence) {
            return [new Location($item->segment->getName(), $this->ranks[$item->position]), $item->position];
        }

        return [null, $item->segments()[0]->position];
    }

    private static function label(StructureNode $node): string
    {
        return ($node instanceof GroupNode ? 'Group' : 'Segment') . " '{$node->name}'";
    }

    private static function cardinality(StructureNode $node): string
    {
        return "[{$node->cardinality->min}.." . ($node->cardinality->max ?? '*') . ']';
    }
}
