<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\IgnoreRule;

use InvalidArgumentException;

final class IgnoreRuleParser
{
    /**
     * Parse an HL7 ignore rule.
     *
     * Examples:
     * - MSH-7
     * - PID-3
     * - PID-3.4
     * - PID-3.4.1
     * - PID[*]-3[*].4
     * - PID[2]-3[0].4.1
     *
     * Wildcards (*) are internally represented as null.
     *
     * @param string $rule
     * @return IgnoreRule
     */
    public function parse(string $rule): IgnoreRule
    {
        $rule = trim($rule);

        $pattern =
            '/^' .
            '([A-Z0-9]{3})' .            // segment
            '(?:\[(\d+|\*)\])?' .        // segment index
            '\-' .
            '(\d+)' .                    // field
            '(?:\[(\d+|\*)\])?' .        // repeat index
            '(?:\.(\d+))?' .             // component
            '(?:\.(\d+))?' .             // subcomponent
            '$/';

        if (!preg_match($pattern, $rule, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid ignore rule "%s"', $rule)
            );
        }

        return new IgnoreRule(
            segmentName: $matches[1],
            segmentIndex: $this->parseWildcardInt($matches[2]),
            fieldOffset: (int) $matches[3],
            repeatIndex: $this->parseWildcardInt($matches[4] ?? null),
            componentOffset: isset($matches[5])
                ? (int) $matches[5]
                : null,
            subComponentOffset: isset($matches[6])
                ? (int) $matches[6]
                : null
        );
    }

    private function parseWildcardInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === '*') {
            return null;
        }

        return (int) $value;
    }
}
