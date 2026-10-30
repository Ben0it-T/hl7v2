<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\IgnoreRule;

use InvalidArgumentException;

/**
 * Parse the ignore rules of the comparator (see IgnoreRule).
 */
final class IgnoreRuleParser
{
    private const PATTERN =
        '/^([A-Z][A-Z0-9]{2})'           // segment name
        . '(?:\[(\d+|\*)\])?'            // segment rank
        . '(?:-(\d+)'                    // field
        . '(?:\[(\d+|\*)\])?'            // repetition
        . '(?:\.(\d+)'                   // component
        . '(?:\.(\d+))?'                 // sub-component
        . ')?)?$/';

    /**
     * Parse an ignore rule.
     *
     * Examples:
     * - ZBE
     * - NK1[2]
     * - MSH-7
     * - PID-3
     * - PID-3.4
     * - PID-3.4.1
     * - PID[*]-3[*].4
     * - PID[1]-3[2].4.1
     *
     * Wildcards (*) and missing indexes are represented as null.
     *
     * @param string $rule
     *
     * @return IgnoreRule
     *
     * @throws InvalidArgumentException On an invalid rule, or an index 0
     *                                  (indexes start at 1).
     */
    public function parse(string $rule): IgnoreRule
    {
        $rule = trim($rule);

        if (preg_match(self::PATTERN, $rule, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid ignore rule "%s".', $rule));
        }

        $index = static function (int $i) use ($m, $rule): ?int {
            $value = $m[$i] ?? '';
            if ($value === '' || $value === '*') {
                return null;
            }
            if ((int) $value < 1) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid ignore rule "%s": indexes start at 1; a segment is counted among the segments of the same name.',
                    $rule
                ));
            }

            return (int) $value;
        };

        return new IgnoreRule($m[1], $index(2), $index(3), $index(4), $index(5), $index(6));
    }
}
