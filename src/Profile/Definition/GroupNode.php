<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;

/**
 * A group of a message structure (the root is the structure itself:
 * ORU_R01, R [1..1]).
 */
final readonly class GroupNode extends StructureNode
{
    /**
     * @param list<StructureNode> $children In profile order.
     */
    public function __construct(
        string $name,
        Usage $usage,
        Cardinality $cardinality,
        public array $children,
        ?string $source = null
    ) {
        parent::__construct($name, $usage, $cardinality, $source);
    }
}
