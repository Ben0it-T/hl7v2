<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\IgnoreRule;
use HL7v2\Comparator\Path;

final class IgnoreRuleMatcher
{
    /**
     * Determine whether a flattened HL7 path matches an ignore rule.
     *
     * Any null value in IgnoreRule acts as a wildcard.
     *
     * Examples:
     *
     * Rule:
     *     PID-3.4
     *
     * Matches:
     *     PID[0]-3[0].4.1
     *     PID[5]-3[1].4.2
     *
     * Does not match:
     *     PID[0]-3[0].5.1
     *     PID[0]-4[0].4.1
     *
     * @param IgnoreRule $rule
     * @param Path $path
     * @return bool
     */
    public function matches(IgnoreRule $rule, Path $path): bool
    {

        if ($rule->getSegmentName() !== $path->getSegmentName()) {
            return false;
        }

        if ($rule->getSegmentIndex() !== null && $rule->getSegmentIndex() !== $path->getSegmentIndex()) {
            return false;
        }

        if ($rule->getFieldOffset() !== null && $rule->getFieldOffset() !== $path->getFieldOffset()) {
            return false;
        }

        if ($rule->getRepeatIndex() !== null && $rule->getRepeatIndex() !== $path->getRepeatIndex()) {
            return false;
        }

        if ($rule->getComponentOffset() !== null && $rule->getComponentOffset() !== $path->getComponentOffset()) {
            return false;
        }

        if ($rule->getSubComponentOffset() !== null && $rule->getSubComponentOffset() !== $path->getSubComponentOffset()) {
            return false;
        }

        return true;
    }
}
