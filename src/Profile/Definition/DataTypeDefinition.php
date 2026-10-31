<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\ComponentDefinition;

/**
 * Definition of a data type: a primitive, or a composite with its components.
 */
final readonly class DataTypeDefinition
{
    /**
     * @param list<ComponentDefinition> $components Empty for a primitive.
     */
    public function __construct(
        public string $name,
        public array $components
    ) {
        //
    }

    public function isPrimitive(): bool
    {
        return $this->components === [];
    }

    /**
     * Get the component at a position (1-based), or null.
     */
    public function component(int $position): ?ComponentDefinition
    {
        return $this->components[$position - 1] ?? null;
    }
}
