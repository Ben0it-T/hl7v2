<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\FieldRepeat;

class Field
{
    /**
     * @var FieldRepeat[]
     */
    private array $repeats = [];

    /**
     * Add repeat
     *
     * @param FieldRepeat $repeat
     */
    public function addRepeat(FieldRepeat $repeat): void
    {
        $this->repeats[] = $repeat;
    }

    /**
     * Get repeat
     *
     * Repetitions are stored using a 0-based index.
     *
     * @param int $index
     * @return FieldRepeat|null
     */
    public function getRepeat(int $index): ?FieldRepeat
    {
        return $this->repeats[$index] ?? null;
    }

    /**
     * Get repeats
     *
     * @return FieldRepeat[]
     */
    public function getRepeats(): array
    {
        return $this->repeats;
    }

}
