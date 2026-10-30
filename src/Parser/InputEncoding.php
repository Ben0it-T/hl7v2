<?php
declare(strict_types=1);

namespace HL7v2\Parser;

/**
 * Encoding of the message string given to the parser.
 */
enum InputEncoding
{
    /**
     * Bytes as transmitted (file, socket, MLLP): encoded in the character
     * set declared by MSH-18 (UTF-8 when MSH-18 is empty). The message is
     * converted to UTF-8, and toHl7() restores the declared encoding.
     */
    case Msh18;

    /**
     * String already in UTF-8 (web form, JSON, PHP string), whatever MSH-18
     * declares. The message is not converted, and toHl7() returns UTF-8.
     */
    case Utf8;
}
