<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

/**
 * Characters of a text value of type ST, TX, FT or GTS: "Any displayable
 * (printable) characters" (HL7 v2.5 section 2.A.74; HL7 v2.3 to v2.4 give
 * the same definition). TX is meant for display (2.A.78), FT is derived from
 * ST (2.A.31), GTS follows the formatting rules of ST (2.A.32).
 *
 * Judged on the written text (the characters outside the escape sequences):
 * a control character a TX or an FT needs is sent as \Xhh\, and its Decoded
 * value holds it. A character of the character set of the message (MSH-18)
 * is displayable: the character set is checked by the parser.
 *
 * Control characters: Unicode category Cc, U+0000 to U+001F and U+007F to
 * U+009F. CR and LF cannot be in a value: the parser splits the segments on
 * them.
 */
final readonly class DisplayableText
{
    /**
     * Data types whose value is judged by its characters.
     */
    public const DATA_TYPES = ['ST', 'TX', 'FT', 'GTS'];

    /**
     * Control characters of a written text, each once, in the order of the text.
     *
     * @param string $text Written text, in UTF-8.
     *
     * @return list<string> Code points, as U+0009.
     */
    public function controlCharacters(string $text): array
    {
        if (preg_match_all('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $text, $matches) === false) {
            return [];
        }

        $found = [];
        foreach ($matches[0] as $character) {
            $found[] = sprintf('U+%04X', mb_ord($character, 'UTF-8'));
        }

        return array_values(array_unique($found));
    }
}
