<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

/**
 * Definition of a field (fields.json): what the field is, wherever it is used.
 * Usage and cardinality belong to its position in a segment (SegmentField).
 */
final readonly class FieldDefinition
{
    /**
     * @param string $id Field id (PID.3).
     * @param string $item HL7 item number (00106).
     * @param string $dataType Data type name (CX).
     * @param int|null $maxLength Null when the profile gives no length.
     * @param string|null $table Table id (0001); null when none.
     * @param string|null $source Source of a value changed by a derived profile.
     */
    public function __construct(
        public string $id,
        public string $item,
        public string $dataType,
        public ?int $maxLength,
        public ?string $table,
        public string $longName,
        public string $chapter,
        public ?string $source = null
    ) {
        //
    }
}
