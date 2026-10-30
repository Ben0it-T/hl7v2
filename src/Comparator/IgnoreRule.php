<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

/**
 * Ignore rule of the comparator: a pattern of locations.
 *
 * Same notation as a Location (counted from 1, a segment ranked among the
 * segments of the same name), but a missing index or * means "every index",
 * and a rule may stop at any level:
 *
 * - ZBE              every ZBE segment
 * - NK1[2]           the second NK1 segment
 * - MSH-7            field MSH-7 of every MSH segment
 * - PID-3            every repetition, component and sub-component of PID-3
 * - PID[*]-3[*].4    component 4 of every repetition of PID-3
 * - PID[1]-3[2].4.1  one sub-component
 *
 * A null property matches every index.
 */
final readonly class IgnoreRule
{
    /**
     * @param string $segment Segment name.
     * @param int|null $segmentRank Rank among the segments of the same name; null: every rank.
     * @param int|null $field Field number; null: the whole segment.
     * @param int|null $repetition Repetition; null: every repetition.
     * @param int|null $component Component number; null: the whole repetition.
     * @param int|null $subComponent Sub-component number; null: the whole component.
     */
    public function __construct(
        public string $segment,
        public ?int $segmentRank = null,
        public ?int $field = null,
        public ?int $repetition = null,
        public ?int $component = null,
        public ?int $subComponent = null
    ) {
        //
    }
}
