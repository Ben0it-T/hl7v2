<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Location;
use HL7v2\Validation\FindingType;

/**
 * A check done by the validation: a TestReport entry, and a finding
 * when it gives one.
 * A failed check always gives a finding; a check that
 * passes may give one too (an Info: conditional element present).
 */
final readonly class Check
{
    /**
     * @param string $type TestReport type (Usage, Cardinality, Structure...).
     * @param bool $result True when the element conforms.
     * @param FindingType|null $finding Finding given by the check.
     * @param Location|null $location HL7 location, when on an element of the message.
     * @param string|null $profilePath Path in the profile (Q213).
     * @param int|null $segment Position of the segment in the message (1-based).
     */
    public function __construct(
        public string $type,
        public bool $result,
        public string $description,
        public ?FindingType $finding = null,
        public ?Location $location = null,
        public ?string $profilePath = null,
        public ?int $segment = null
    ) {
        if (!$result && $finding === null) {
            throw new \InvalidArgumentException('A failed check gives a finding.');
        }
    }
}
