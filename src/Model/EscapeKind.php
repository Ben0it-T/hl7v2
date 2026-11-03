<?php
declare(strict_types=1);

namespace HL7v2\Model;

/**
 * Kind of a well-formed escape sequence (HL7 v2.5, section 2.7).
 */
enum EscapeKind
{
    /** \F\ \S\ \T\ \R\ \E\: separator or escape character as data (2.7.4). */
    case Separator;

    /** \H\ \N\: start and end of highlighting (2.7.3). */
    case Highlighting;

    /** \Xdddd...\: hexadecimal data (2.7.5). */
    case Hexadecimal;

    /** \Zdddd...\: locally defined (2.7.7). */
    case Local;

    /** \Cxxyy\ \Mxxyyzz\: single-byte or multi-byte character set (2.7.2). */
    case CharacterSet;

    /** \.sp\ \.br\ ...: formatting command of formatted text (2.7.6). */
    case Formatting;
}
