<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

/**
 * Type of a difference between two HL7 messages.
 *
 * Data differences: ADDED, REMOVED, CHANGED (Raw values of sub-components).
 * Form differences: EMPTY_ADDED, EMPTY_REMOVED (empty elements); for HL7, an
 * empty element and an absent one carry the same data (not present).
 */
enum DifferenceType
{
    /** Value present in the right message only. */
    case ADDED;

    /** Value present in the left message only. */
    case REMOVED;

    /** Different values. */
    case CHANGED;

    /** Empty element present in the right message only (form, not data). */
    case EMPTY_ADDED;

    /** Empty element present in the left message only (form, not data). */
    case EMPTY_REMOVED;

    /**
     * Check whether the difference is about data (ADDED, REMOVED, CHANGED)
     * rather than form (EMPTY_ADDED, EMPTY_REMOVED).
     *
     * @return bool
     */
    public function isDataDifference(): bool
    {
        return match ($this) {
            self::EMPTY_ADDED, self::EMPTY_REMOVED => false,
            default                                => true,
        };
    }
}
