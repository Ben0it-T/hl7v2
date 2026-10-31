<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\FieldDefinition;
use HL7v2\Profile\Definition\Usage;

/**
 * A field at its position in a segment: usage and cardinality, with the
 * field definition.
 */
final readonly class SegmentField
{
    /**
     * @param int $position 1-based.
     * @param string|null $source Source of the usage and cardinality, when
     *                            set by a derived profile.
     */
    public function __construct(
        public int $position,
        public Usage $usage,
        public Cardinality $cardinality,
        public FieldDefinition $definition,
        public ?string $source = null
    ) {
        //
    }
}
