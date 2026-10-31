<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedSegment;

/**
 * Places the segments of a message on the nodes of its message structure
 * (ADR 0002): a recursive descent driven by the profile.
 *
 * - A node is opened by its entry segments (GroupNode::$entrySegments).
 * - Repetitions are counted while walking, never predicted.
 * - Greedy (Q14): a segment goes to the innermost element that can take it;
 *   a group occurrence ends only when none of its remaining children can.
 * - Each segment is attached to a node, never to a name: the three NTE of
 *   ORU_R01 are three nodes.
 *
 * The matcher judges nothing: it gives the tree of occurrences, which the
 * structure checks (7.5) and the content pass (8) walk.
 *
 * Step 7.3: a segment that opens no remaining element is skipped past; at
 * the end of the structure, the segments left are unplaced. Step 7.4 adds
 * recovery (expected later, not defined, not expected).
 */
final class StructureMatcher
{
    /**
     * @var list<Segment>
     */
    private array $segments = [];

    /**
     * Index (0-based) of the current segment.
     */
    private int $cursor = 0;

    /**
     * Place the segments of a message on a message structure.
     *
     * @param GroupNode $structure Root of the message structure (MessageProfile::$root).
     *
     * @return GroupOccurrence The root occurrence.
     */
    public function match(GroupNode $structure, Message $message): GroupOccurrence
    {
        $this->segments = array_values($message->getSegments());
        $this->cursor = 0;

        $root = $this->groupOccurrence($structure, 1, '');

        $items = $root->items;
        for (; $this->cursor < count($this->segments); $this->cursor++) {
            $items[] = new UnplacedSegment($this->cursor + 1, $this->segments[$this->cursor]);
        }

        return new GroupOccurrence($structure, 1, '', $items);
    }

    /**
     * Walk one occurrence of a group: each child in order for a sequence,
     * the first alternative opened by the current segment for a choice.
     */
    private function groupOccurrence(GroupNode $group, int $repetition, string $path): GroupOccurrence
    {
        $pathNames = $group->childPathNames();
        $items = [];

        foreach ($group->children as $index => $child) {
            if ($group->choice && !$this->opens($child)) {
                continue;
            }

            // Spread operator (...): puts the elements of each array one
            // after the other, as array_merge() does for lists.
            $items = [...$items, ...$this->place($child, $pathNames[$index], $path)];

            if ($group->choice) {
                break;
            }
        }

        return new GroupOccurrence($group, $repetition, $path, $items);
    }

    /**
     * Place the occurrences of a child node, as long as the current segment
     * opens it and it is not full.
     *
     * @return list<GroupOccurrence|SegmentOccurrence>
     */
    private function place(StructureNode $child, string $pathName, string $parentPath): array
    {
        $occurrences = [];
        $max = $child->cardinality->max;

        while ($this->opens($child) && ($max === null || count($occurrences) < $max)) {
            $repetition = count($occurrences) + 1;
            $path = ($parentPath === '' ? '' : "$parentPath/") . "{$pathName}[$repetition]";

            if ($child instanceof SegmentNode) {
                $occurrences[] = new SegmentOccurrence(
                    $child,
                    $repetition,
                    $path,
                    $this->cursor + 1,
                    $this->segments[$this->cursor]
                );
                $this->cursor++;
            } elseif ($child instanceof GroupNode) {
                $start = $this->cursor;
                $occurrence = $this->groupOccurrence($child, $repetition, $path);

                // An entry segment of a child that cannot occur ([0..0]):
                // the occurrence holds nothing and is dropped.
                if ($this->cursor === $start) {
                    break;
                }

                $occurrences[] = $occurrence;
            } else {
                throw new \LogicException('Unknown structure node: ' . $child::class);
            }
        }

        return $occurrences;
    }

    /**
     * Check whether the current segment can open a node.
     */
    private function opens(StructureNode $node): bool
    {
        $segment = $this->segments[$this->cursor] ?? null;

        return $segment !== null && in_array($segment->getName(), $node->entrySegments, true);
    }
}
