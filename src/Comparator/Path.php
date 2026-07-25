<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

final readonly class Path
{
    /**
     * Represents a flattened HL7 path.
     *
     * Path format:
     * SEGMENT[segmentIndex]-fieldOffset[repeatIndex].componentOffset.subComponentOffset
     *
     * Example:
     * PID[2]-3[0].4.1
     */

    public function __construct(
        private string $segmentName,
        private int $segmentIndex,
        private int $fieldOffset,
        private int $repeatIndex,
        private int $componentOffset,
        private int $subComponentOffset
    ) {
        //
    }

    public function getSegmentName(): string
    {
        return $this->segmentName;
    }

    public function getSegmentIndex(): int
    {
        return $this->segmentIndex;
    }

    public function getFieldOffset(): int
    {
        return $this->fieldOffset;
    }

    public function getRepeatIndex(): int
    {
        return $this->repeatIndex;
    }

    public function getComponentOffset(): int
    {
        return $this->componentOffset;
    }

    public function getSubComponentOffset(): int
    {
        return $this->subComponentOffset;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s[%d]-%d[%d].%d.%d',
            $this->segmentName,
            $this->segmentIndex,
            $this->fieldOffset,
            $this->repeatIndex,
            $this->componentOffset,
            $this->subComponentOffset
        );
    }
}
