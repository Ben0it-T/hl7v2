<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\Location;

/**
 * A problem met while parsing a message: parsing goes on, and the
 * validation reports the problem as a finding.
 *
 * Example: unknown character set (located on MSH-18)
 *     message:  MSH-18: unknown character set 'FOO-CHARSET'; the message is read as UTF-8.
 *     location: MSH[1]-18          (short form: MSH-18)
 *
 * Example: invalid bytes (no location: the whole message)
 *     message:  The message is not valid UTF-8; invalid bytes are replaced by U+FFFD.
 */
final readonly class ParseIssue
{
    /**
     * @param string $message
     * @param Location|null $location Element concerned; null for the whole message.
     */
    public function __construct(
        public string $message,
        public ?Location $location = null
    ) {
    }
}
