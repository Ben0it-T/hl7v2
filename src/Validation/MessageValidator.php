<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Message;
use HL7v2\Profile\MessageProfile;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\Content\ContentChecker;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Structure\StructureChecker;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\ValidationOutcome;
use HL7v2\Validation\ValidationResult;

/**
 * Validates a message against a message profile of the normalized format:
 * structure > problems met while parsing > content pass
 * A problem met while parsing is attached to the segment it is located on.
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

        $tree = (new StructureMatcher())->match($profile->root, $message);
        $profiled = (new StructureChecker())->check($tree, $recorder);

        foreach ($message->getParseIssues() as $issue) {
            $segment = $issue->location === null ? null : $profiled->segmentOf($issue->location);
            $finding = $recorder->record(new Check(
                'Parse', false, $issue->message, FindingType::ParseIssue,
                $issue->location, null, $segment?->position
            ));
            if ($finding !== null && $segment !== null) {
                $profiled->attach($segment, $finding);
            }
        }

        (new ContentChecker())->check($profiled, $message, $recorder);

        return new ValidationOutcome($result, $profiled);
    }
}
