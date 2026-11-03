<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

use HL7v2\Model\EscapeKind;
use HL7v2\Model\EscapeScan;

/**
 * Escape sequences allowed in a primitive data type.
 *
 * Sources (HL7 v2.5):
 * - figure 2-1 (section 2.5.4): the escape character is for fields of type
 *   ST, TX or FT, and for the data of an ED;
 * - section 2.7.1: \F\ \S\ \T\ \R\ \E\ \H\ \N\ \X...\ \Z...\ in TX, FT
 *   and CF; \F\ \S\ \T\ \R\ \E\ also in ST;
 * - section 2.7.2: \C...\ \M...\ in FT, ST and TX;
 * - section 2.7.6: formatting commands in FT only.
 * Any other primitive type allows no escape sequence. CF is a composite:
 * its components are checked by their own types.
 *
 * HL7 2.4 (section 2.10) and 2.5.1 to 2.7 (section 2.7) give the same rules,
 * and they apply to every HL7 version, 2.3 and 2.3.1 included.
 *
 * HL7 2.3 and 2.3.1 (section 2.9) give the same rules too, except for
 * \C...\ and \M...\, which they tie to the type of the field, not to the type
 * of the value: fields of type PN and XPN in 2.3; PN, XPN, XCN, XON and XAD
 * in 2.3.1 (title of section 2.9.2; its text names only PN and XPN).
 * This particular rule is not taken into account: in 2.3 and 2.3.1 as in later
 * versions, \C...\ and \M...\ are allowed in FT, ST and TX, and in no other
 * primitive type, whatever the type of the field.
 */
final readonly class EscapePolicy
{
    /**
     * Kinds of escape sequences allowed, by primitive data type.
     * A primitive data type not listed allows none.
     *
     * @var array<string, list<EscapeKind>>
     */
    private const ALLOWED = [
        'FT' => [
            EscapeKind::Separator,
            EscapeKind::Highlighting,
            EscapeKind::Hexadecimal,
            EscapeKind::Local,
            EscapeKind::CharacterSet,
            EscapeKind::Formatting,
        ],
        'TX' => [
            EscapeKind::Separator,
            EscapeKind::Highlighting,
            EscapeKind::Hexadecimal,
            EscapeKind::Local,
            EscapeKind::CharacterSet,
        ],
        'ST' => [
            EscapeKind::Separator,
            EscapeKind::CharacterSet,
        ],
    ];

    /**
     * Whether a primitive data type allows a kind of escape sequence.
     *
     * @param string $dataType Primitive data type name (ST).
     */
    public function allows(string $dataType, EscapeKind $kind): bool
    {
        return in_array($kind, self::ALLOWED[$dataType] ?? [], true);
    }

    /**
     * Well-formed sequences of the scan that the primitive data type does not
     * allow, as written, in the order of the value.
     * Malformed sequences are read from the scan itself.
     *
     * @param EscapeScan $scan Scan of the Raw value of a primitive value.
     * @param string $dataType Primitive data type name (ST).
     * @return list<string> Sequences as written (\H\); empty when all are allowed.
     */
    public function notAllowed(EscapeScan $scan, string $dataType): array
    {
        $notAllowed = [];
        foreach ($scan->sequences as [$kind, $sequence]) {
            if (!$this->allows($dataType, $kind)) {
                $notAllowed[] = $sequence;
            }
        }

        return $notAllowed;
    }
}
