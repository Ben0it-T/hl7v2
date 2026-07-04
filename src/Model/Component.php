<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\SubComponent;

class Component
{
    /**
     * @var SubComponent[]
     */
    private array $subComponents = [];

    /**
     * Add sub-component
     *
     * @param SubComponent $subComponent
     */
    public function addSubComponent(SubComponent $subComponent): void
    {
        $this->subComponents[] = $subComponent;
    }

    /**
     * Get sub-component
     *
     * HL7 positions are 1-based.
     *
     * @param int $index
     * @return SubComponent|null
     */
    public function getSubComponent(int $index): ?SubComponent
    {
        return $this->subComponents[$index - 1] ?? null;
    }

    /**
     * Get sub-components
     *
     * @return SubComponent[]
     */
    public function getSubComponents(): array
    {
        return $this->subComponents;
    }
}
