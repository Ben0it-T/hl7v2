<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\ComparisonResult;
use HL7v2\Comparator\Difference;
use HL7v2\Comparator\DifferenceType;
use HL7v2\Comparator\IgnoreRuleMatcher;
use HL7v2\Comparator\IgnoreRuleParser;
use HL7v2\Comparator\MessageFlattener;
use HL7v2\Comparator\PathParser;
use HL7v2\Model\Message;

final class HL7Comparator
{
    /**
     * Compare this HL7 message with another HL7 message.
     *
     * @param Message $left
     * @param Message $right
     * @param string[] $ignoreRules
     *
     * @return ComparisonResult
     */
    public function compare(Message $left, Message $right, array $ignoreRules = []): ComparisonResult
    {
        $flattener = new MessageFlattener();

        $leftPaths = $flattener->flatten($left);
        $rightPaths = $flattener->flatten($right);

        $ruleParser = new IgnoreRuleParser();
        $pathParser = new PathParser();
        $matcher = new IgnoreRuleMatcher();

        $parsedRules = [];

        foreach ($ignoreRules as $rule) {
            $parsedRules[] = $ruleParser->parse($rule);
        }

        $allPaths = array_unique(
            array_merge(
                array_keys($leftPaths),
                array_keys($rightPaths)
            )
        );

        $result = new ComparisonResult(count($allPaths));

        sort($allPaths);

        foreach ($allPaths as $pathString) {

            $path = $pathParser->parse($pathString);

            $ignored = false;

            foreach ($parsedRules as $rule) {
                if ($matcher->matches($rule, $path)) {
                    $ignored = true;
                    break;
                }
            }

            if ($ignored) {
                continue;
            }

            $existsLeft  = array_key_exists($pathString, $leftPaths);
            $existsRight = array_key_exists($pathString, $rightPaths);

            if (!$existsLeft && $existsRight) {
                $result->addDifference(
                    new Difference(
                        path: $pathString,
                        left: null,
                        right: $rightPaths[$pathString],
                        type: DifferenceType::ADDED
                    )
                );

                continue;
            }

            if ($existsLeft && !$existsRight) {
                $result->addDifference(
                    new Difference(
                        path: $pathString,
                        left: $leftPaths[$pathString],
                        right: null,
                        type: DifferenceType::REMOVED
                    )
                );

                continue;
            }

            if ($leftPaths[$pathString] !== $rightPaths[$pathString]) {
                $result->addDifference(
                    new Difference(
                        path: $pathString,
                        left: $leftPaths[$pathString],
                        right: $rightPaths[$pathString],
                        type: DifferenceType::CHANGED
                    )
                );
            }
        }

        return $result;
    }
}
