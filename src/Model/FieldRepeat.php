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
     * HL7 component positions are 1-based.
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

    /**
     * Count components
     *
     * @return int
     */
    public function countComponents(): int
    {
        return count($this->components);
    }

    /**
     * Check if a component exists.
     *
     * HL7 component positions are 1-based.
     *
     * @param int $index
     * @return bool
     */
    public function hasComponent(int $index): bool
    {
        return isset($this->components[$index - 1]);
    }

}
