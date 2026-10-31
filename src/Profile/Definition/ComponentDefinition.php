<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\Usage;

/**
 * A component of a composite data type, or a sub-component when the
 * composite is itself the type of a component.
 */
final readonly class ComponentDefinition
{
    /**
     * @param string $id Component id (HD.1).
     * @param int $position 1-based.
     * @param int|null $maxLength Null when the profile gives no length.
     * @param string|null $table Table id; null when none.
     */
    public function __construct(
        public string $id,
        public int $position,
        public string $dataType,
        public Usage $usage,
        public Cardinality $cardinality,
        public ?int $maxLength,
        public ?string $table,
        public string $longName,
        public ?string $source = null
    ) {
        //
    }
}
