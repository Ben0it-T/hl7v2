<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\Usage;

/**
 * A node of a message structure: a group or a segment, with its usage and
 * cardinality at this place of the structure.
 */
abstract readonly class StructureNode
{
    /**
     * @param string|null $source Source of the usage and cardinality, when
     *                            set by a derived profile.
     */
    public function __construct(
        public string $name,
        public Usage $usage,
        public Cardinality $cardinality,
        public ?string $source = null
    ) {
        //
    }
}
