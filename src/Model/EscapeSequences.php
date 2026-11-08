<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\EscapeKind;
use HL7v2\Model\EscapeScan;

/**
 * Escape sequences of a message (HL7 v2.5, section 2.7), for the encoding
 * characters of that message (MSH-1 and MSH-2).
 *
 * Decoded value (decode):
 * - \F\ \S\ \T\ \R\ \E\ give the separator or escape character of the message;
 * - \Xdddd...\ gives the bytes of the hexadecimal pairs;
 * - \H\ \N\ (highlighting), \.xx\ (formatting, FT), \Cxxyy\ \Mxxyyzz\
 *   (character sets) are removed: they carry no data character;
 * - \Zdddd...\ (locally defined) is kept as is: its meaning is unknown;
 * - a malformed sequence (unclosed, unknown code, invalid data) is kept as is.
 *
 * HL7 length (length), counted on a Raw value of any level:
 * - every character counts 1, separators included (section 2.5.3.2);
 * - \F\ \S\ \T\ \R\ \E\ count 1;
 * - \Xdddd...\ counts its number of bytes;
 * - \H\ \N\ \.xx\ \C...\ \M...\ count 0;
 * - \Zdddd...\ counts its data characters (the d), without the escape characters and the code Z;
 * - a malformed sequence counts its characters.
 * The syntax of a sequence (escape characters and code) never counts: only what the sequence carries does.
 *
 * Written text (text): the characters written in a Raw value outside its
 * well-formed escape sequences; a malformed sequence is kept as written.
 */
final readonly class EscapeSequences
{
    private const FORMATTING = '/^\.(sp|br|fi|nf|in|ti|sk|ce) ?([+-]?\d+)?$/';

    public function __construct(
        private string $fieldSeparator = '|',
        private string $componentSeparator = '^',
        private string $repetitionSeparator = '~',
        private string $escapeChar = '\\',
        private string $subComponentSeparator = '&'
    ) {
    }

    /**
     * Decoded value of a Raw value.
     */
    public function decode(string $raw): string
    {
        $decoded = '';
        foreach ($this->tokens($raw) as [$kind, $content]) {
            $decoded .= match ($kind) {
                'text', 'malformed' => $content,
                'F'     => $this->fieldSeparator,
                'S'     => $this->componentSeparator,
                'T'     => $this->subComponentSeparator,
                'R'     => $this->repetitionSeparator,
                'E'     => $this->escapeChar,
                'X'     => (string) hex2bin(substr($content, 1)),
                'Z'     => $this->escapeChar . $content . $this->escapeChar,
                default => '', // H, N, formatting, C, M
            };
        }

        return $decoded;
    }

    /**
     * Written text of a Raw value: its characters outside the well-formed
     * escape sequences, a malformed sequence kept as written.
     */
    public function text(string $raw): string
    {
        $text = '';
        foreach ($this->tokens($raw) as [$kind, $content]) {
            if ($kind === 'text' || $kind === 'malformed') {
                $text .= $content;
            }
        }

        return $text;
    }

    /**
     * HL7 length of a Raw value, in characters.
     *
     * The syntax of a sequence (escape characters and code) never counts:
     * only the data it carries does.
     */
    public function length(string $raw): int
    {
        $length = 0;
        foreach ($this->tokens($raw) as [$kind, $content]) {
            $length += match ($kind) {
                'text', 'malformed'     => mb_strlen($content, 'UTF-8'),
                'F', 'S', 'T', 'R', 'E' => 1,
                'X'                     => intdiv(strlen($content) - 1, 2),
                // \Zdddd...\ is locally defined (HL7 v2.5, section 2.7.7):
                // its meaning is agreed between the applications, so the number of
                // characters it stands for cannot be known. Its data characters
                // (the d) are counted as written, without the escape characters
                // and the code Z, as the closest approximation.
                'Z'                     => mb_strlen(substr($content, 1), 'UTF-8'),
                default                 => 0, // H, N, formatting, C, M: no data character
            };
        }

        return $length;
    }

    /**
     * Escape sequences of a Raw value: the well-formed ones with their kind,
     * and the malformed ones, each as written, in the order of the value.
     * Without an escape character (MSH-2), a value has no sequence.
     */
    public function scan(string $raw): EscapeScan
    {
        $sequences = [];
        $malformed = [];
        foreach ($this->tokens($raw) as [$kind, $content]) {
            if ($kind === 'text') {
                continue;
            }
            if ($kind === 'malformed') {
                $malformed[] = $content;
                continue;
            }
            $sequences[] = [
                self::escapeKindOf($kind),
                $this->escapeChar . $content . $this->escapeChar,
            ];
        }

        return new EscapeScan($sequences, $malformed);
    }

    /**
     * Split a Raw value into text, well-formed escape sequences and
     * malformed escape sequences.
     *
     * @return list<array{string, string}> Pairs [kind, content]: kind is 'text',
     *         'malformed' or the code of the sequence; content is the text, the
     *         malformed sequence as written, or the well-formed sequence
     *         without its escape characters.
     */
    private function tokens(string $raw): array
    {
        $escape = $this->escapeChar;
        if ($escape === '' || !str_contains($raw, $escape)) {
            return [['text', $raw]];
        }

        $tokens = [];
        $position = 0;
        $end = strlen($raw);
        while ($position < $end) {
            $start = strpos($raw, $escape, $position);
            if ($start === false) {
                $tokens[] = ['text', substr($raw, $position)];
                break;
            }
            if ($start > $position) {
                $tokens[] = ['text', substr($raw, $position, $start - $position)];
            }

            $close = strpos($raw, $escape, $start + 1);
            if ($close === false) {
                // Unclosed sequence: the rest is malformed.
                $tokens[] = ['malformed', substr($raw, $start)];
                break;
            }

            $content = substr($raw, $start + 1, $close - $start - 1);
            $kind = $this->kindOf($content);
            $tokens[] = $kind === null
                ? ['malformed', substr($raw, $start, $close - $start + 1)]
                : [$kind, $content];
            $position = $close + 1;
        }

        return $tokens;
    }

    /**
     * Code of a well-formed sequence, or null.
     */
    private function kindOf(string $content): ?string
    {
        return match (true) {
            in_array($content, ['F', 'S', 'T', 'R', 'E', 'H', 'N'], true)   => $content,
            preg_match('/^X(?:[0-9A-Fa-f]{2})+$/', $content) === 1          => 'X',
            preg_match(self::FORMATTING, $content) === 1                    => '.',
            preg_match('/^C[0-9A-Fa-f]{4}$/', $content) === 1               => 'C',
            preg_match('/^M[0-9A-Fa-f]{4}(?:[0-9A-Fa-f]{2})?$/', $content) === 1 => 'M',
            preg_match('/^Z.+$/s', $content) === 1                          => 'Z',
            default                                                         => null,
        };
    }

    /**
     * Kind of a well-formed sequence, from its code.
     */
    private static function escapeKindOf(string $code): EscapeKind
    {
        return match ($code) {
            'F', 'S', 'T', 'R', 'E' => EscapeKind::Separator,
            'H', 'N'                => EscapeKind::Highlighting,
            'X'                     => EscapeKind::Hexadecimal,
            'Z'                     => EscapeKind::Local,
            'C', 'M'                => EscapeKind::CharacterSet,
            default                 => EscapeKind::Formatting, // '.'
        };
    }
}
