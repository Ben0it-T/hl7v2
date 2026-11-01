<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Validation\Structure\Frame;
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedReason;
use HL7v2\Validation\Structure\UnplacedSegment;

/**
 * Places the segments of a message on the nodes of its message structure:
 * a recursive descent driven by the profile, with an explicit stack of the
 * open group occurrences.
 *
 * - A node is opened by its entry segments (StructureNode::$entrySegments).
 * - Repetitions are counted while walking, never predicted.
 * - Greedy: a segment goes to the innermost element that can take it.
 * - Each segment is attached to a node, never to a name.
 *
 * When the current segment does not open the current child, one question
 * decides: is it expected later (an entry segment of a following element,
 * or of a new occurrence of an enclosing element)?
 * - Yes: the child is complete or absent, the walk goes on.
 * - No, but it opens the full current child: one more repetition.
 * - No, and unknown to the structure: not defined segment.
 * - No, and known to the structure: not expected segment, with its
 *   nearest definition.
 * A not defined or not expected segment is consumed where it was met, and
 * the walk stays on its position in the profile.
 *
 * The matcher judges nothing: it gives the tree of occurrences, which the
 * structure checks and the content pass walk.
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
     * Open group occurrences, outermost first.
     *
     * @var list<Frame>
     */
    private array $stack = [];

    /**
     * Segment names of the message structure.
     *
     * @var array<string, true>
     */
    private array $knownSegments = [];

    /**
     * Segment nodes of the message structure, in profile order.
     *
     * @var list<SegmentNode>
     */
    private array $profileOrder = [];

    /**
     * Index in $profileOrder of the first segment node of each node, by
     * object id (spl_object_id).
     *
     * @var array<int, int>
     */
    private array $firstSegment = [];

    /**
     * Place the segments of a message on a message structure.
     *
     * @param GroupNode $structure Root of the message structure (MessageProfile::$root).
     *
     * @return GroupOccurrence The root occurrence; every segment of the
     *                         message is in it, placed or not.
     */
    public function match(GroupNode $structure, Message $message): GroupOccurrence
    {
        $this->segments = array_values($message->getSegments());
        $this->cursor = 0;
        $this->stack = [];
        $this->knownSegments = [];
        $this->profileOrder = [];
        $this->firstSegment = [];
        $this->indexNode($structure);

        $root = $this->groupOccurrence($structure, 1, '');

        if ($this->cursor !== count($this->segments)) {
            throw new \LogicException('Segments left after the message structure.');
        }

        return $root;
    }

    /**
     * Walk one occurrence of a group: each child in order for a sequence,
     * the first alternative opened by the current segment for a choice.
     */
    private function groupOccurrence(GroupNode $group, int $repetition, string $path): GroupOccurrence
    {
        $frame = new Frame($group);
        $this->stack[] = $frame;

        $pathNames = $group->childPathNames();
        $items = [];

        foreach ($group->children as $index => $child) {
            if ($group->choice && !$this->opens($child)) {
                continue;
            }

            $frame->childIndex = $index;
            $frame->repetition = 0;

            // Spread operator (...): puts the elements of each array one
            // after the other, as array_merge() does for lists.
            $items = [...$items, ...$this->placeChild($child, $pathNames[$index], $path, $frame)];

            if ($group->choice) {
                break;
            }
        }

        array_pop($this->stack);

        return new GroupOccurrence($group, $repetition, $path, $items);
    }

    /**
     * Place what the message holds for a child node, until the current
     * segment belongs further on.
     *
     * @return list<GroupOccurrence|SegmentOccurrence|UnplacedSegment>
     */
    private function placeChild(StructureNode $child, string $pathName, string $path, Frame $frame): array
    {
        $items = [];

        while (($segment = $this->segments[$this->cursor] ?? null) !== null) {
            $name = $segment->getName();

            // The segment opens the child, which can take one more occurrence.
            if ($this->opens($child) && !self::isFull($child, $frame->repetition)) {
                $occurrence = $this->placeOne($child, $pathName, $path, $frame);
                if ($occurrence !== null) {
                    $items[] = $occurrence;
                    continue;
                }
            }

            // Expected later, within the cardinalities: the child is complete or absent.
            if ($this->expectedLater($name, true)) {
                break;
            }

            // It opens the full child: one more repetition.
            if ($this->opens($child)) {
                $occurrence = $this->placeOne($child, $pathName, $path, $frame);
                if ($occurrence !== null) {
                    $items[] = $occurrence;
                    continue;
                }
            }

            // Expected later beyond a maximum: an enclosing full element
            // takes it as one more repetition, at its own level.
            if ($this->expectedLater($name, false)) {
                break;
            }

            $items[] = $this->unplaced($segment, $name, $path);
            $this->cursor++;
        }

        return $items;
    }

    /**
     * Place one more occurrence of a child node, opened by the current segment.
     *
     * @return GroupOccurrence|SegmentOccurrence|null Null when the occurrence
     *         would hold nothing (an entry segment of a child [0..0]).
     */
    private function placeOne(StructureNode $child, string $pathName, string $parentPath, Frame $frame): GroupOccurrence|SegmentOccurrence|null
    {
        $repetition = $frame->repetition + 1;
        $path = ($parentPath === '' ? '' : "$parentPath/") . "{$pathName}[$repetition]";

        if ($child instanceof SegmentNode) {
            $frame->repetition = $repetition;
            $occurrence = new SegmentOccurrence($child, $repetition, $path, $this->cursor + 1, $this->segments[$this->cursor]);
            $this->cursor++;

            return $occurrence;
        }

        if (!$child instanceof GroupNode) {
            throw new \LogicException('Unknown structure node: ' . $child::class);
        }

        // Set before the walk: an inner level reads whether this group can
        // still repeat.
        $frame->repetition = $repetition;
        $start = $this->cursor;
        $occurrence = $this->groupOccurrence($child, $repetition, $path);

        if ($this->cursor === $start) {
            $frame->repetition = $repetition - 1;

            return null;
        }

        return $occurrence;
    }

    /**
     * Check whether a segment is expected later: an entry segment of a child
     * following the current one (none in a choice: one alternative per
     * occurrence), at any level, or of a new occurrence of the current
     * element of an enclosing level.
     *
     * @param bool $withinCardinality False to accept a new occurrence of a
     *                                full enclosing element.
     */
    private function expectedLater(string $name, bool $withinCardinality): bool
    {
        $innermost = count($this->stack) - 1;

        foreach ($this->stack as $level => $frame) {
            $children = $frame->group->children;

            if (!$frame->group->choice) {
                for ($i = $frame->childIndex + 1; $i < count($children); $i++) {
                    if (in_array($name, $children[$i]->entrySegments, true)) {
                        return true;
                    }
                }
            }

            $current = $children[$frame->childIndex];
            if (
                $level < $innermost
                && in_array($name, $current->entrySegments, true)
                && (!$withinCardinality || !self::isFull($current, $frame->repetition))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * A segment without place: not defined if unknown to the message
     * structure, not expected otherwise.
     */
    private function unplaced(Segment $segment, string $name, string $path): UnplacedSegment
    {
        if (!isset($this->knownSegments[$name])) {
            return new UnplacedSegment($this->cursor + 1, $segment, UnplacedReason::NotDefined, $path);
        }

        return new UnplacedSegment($this->cursor + 1, $segment, UnplacedReason::NotExpected, $path, $this->nearest($name));
    }

    /**
     * The nearest definition of a segment name from the current position:
     * the next segment node of that name in profile order, else the previous
     * one.
     */
    private function nearest(string $name): SegmentNode
    {
        $frame = $this->stack[count($this->stack) - 1];
        $position = $this->firstSegment[spl_object_id($frame->group->children[$frame->childIndex])];

        for ($i = $position; $i < count($this->profileOrder); $i++) {
            if ($this->profileOrder[$i]->name === $name) {
                return $this->profileOrder[$i];
            }
        }

        for ($i = $position - 1; $i >= 0; $i--) {
            if ($this->profileOrder[$i]->name === $name) {
                return $this->profileOrder[$i];
            }
        }

        throw new \LogicException("Segment $name is not in the message structure.");
    }

    /**
     * Index the message structure: segment names, segment nodes in profile
     * order, first segment node of each node.
     */
    private function indexNode(StructureNode $node): void
    {
        $this->firstSegment[spl_object_id($node)] = count($this->profileOrder);

        if ($node instanceof SegmentNode) {
            $this->profileOrder[] = $node;
            $this->knownSegments[$node->name] = true;
        } elseif ($node instanceof GroupNode) {
            foreach ($node->children as $child) {
                $this->indexNode($child);
            }
        }
    }

    /**
     * Check whether the current segment can open a node.
     */
    private function opens(StructureNode $node): bool
    {
        $segment = $this->segments[$this->cursor] ?? null;

        return $segment !== null && in_array($segment->getName(), $node->entrySegments, true);
    }

    /**
     * Check whether a node holds its maximum of occurrences.
     */
    private static function isFull(StructureNode $node, int $occurrences): bool
    {
        return $node->cardinality->max !== null && $occurrences >= $node->cardinality->max;
    }
}
