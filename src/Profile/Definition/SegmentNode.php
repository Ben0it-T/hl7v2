<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;

/**
 * A segment at one place of a message structure. Its definition already
 * carries the field overrides of this message: the engine never
 * merges two files.
 */
final readonly class SegmentNode extends StructureNode
{
    public function __construct(
        Usage $usage,
        Cardinality $cardinality,
        public SegmentDefinition $definition,
        ?string $source = null
    ) {
        parent::__construct(
            $definition->name,
            $usage,
            $cardinality,
            $cardinality->min === 0,
            [$definition->name],
            $source
        );
    }
}
