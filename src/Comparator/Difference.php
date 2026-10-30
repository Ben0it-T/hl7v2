<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\DifferenceType;
use HL7v2\Model\Location;

/**
 * A difference detected between two HL7 messages.
 *
 * The location is counted from 1, a segment being ranked among the segments
 * of the same name (PID[1]-5[1].2.1). A data difference (ADDED, REMOVED,
 * CHANGED) is located on a sub-component; a form difference (EMPTY_ADDED,
 * EMPTY_REMOVED) on the highest empty element present on one side only
 * (segment, field, repetition, component or sub-component).
 *
 * Examples:
 *
 * Left:   PID|1||100001^^^HOSP||DOE^JANE|||F
 * Right:  PID|1||100001^^^HOSP||DOE^JOAN|||""||
 *
 *     type           getPath()         short form   left     right
 *     CHANGED        PID[1]-5[1].2.1   PID-5.2.1    'JANE'   'JOAN'
 *     CHANGED        PID[1]-8[1].1.1   PID-8.1.1    'F'      '""'
 *     EMPTY_ADDED    PID[1]-9[1]       PID-9        null     null
 *     EMPTY_ADDED    PID[1]-10[1]      PID-10       null     null
 *
 * Left:   PID|1||100001^^^HOSP||DOE^JANE
 * Right:  PID|1||100001^^^HOSP~200001^^^HOSP||DOE^JANE||
 *
 *     type           getPath()         short form   left     right
 *     ADDED          PID[1]-3[2].1.1   PID-3[2].1.1 null     '200001'
 *     ADDED          PID[1]-3[2].4.1   PID-3[2].4.1 null     'HOSP'
 *     EMPTY_ADDED    PID[1]-6[1]       PID-6        null     null
 *     EMPTY_ADDED    PID[1]-7[1]       PID-7        null     null
 *
 * The explicit null "" is a value: "" against another value gives CHANGED,
 * "" against an empty element gives ADDED or REMOVED.
 */
final readonly class Difference
{
    /**
     * @param Location $location Location of the difference.
     * @param string|null $left Raw value in the left message; null when absent or empty.
     * @param string|null $right Raw value in the right message; null when absent or empty.
     * @param DifferenceType $type
     */
    public function __construct(
        private Location $location,
        private ?string $left,
        private ?string $right,
        private DifferenceType $type
    ) {
        //
    }

    /**
     * Get the location in long form.
     *
     * Example: PID[1]-5[1].2.1 (short form: PID-5.2.1, see getLocation()).
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->location->toLongString();
    }

    /**
     * Get the location.
     *
     * @return Location
     */
    public function getLocation(): Location
    {
        return $this->location;
    }

    /**
     * Get the Raw value in the left message.
     *
     * @return string|null Null when absent or empty.
     */
    public function getLeft(): ?string
    {
        return $this->left;
    }

    /**
     * Get the Raw value in the right message.
     *
     * @return string|null Null when absent or empty.
     */
    public function getRight(): ?string
    {
        return $this->right;
    }

    /**
     * Get the type of the difference.
     *
     * @return DifferenceType
     */
    public function getType(): DifferenceType
    {
        return $this->type;
    }
}
