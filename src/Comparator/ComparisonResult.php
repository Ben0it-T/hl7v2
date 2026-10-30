<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\Difference;

/**
 * Result of the comparison of two HL7 messages: the differences found, of
 * data and of form, in message order.
 */
final class ComparisonResult
{
    /**
     * @var list<Difference>
     */
    private array $differences = [];

    private int $comparedPaths;

    /**
     * @param int $comparedPaths Number of non-empty sub-component values compared.
     */
    public function __construct(int $comparedPaths)
    {
        $this->comparedPaths = $comparedPaths;
    }

    /**
     * Add a difference.
     *
     * @param Difference $difference
     *
     * @return void
     */
    public function addDifference(Difference $difference): void
    {
        $this->differences[] = $difference;
    }

    /**
     * Get all differences.
     *
     * Every difference, of data and of form, in message order.
     *
     * @return list<Difference>
     */
    public function getDifferences(): array
    {
        return $this->differences;
    }

    /**
     * Get the data differences.
     *
     * Differences of data only (ADDED, REMOVED, CHANGED), without the form
     * differences (EMPTY_ADDED, EMPTY_REMOVED).
     *
     * @return list<Difference>
     */
    public function getDataDifferences(): array
    {
        return array_values(array_filter(
            $this->differences,
            static fn (Difference $difference): bool => $difference->getType()->isDataDifference()
        ));
    }

    /**
     * Get compared paths.
     *
     * Number of non-empty sub-component values compared (union of both
     * messages).
     *
     * @return int
     */
    public function getComparedPaths(): int
    {
        return $this->comparedPaths;
    }

    /**
     * Count differences.
     *
     * Differences of data and of form.
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
     * Identical in data and in form: true when no difference was found.
     *
     * @return bool
     */
    public function isIdentical(): bool
    {
        return $this->differences === [];
    }

    /**
     * Check whether differences were found.
     *
     * Differences of data or of form.
     *
     * @return bool
     */
    public function hasDifferences(): bool
    {
        return !$this->isIdentical();
    }
}
