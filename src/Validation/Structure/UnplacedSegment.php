<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Segment;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Validation\Structure\UnplacedReason;

/**
 * A segment of the message that found no place in the message structure. It
 * stays where it was met, in the occurrence being walked, and the walk stays
 * on its position in the profile.
 */
final readonly class UnplacedSegment
{
    /**
     * @param int $position Position of the segment in the message (1-based).
     * @param string $path Path of the occurrence where it was met; '' at the root.
     * @param SegmentNode|null $nearest For a not expected segment, the nearest
     *                                  definition of that name: the next
     *                                  one in profile order, else the previous.
     */
    public function __construct(
        public int $position,
        public Segment $segment,
        public UnplacedReason $reason,
        public string $path,
        public ?SegmentNode $nearest = null
    ) {
        //
    }
}
