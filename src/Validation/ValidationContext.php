<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Message;
use HL7v2\Model\Validation\NavigationState;

final class ValidationContext
{
    /**
     * Current segment position in Message.
     *
     * Note : equivalent of legacy $segmentLocation.
     */
    public int $messageSegmentIndex = 0;

    /**
     * Current segment position in Profile.
     *
     * Note : equivalent of legacy $profileSegmentLocation.
     */
    public int $profileSegmentIndex = 0;

    /**
     * Requests parent group to re-evaluate the current profile element.
     *
     * Note : Equivalent of legacy
     * $profileLocationMoveBack.
     */
    public bool $moveBack = false;

    /**
     * First segment names of repeating parent groups.
     *
     * Note : equivalent of legacy $profileParentGroupFirstSegmentsName.
     *
     * @var list<string>
     */
    public array $parentGroupFirstSegments = [];

    /**
     * All segment names from profile.
     *
     * @var array<string>
     */
    public array $profileSegmentNames = [];

    /**
     * Segment names found in message
     * but missing from profile.
     *
     * @var list<string>
     */
    public array $notDefinedSegments = [];

    /**
     * Segment names present in profile
     * but missing from message.
     *
     * @var list<string>
     */
    public array $notPresentSegments = [];




    /**
     * Check whether a group exists in the message, starting from the current message position.
     *
     * A group is considered to exist if its first segment appears later in the message.
     *
     * @param Message $message
     * @param string $firstSegmentNameInGroup
     *
     * @return bool
     */
    public function isGroupExists(Message $message, string $firstSegmentNameInGroup): bool
    {
        $messageSegmentNames = $message->getSegmentNames();

        $remainingSegments = array_slice($messageSegmentNames, $this->messageSegmentIndex);

        return in_array($firstSegmentNameInGroup, $remainingSegments, true);
    }

    /**
     * Count group repetitions in message,
     * starting from the current message position.
     *
     * @param Message $message
     * @param string $firstSegmentNameInGroup
     * @param string[] $segmentsInGroup
     *
     * @return int
     */
    public function countGroupRepetitions(Message $message, string $firstSegmentNameInGroup, array $segmentsInGroup): int
    {
        $groupRepetitions = 0;

        for ($i = $this->messageSegmentIndex; $i < $message->countSegments(); $i++) {
            $segment = $message->getSegment($i);

            if ($segment === null) {
                break;
            }

            $segmentName = $segment->getName();

            if ($segmentName === $firstSegmentNameInGroup) {
                $groupRepetitions++;
            }

            if (in_array($segmentName, $this->notDefinedSegments, true)) {
                // Segment is not defined in profile.
                // It does not break the current group.
                // Ignore it when counting the group boundary.
                continue;
            }

            if (!in_array($segmentName, $segmentsInGroup, true)) {
                // not in the group
                break;
            }
        }

        return $groupRepetitions;
    }

    /**
     * Check whether a segment appears later in the profile structure.
     *
     * @param string $segmentName
     *
     * @return bool
     */
    public function isSegmentLaterInProfileStructure(string $segmentName): bool
    {
        $profileNextSegmentsNames = [];

        for ($i = $this->profileSegmentIndex + 1; $i < count($this->profileSegmentNames); $i++) {
            $profileNextSegmentsNames[] = $this->profileSegmentNames[$i];
        }

        return in_array($segmentName, $profileNextSegmentsNames, true);
    }

}
