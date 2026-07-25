<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\Difference;

final class ComparisonResult
{
    /**
     * @var Difference[]
     */
    private array $differences = [];

    /**
     * Add a difference.
     *
     * @param Difference $difference
     * @return void
     */
    public function addDifference(Difference $difference): void
    {
        $this->differences[] = $difference;
    }

    /**
     * Get all differences.
     *
     * @return Difference[]
     */
    public function getDifferences(): array
    {
        return $this->differences;
    }

    /**
     * Count differences.
     *
     * @return int
     */
    public function countDifferences(): int
    {
        return count($this->differences);
    }

    /**
     * Check whether both messages are identical.
     *
     * @return bool
     */
    public function isIdentical(): bool
    {
        return count($this->differences) === 0;
    }

    /**
     * Check whether differences were found.
     *
     * @return bool
     */
    public function hasDifferences(): bool
    {
        return !$this->isIdentical();
    }
}
