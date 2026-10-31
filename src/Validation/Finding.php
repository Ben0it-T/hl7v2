<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Location;
use HL7v2\Validation\Severity;
use HL7v2\Validation\FindingType;

/**
 * A finding of the validation: a problem met in a message, or a check that
 * could not be done.
 *
 * A finding carries up to three positions:
 * - the HL7 location (PID[1]-3[1].4.1), when the finding is on an element
 *   of the message;
 * - the path in the profile (PATIENT_RESULT[1]/ORDER_OBSERVATION[2]/OBR),
 *   when known;
 * - the position of the segment in the message (1 for MSH), when the
 *   finding is on a segment of the message.
 *
 * Example: a required field absent
 *     severity:     Error
 *     type:         requiredAbsent
 *     location:     PID[1]-3[1]          (short form: PID-3)
 *     profilePath:  PID
 *     segment:      3
 *     message:      Field 'Patient Identifier List' (PID.3) is required.
 *
 * Example: a required segment absent (no location, no segment)
 *     severity:     Error
 *     type:         requiredAbsent
 *     profilePath:  PV1
 *     message:      Segment 'PV1' is required.
 */
final readonly class Finding
{
    /**
     * @param Severity $severity
     * @param FindingType $type
     * @param string $message Description of the finding.
     * @param Location|null $location HL7 location, when on an element of the message.
     * @param string|null $profilePath Path in the profile, when known.
     * @param int|null $segment Position of the segment in the message (1-based), when on a segment.
     */
    public function __construct(
        public Severity $severity,
        public FindingType $type,
        public string $message,
        public ?Location $location = null,
        public ?string $profilePath = null,
        public ?int $segment = null
    ) {
        //
    }

    /**
     * Check whether the finding makes the message invalid.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->severity === Severity::Error;
    }

    /**
     * Get the finding as an array (location in short form).
     *
     * @return array{
     *     severity: string,
     *     type: string,
     *     location: string|null,
     *     profilePath: string|null,
     *     segment: int|null,
     *     message: string
     * }
     */
    public function toArray(): array
    {
        return [
            'severity'    => $this->severity->value,
            'type'        => $this->type->value,
            'location'    => $this->location?->toShortString(),
            'profilePath' => $this->profilePath,
            'segment'     => $this->segment,
            'message'     => $this->message,
        ];
    }
}
