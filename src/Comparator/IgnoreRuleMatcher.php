<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\IgnoreRule;
use HL7v2\Model\Location;

/**
 * Match locations against the ignore rules of the comparator.
 */
final class IgnoreRuleMatcher
{
    /**
     * Check whether a location matches an ignore rule.
     *
     * A null property of the rule matches every index. A rule on a deeper
     * level than the location (a component rule for a field location) does
     * not match it.
     *
     * Examples:
     *
     * Rule:
     *     PID-3.4
     *
     * Matches:
     *     PID[1]-3[1].4.1
     *     PID[2]-3[3].4.2
     *
     * Does not match:
     *     PID[1]-3[1].5.1
     *     PID[1]-4[1].4.1
     *     PID[1]-3[1]       (field location: the rule is deeper)
     *
     * @param IgnoreRule $rule
     * @param Location $location
     *
     * @return bool
     */
    public function matches(IgnoreRule $rule, Location $location): bool
    {
        if ($rule->segment !== $location->segment) {
            return false;
        }

        foreach ([
            [$rule->segmentRank, $location->segmentRank],
            [$rule->field, $location->field],
            [$rule->repetition, $location->repetition],
            [$rule->component, $location->component],
            [$rule->subComponent, $location->subComponent],
        ] as [$expected, $actual]) {
            if ($expected !== null && $expected !== $actual) {
                return false;
            }
        }

        return true;
    }
}
