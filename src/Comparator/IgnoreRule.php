<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

final readonly class IgnoreRule
{
    /**
     * Represents an ignore rule used by the HL7 comparator.
     *
     * Any nullable property set to null acts as a wildcard.
     *
     * Examples:
     *
     * MSH-7
     *     Ignore field MSH-7 and all its components/sub-components.
     *
     * PID-3
     *      Ignore field PID-3 and all its repetitions, components and sub-components.
     *
     * PID-3.4
     *     Ignore component PID-3.4 and all its sub-components.
     *
     * PID-3.4.1
     *     Ignore sub-component PID-3.4.1 only.
     *
     * PID[2]-3[0].4.1
     *     Ignore only sub-component PID-3.4.1
     *     in segment index 2 and repeat index 0.
     *
     * Internal representation using wildcards (null):
     *
     * new IgnoreRule(
     *     segmentName: 'PID',
     *     segmentIndex: null,
     *     fieldOffset: 3,
     *     repeatIndex: null,
     *     componentOffset: 4,
     *     subComponentOffset: null
     * );
     *
     * Equivalent to:
     *
     * `PID[*]-3[*].4`
     *
     * Meaning:
     *     Ignore component PID-3.4 for all segment indexes,
     *     all field repetitions and all sub-components.
     *
     *     > Ignore all paths matching: `PID[*]-3[*].4.*`
     *     PID[0]-3[0].4.1
     *     PID[0]-3[0].4.2
     *     PID[0]-3[1].4.1
     *     PID[5]-3[0].4.1
     */

    public function __construct(
        private string $segmentName,
        private ?int $segmentIndex,
        private ?int $fieldOffset,
        private ?int $repeatIndex,
        private ?int $componentOffset,
        private ?int $subComponentOffset
    ) {
        //
    }

    public function getSegmentName(): string
    {
        return $this->segmentName;
    }

    public function getSegmentIndex(): ?int
    {
        return $this->segmentIndex;
    }

    public function getFieldOffset(): ?int
    {
        return $this->fieldOffset;
    }

    public function getRepeatIndex(): ?int
    {
        return $this->repeatIndex;
    }

    public function getComponentOffset(): ?int
    {
        return $this->componentOffset;
    }

    public function getSubComponentOffset(): ?int
    {
        return $this->subComponentOffset;
    }
}
