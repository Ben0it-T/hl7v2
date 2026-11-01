<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Validation\Finding;
use HL7v2\Validation\Severity;
use HL7v2\Validation\Structure\GroupOccurrence;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedSegment;

/**
 * The message rearranged into the group tree of its message profile, each
 * element attached to a node of the profile (StructureMatcher), with the
 * findings of each element.
 *
 * A finding is attached to the element it is about:
 * - an occurrence (group or segment), or a segment without place;
 * - for an absent child, or a child under its minimum, the group
 *   occurrence that should hold it (the root for MSH, EVN, PV1...).
 * The findings of an element are its own, not those of its children.
 */
final class ProfiledMessage
{
    /**
     * Findings of each element, in validation order. Keyed by the element
     * itself (identity): two occurrences of the same node stay apart.
     *
     * @var \WeakMap<GroupOccurrence|SegmentOccurrence|UnplacedSegment, list<Finding>>
     */
    private \WeakMap $findings;

    /**
     * @param GroupOccurrence $root Root occurrence given by StructureMatcher::match().
     */
    public function __construct(
        public readonly GroupOccurrence $root
    ) {
        $this->findings = new \WeakMap();
    }

    /**
     * Attach a finding to an element of the tree.
     *
     * @internal Used by the passes of the validation.
     *
     * @param GroupOccurrence|SegmentOccurrence|UnplacedSegment $element
     * @param Finding $finding
     *
     * @return void
     */
    public function attach(GroupOccurrence|SegmentOccurrence|UnplacedSegment $element, Finding $finding): void
    {
        // Spread operator (...): puts the elements of the array one after the other, followed by the new finding.
        $this->findings[$element] = [...($this->findings[$element] ?? []), $finding];
    }

    /**
     * Get the findings of an element, in validation order.
     *
     * @param GroupOccurrence|SegmentOccurrence|UnplacedSegment $element
     *
     * @return list<Finding>
     */
    public function findingsOf(GroupOccurrence|SegmentOccurrence|UnplacedSegment $element): array
    {
        return $this->findings[$element] ?? [];
    }

    /**
     * Check whether an element has a finding of severity Error (its own
     * findings only, not those of its children).
     *
     * @param GroupOccurrence|SegmentOccurrence|UnplacedSegment $element
     *
     * @return bool
     */
    public function hasError(GroupOccurrence|SegmentOccurrence|UnplacedSegment $element): bool
    {
        foreach ($this->findingsOf($element) as $finding) {
            if ($finding->severity === Severity::Error) {
                return true;
            }
        }

        return false;
    }
}
