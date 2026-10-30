<?php
declare(strict_types=1);

namespace HL7v2\Model;

use InvalidArgumentException;

/**
 * Position of an element in an HL7 message, counted from 1.
 *
 * Long form:  SEG[segmentRank]-field[repetition].component.subComponent
 *             PID[1]-3[2].4.1
 * Short form: indexes equal to 1 are omitted
 *             PID-3[2].4.1, PID-3.4.1
 *
 * The segment rank counts the segments of the same name in the message
 * (the second NK1 is NK1[2], whatever segments come before it).
 * A location may stop at any level:
 * - segment (PV1[2]),
 * - field (PID[1]-3),
 * - repetition (PID[1]-3[2]),
 * - component (PID[1]-3[2].4) or sub-component.
 */
final readonly class Location
{
    private const PATTERN =
        '/^([A-Z][A-Z0-9]{2})'         // segment name
        . '(?:\[(\d+)\])?'             // segment rank
        . '(?:-(\d+)'                  // field
        . '(?:\[(\d+)\])?'             // repetition
        . '(?:\.(\d+)'                 // component
        . '(?:\.(\d+))?'               // sub-component
        . ')?)?$/';

    public function __construct(
        public string $segment,
        public int $segmentRank = 1,
        public ?int $field = null,
        public ?int $repetition = null,
        public ?int $component = null,
        public ?int $subComponent = null
    ) {
        if (preg_match('/^[A-Z][A-Z0-9]{2}$/', $segment) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid segment name "%s".', $segment));
        }

        foreach ([
            'segment rank'  => $segmentRank,
            'field'         => $field,
            'repetition'    => $repetition,
            'component'     => $component,
            'sub-component' => $subComponent,
        ] as $name => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid %s %d: indexes start at 1; a segment is counted among the segments of the same name.',
                    $name,
                    $value
                ));
            }
        }

        if (($repetition !== null || $component !== null) && $field === null) {
            throw new InvalidArgumentException('A repetition or a component needs a field.');
        }
        if ($subComponent !== null && $component === null) {
            throw new InvalidArgumentException('A sub-component needs a component.');
        }
    }

    /**
     * Parse the long or the short form. A missing index is 1.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $location): self
    {
        if (preg_match(self::PATTERN, $location, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid location "%s".', $location));
        }

        $int = static fn (int $i): ?int => isset($m[$i]) && $m[$i] !== '' ? (int) $m[$i] : null;
        $field = $int(3);

        return new self(
            $m[1],
            $int(2) ?? 1,
            $field,
            $field === null ? null : ($int(4) ?? 1),
            $int(5),
            $int(6)
        );
    }

    /**
     * Long form, every index written: PID[1]-3[1].4.1
     */
    public function toLongString(): string
    {
        return $this->format(false);
    }

    /**
     * Short form, indexes equal to 1 omitted: PID-3.4.1
     */
    public function toShortString(): string
    {
        return $this->format(true);
    }

    public function __toString(): string
    {
        return $this->toLongString();
    }

    public function equals(self $other): bool
    {
        return $this->toLongString() === $other->toLongString();
    }

    private function format(bool $short): string
    {
        $index = static fn (int $value): string => $short && $value === 1 ? '' : "[$value]";

        $text = $this->segment . $index($this->segmentRank);
        if ($this->field === null) {
            return $text;
        }
        $text .= '-' . $this->field . $index($this->repetition ?? 1);
        if ($this->component !== null) {
            $text .= '.' . $this->component;
        }
        if ($this->subComponent !== null) {
            $text .= '.' . $this->subComponent;
        }

        return $text;
    }
}
