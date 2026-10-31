<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\SegmentField;

/**
 * Definition of a segment: its fields in order.
 */
final readonly class SegmentDefinition
{
    /**
     * @param list<SegmentField> $fields $fields[0] is field 1.
     */
    public function __construct(
        public string $name,
        public string $longName,
        public string $chapter,
        public array $fields,
        public ?string $source = null
    ) {
        //
    }

    /**
     * Get the field at a position (1-based), or null if the segment has no
     * such field.
     */
    public function field(int $position): ?SegmentField
    {
        return $this->fields[$position - 1] ?? null;
    }

    /**
     * Get a copy of the segment with some fields replaced.
     *
     * @param array<int, SegmentField> $overrides Fields by position.
     */
    public function withFields(array $overrides): self
    {
        return new self(
            $this->name,
            $this->longName,
            $this->chapter,
            array_map(
                static fn (SegmentField $field): SegmentField => $overrides[$field->position] ?? $field,
                $this->fields
            ),
            $this->source
        );
    }
}
