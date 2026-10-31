<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Segment;
use HL7v2\Profile\Definition\SegmentNode;

/**
 * A segment of the message placed on a segment node of the profile.
 */
final readonly class SegmentOccurrence
{
    /**
     * @param int $repetition Repetition of the node in its parent occurrence (1-based).
     * @param string $path Path in the profile (PATIENT_RESULT[1]/ORDER_OBSERVATION[2]/OBR[1]).
     * @param int $position Position of the segment in the message (1-based, 1 for MSH).
     */
    public function __construct(
        public SegmentNode $node,
        public int $repetition,
        public string $path,
        public int $position,
        public Segment $segment
    ) {
        //
    }
}
