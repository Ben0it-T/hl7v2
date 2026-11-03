<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\EscapeKind;

/**
 * Escape sequences found in a Raw value, in the order of the value.
 */
final readonly class EscapeScan
{
    /**
     * @param list<array{EscapeKind, string}> $sequences Well-formed sequences:
     *        kind, and the sequence as written (\H\).
     * @param list<string> $malformed Malformed sequences as written: unclosed
     *        (\B), unknown code or invalid data (\XZZ\, \\).
     */
    public function __construct(
        public array $sequences,
        public array $malformed
    ) {
        //
    }
}
