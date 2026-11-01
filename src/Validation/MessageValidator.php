<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Location;
use HL7v2\Model\Message;
use HL7v2\Profile\MessageProfile;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Structure\StructureChecker;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\ValidationOutcome;
use HL7v2\Validation\ValidationResult;

/**
 * Validates a message against a message profile of the normalized format:
 * the problems met while parsing first, then the structure pass.
 *
 * Every check is recorded through one CheckRecorder: the TestReport and the
 * findings of the result, with the severities of the rules of the profile
 * directory.
 */
final class MessageValidator
{
    /**
     * Validate a message.
     *
     * @param Message $message
     * @param MessageProfile $profile Message profile of the message (ProfileDirectory::messageProfile()).
     *
     * @return ValidationOutcome The result (TestReport, findings) and the Profiled message.
     */
    public function validate(Message $message, MessageProfile $profile): ValidationOutcome
    {
        $result = new ValidationResult();
        $recorder = new CheckRecorder($profile->directory->rules, $result, $profile->structure);

        foreach ($message->getParseIssues() as $issue) {
            $recorder->record(new Check(
                'Parse', false, $issue->message, FindingType::ParseIssue,
                $issue->location, null, self::segmentPosition($message, $issue->location)
            ));
        }

        $tree = (new StructureMatcher())->match($profile->root, $message);
        $profiled = (new StructureChecker())->check($tree, $recorder);

        return new ValidationOutcome($result, $profiled);
    }

    /**
     * Get the position (1-based) in the message of the segment of a location.
     *
     * @param Message $message
     * @param Location|null $location
     *
     * @return int|null Null without location, or when the segment is not in the message.
     */
    private static function segmentPosition(Message $message, ?Location $location): ?int
    {
        if ($location === null) {
            return null;
        }

        $rank = 0;
        foreach (array_values($message->getSegments()) as $index => $segment) {
            if ($segment->getName() === $location->segment && ++$rank === $location->segmentRank) {
                return $index + 1;
            }
        }

        return null;
    }
}
