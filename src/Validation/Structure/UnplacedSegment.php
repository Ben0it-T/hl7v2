<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Model\Segment;

/**
 * A segment of the message that found no place in the message structure.
 *
 * Step 7.3: the first segment without place and every following one. Step
 * 7.4 adds the reason (not defined, not expected) and places them where
 * they were met.
 */
final readonly class UnplacedSegment
{
    /**
     * @param int $position Position of the segment in the message (1-based).
     */
    public function __construct(
        public int $position,
        public Segment $segment
    ) {
        //
    }
}
