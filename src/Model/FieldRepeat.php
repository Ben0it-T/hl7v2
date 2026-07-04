<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\Component;

class FieldRepeat
{
    /**
     * @var Component[]
     */
    private array $components = [];

    /**
     * Add component
     *
     * @param Component $component
     */
    public function addComponent(Component $component): void
    {
        $this->components[] = $component;
    }

    /**
     * Get component
     *
     * HL7 positions are 1-based.
     *
     * @param int $index
     * @return Component|null
     */
    public function getComponent(int $index): ?Component
    {
        return $this->components[$index - 1] ?? null;
    }

    /**
     * Get components
     *
     * @return Component[]
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
