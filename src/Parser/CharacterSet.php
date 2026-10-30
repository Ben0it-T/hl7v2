<?php
declare(strict_types=1);

namespace HL7v2\Parser;

/**
 * Character set declared by MSH-18 (HL7 table 0211), and the matching
 * PHP encoding.
 *
 * - supported: the message can be converted to UTF-8;
 * - known but not supported: UTF-16 and UTF-32 (separators on 2 or 4
 *   bytes), and the ISO 2022 Japanese, Korean and Chinese character sets;
 * - unknown: any other value.
 */
final readonly class CharacterSet
{
    /** MSH-18 value => PHP encoding. */
    private const ENCODINGS = [
        ''              => 'UTF-8', // MSH-18 empty: UTF-8 by default
        'ASCII'         => 'ASCII',
        '8859/1'        => 'ISO-8859-1',
        '8859/2'        => 'ISO-8859-2',
        '8859/3'        => 'ISO-8859-3',
        '8859/4'        => 'ISO-8859-4',
        '8859/5'        => 'ISO-8859-5',
        '8859/6'        => 'ISO-8859-6',
        '8859/7'        => 'ISO-8859-7',
        '8859/8'        => 'ISO-8859-8',
        '8859/9'        => 'ISO-8859-9',
        '8859/15'       => 'ISO-8859-15',
        'UNICODE UTF-8' => 'UTF-8',
        'UNICODE'       => 'UTF-8', // ISO/IEC 10646, encoding not specified by HL7
        'BIG-5'         => 'BIG-5',
        'GB 18030-2000' => 'GB18030',
    ];

    private const UNSUPPORTED = [
        'UNICODE UTF-16',
        'UNICODE UTF-32',
        'ISO IR14',
        'ISO IR87',
        'ISO IR159',
        'JIS X 0202',
        'JAS2020',
        'KS X 1001',
        'CNS 11643-1992',
    ];

    private function __construct(
        public string $value,
        public ?string $encoding,
        public bool $known
    ) {
    }

    public static function fromMsh18(string $value): self
    {
        if (array_key_exists($value, self::ENCODINGS)) {
            return new self($value, self::ENCODINGS[$value], true);
        }

        return new self($value, null, in_array($value, self::UNSUPPORTED, true));
    }

    public function isSupported(): bool
    {
        return $this->encoding !== null;
    }
}
