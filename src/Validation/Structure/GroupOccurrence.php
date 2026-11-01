<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedSegment;

/**
 * An occurrence of a group of the profile, with what the message holds in
 * it, in message order. The root occurrence is the message itself (path '').
 */
final readonly class GroupOccurrence
{
    /**
     * @param int $repetition Repetition of the group in its parent occurrence (1-based).
     * @param string $path Path in the profile; '' for the root.
     * @param list<GroupOccurrence|SegmentOccurrence|UnplacedSegment> $items In message order.
     */
    public function __construct(
        public GroupNode $node,
        public int $repetition,
        public string $path,
        public array $items
    ) {
        //
    }

    /**
     * Get the occurrences of a child node in this occurrence: the real count
     * that usage and cardinality are judged on.
     *
     * @return list<GroupOccurrence|SegmentOccurrence>
     */
    public function occurrencesOf(StructureNode $child): array
    {
        $occurrences = [];
        foreach ($this->items as $item) {
            if (!$item instanceof UnplacedSegment && $item->node === $child) {
                $occurrences[] = $item;
            }
        }

        return $occurrences;
    }

    /**
     * Get the segments of this occurrence and of its groups, in message order.
     *
     * @return list<SegmentOccurrence|UnplacedSegment>
     */
    public function segments(): array
    {
        $segments = [];
        foreach ($this->items as $item) {
            if ($item instanceof GroupOccurrence) {
                // Spread operator (...): puts the elements of each array one
                // after the other, as array_merge() does for lists.
                $segments = [...$segments, ...$item->segments()];
            } else {
                $segments[] = $item;
            }
        }

        return $segments;
    }
}
