<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\ComparisonResult;
use HL7v2\Comparator\Difference;
use HL7v2\Comparator\DifferenceType;
use HL7v2\Comparator\IgnoreRule;
use HL7v2\Comparator\IgnoreRuleMatcher;
use HL7v2\Comparator\IgnoreRuleParser;
use HL7v2\Comparator\MessageFlattener;
use HL7v2\Model\Location;
use HL7v2\Model\Message;

use InvalidArgumentException;

/**
 * Compare two HL7 messages.
 *
 * Data: the Raw values of the sub-components are compared; an empty element
 * and an absent one are equal (HL7: not present), the explicit null "" is a
 * value. Differences ADDED, REMOVED, CHANGED, located on the sub-component.
 *
 * Form: an empty element present on one side only, whose parent is present
 * on both sides, is reported as EMPTY_ADDED or EMPTY_REMOVED (for example
 * trailing empty fields).
 *
 * Segments are ranked among the segments of the same name: a segment
 * inserted in a message does not shift the following ones. Differences are
 * listed in message order: the elements of the right message only are
 * inserted after the element that precedes them in the right message.
 */
final class HL7Comparator
{
    /**
     * Compare two HL7 messages.
     *
     * @param Message $left
     * @param Message $right
     * @param string[] $ignoreRules Ignore rules (see IgnoreRule).
     *
     * @return ComparisonResult
     *
     * @throws InvalidArgumentException On an invalid ignore rule.
     */
    public function compare(Message $left, Message $right, array $ignoreRules = []): ComparisonResult
    {
        $flattener = new MessageFlattener();
        $leftFlat  = $flattener->flatten($left);
        $rightFlat = $flattener->flatten($right);

        $parser = new IgnoreRuleParser();
        $rules = array_values(array_map(
            static fn (string $rule): IgnoreRule => $parser->parse($rule),
            $ignoreRules
        ));
        $matcher = new IgnoreRuleMatcher();

        $result = new ComparisonResult(count($leftFlat['values'] + $rightFlat['values']));

        foreach ($this->mergedKeys($leftFlat['elements'], $rightFlat['elements']) as $key) {
            $element = $leftFlat['elements'][$key] ?? $rightFlat['elements'][$key];
            $location = $element['location'];
            if ($this->isIgnored($location, $rules, $matcher)) {
                continue;
            }

            // Data: a non-empty value on at least one side.
            $leftValue  = $leftFlat['values'][$key] ?? null;
            $rightValue = $rightFlat['values'][$key] ?? null;
            if ($leftValue !== null || $rightValue !== null) {
                $type = match (true) {
                    $leftValue === null        => DifferenceType::ADDED,
                    $rightValue === null       => DifferenceType::REMOVED,
                    $leftValue !== $rightValue => DifferenceType::CHANGED,
                    default                    => null,
                };
                if ($type !== null) {
                    $result->addDifference(new Difference($location, $leftValue, $rightValue, $type));
                }
                continue;
            }

            // Form: an empty element present on one side only.
            $inLeft  = isset($leftFlat['elements'][$key]);
            $inRight = isset($rightFlat['elements'][$key]);
            if ($inLeft === $inRight || !$element['empty']) {
                continue;
            }

            // Report only the highest empty element: its parent is on both sides.
            $parent = $element['parent'];
            $other = $inLeft ? $rightFlat : $leftFlat;
            if ($parent !== null && !isset($other['elements'][$parent])) {
                continue;
            }

            $result->addDifference(new Difference(
                $location,
                null,
                null,
                $inLeft ? DifferenceType::EMPTY_REMOVED : DifferenceType::EMPTY_ADDED
            ));
        }

        return $result;
    }

    /**
     * Check whether a location matches one of the ignore rules.
     *
     * @param Location $location
     * @param list<IgnoreRule> $rules
     * @param IgnoreRuleMatcher $matcher
     *
     * @return bool
     */
    private function isIgnored(Location $location, array $rules, IgnoreRuleMatcher $matcher): bool
    {
        foreach ($rules as $rule) {
            if ($matcher->matches($rule, $location)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the keys of the elements of both messages, in message order.
     *
     * The elements of the left message keep their order. Each element of
     * the right message only is inserted after its anchor: the last element
     * present in both messages that precedes it in the right message.
     *
     * Example:
     *     left:   PID[1]-8[1].1.1, PV1[1]
     *     right:  PID[1]-8[1].1.1, PID[1]-9, PV1[1]
     *     merged: PID[1]-8[1].1.1, PID[1]-9, PV1[1]
     *
     * @param array<string, mixed> $left Elements of the left message, by key.
     * @param array<string, mixed> $right Elements of the right message, by key.
     *
     * @return list<string>
     */
    private function mergedKeys(array $left, array $right): array
    {
        // Elements of the right message only, by anchor ('' before any common element).
        $inserted = [];
        $anchor = '';
        foreach (array_keys($right) as $key) {
            if (isset($left[$key])) {
                $anchor = $key;
            } else {
                $inserted[$anchor][] = $key;
            }
        }

        $keys = $inserted[''] ?? [];
        foreach (array_keys($left) as $key) {
            $keys[] = $key;
            foreach ($inserted[$key] ?? [] as $rightKey) {
                $keys[] = $rightKey;
            }
        }

        return $keys;
    }
}
