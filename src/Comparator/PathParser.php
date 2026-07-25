<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Comparator\Path;

use InvalidArgumentException;

final class PathParser
{
    /**
     * Parse a flattened HL7 path.
     *
     * Example:
     * PID[2]-3[0].4.1
     *
     * @param string $path
     * @return Path
     */
    public function parse(string $path): Path
    {
        // Path format: SEGMENT[segmentIndex]-FIELD[repeatIndex].COMPONENT.SUBCOMPONENT
        // Ex: PID[2]-3[0].4.1
        $pattern =
            '/^' .
            '([A-Z0-9]{3})' .   // Segment name
            '\[(\d+)\]' .       // Segment index
            '\-' .
            '(\d+)' .           // Field offset
            '\[(\d+)\]' .       // Repeat index
            '\.' .
            '(\d+)' .           // Component offset
            '\.' .
            '(\d+)' .           // Sub-component offset
            '$/';

        if (!preg_match($pattern, $path, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid path "%s".', $path)
            );
        }

        return new Path(
            segmentName:       $matches[1],         // PID
            segmentIndex:      (int) $matches[2],   // 2
            fieldOffset:       (int) $matches[3],   // 3
            repeatIndex:       (int) $matches[4],   // 0
            componentOffset:   (int) $matches[5],   // 4
            subComponentOffset:(int) $matches[6]    // 1
        );
    }
}
