<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

/**
 * Cardinality of an element: minimum and maximum number of occurrences.
 * A null maximum is unbounded (*).
 */
final readonly class Cardinality
{
    public function __construct(
        public int $min,
        public ?int $max
    ) {
        //
    }

    /**
     * Read a cardinality from the strings of a profile ("0", "*").
     *
     * @return Cardinality|null Null if a bound is not a number, or if max < min.
     */
    public static function parse(string $min, string $max): ?self
    {
        if (!ctype_digit($min) || ($max !== '*' && !ctype_digit($max))) {
            return null;
        }

        $cardinality = new self((int) $min, $max === '*' ? null : (int) $max);

        return $cardinality->max !== null && $cardinality->max < $cardinality->min
            ? null
            : $cardinality;
    }

    /**
     * Get the cardinality as written in HL7: [0..*].
     */
    public function toString(): string
    {
        return sprintf('[%d..%s]', $this->min, $this->max ?? '*');
    }
}
