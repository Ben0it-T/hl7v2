<?php
declare(strict_types=1);

namespace HL7v2\Validation;

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
}
