<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\DifferenceType;

final readonly class Difference
{
    /**
     * Represents a difference detected between two HL7 messages.
     *
     * Path format:
     * SEGMENT[segmentIndex]-FIELD[repeatIndex].COMPONENT.SUBCOMPONENT
     *
     * Example:
     * PID[2]-3[0].4.1
     *
     * Difference types:
     * - ADDED
     * - REMOVED
     * - CHANGED
     *
     */
    public function __construct(
        private string $path,
        private ?string $left,
        private ?string $right,
        private DifferenceType $type
    ) {
        //
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLeft(): ?string
    {
        return $this->left;
    }

    public function getRight(): ?string
    {
        return $this->right;
    }

    public function getType(): DifferenceType
    {
        return $this->type;
    }
}
