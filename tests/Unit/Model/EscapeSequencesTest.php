<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Model;

use HL7v2\Model\EscapeSequences;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EscapeSequencesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function sequences(): iterable
    {
        // raw, decoded value, HL7 length
        yield 'no sequence'               => ['DOE', 'DOE', 3];
        yield 'empty'                     => ['', '', 0];
        yield 'field separator'           => ['A\F\B', 'A|B', 3];
        yield 'component separator'       => ['A\S\B', 'A^B', 3];
        yield 'sub-component separator'   => ['O\T\BRIEN', 'O&BRIEN', 7];
        yield 'repetition separator'      => ['A\R\B', 'A~B', 3];
        yield 'escape character'          => ['C:\E\TEMP', 'C:\TEMP', 7];
        yield 'several sequences'         => ['\F\90 - 200\F\\', '|90 - 200|', 10];
        yield 'hexadecimal'               => ['\X414243\\', 'ABC', 3];
        yield 'hexadecimal, lower case'   => ['\X6162\\', 'ab', 2];
        yield 'highlighting removed'      => ['\H\240*\N\\', '240*', 4];
        yield 'line break removed'        => ['LINE 1\.br\LINE 2', 'LINE 1LINE 2', 12];
        yield 'formatting with number'    => ['\.in+4\\\\.ti-4\TEXT', 'TEXT', 4];
        yield 'skip with space'           => ['A\.sp 2\B', 'AB', 2];
        yield 'single-byte character set' => ['\C2D41\ABC', 'ABC', 3];
        yield 'multi-byte, 2 pairs'       => ['\M2442\ABC', 'ABC', 3];
        yield 'multi-byte, 3 pairs'       => ['\M242844\ABC', 'ABC', 3];
        yield 'local, kept and counted'   => ['A\Zbold\B', 'A\Zbold\B', 6];
        yield 'multibyte characters'      => ['DÉSIRÉ\T\JOËLLE', 'DÉSIRÉ&JOËLLE', 13];
        yield 'separators in raw value'   => ['A^B&C', 'A^B&C', 5];
    }

    #[DataProvider('sequences')]
    public function testDecodeAndLength(string $raw, string $decoded, int $length): void
    {
        $escapes = new EscapeSequences();

        $this->assertSame($decoded, $escapes->decode($raw));
        $this->assertSame($length, $escapes->length($raw));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function malformed(): iterable
    {
        // raw (kept as is in the decoded value), HL7 length (its characters)
        yield 'unclosed'                    => ['DOE\F', 5];
        yield 'unknown code'                => ['A\Q\B', 5];
        yield 'data after a separator code' => ['A\Fx\B', 6];
        yield 'odd hexadecimal'             => ['\X414\\', 6];
        yield 'invalid hexadecimal'         => ['\XZZ\\', 5];
        yield 'empty sequence'              => ['A\\\\B', 4];
        yield 'local without data'          => ['\Z\\', 3];
        yield 'unknown formatting command'  => ['\.xx\\', 5];
        yield 'single-byte, 3 digits'       => ['\C2D4\\', 6];
        yield 'lone escape character'       => ['A\\', 2];
    }

    #[DataProvider('malformed')]
    public function testMalformedSequenceIsKeptAndCountedAsText(string $raw, int $length): void
    {
        $escapes = new EscapeSequences();

        $this->assertSame($raw, $escapes->decode($raw));
        $this->assertSame($length, $escapes->length($raw));
    }

    public function testMalformedSequenceDoesNotHideTheNextOne(): void
    {
        $escapes = new EscapeSequences();

        // "\Q\" is malformed; the sequence that follows is still decoded.
        $this->assertSame('\Q\A|B', $escapes->decode('\Q\A\F\B'));
    }

    public function testEncodingCharactersOfTheMessage(): void
    {
        // MSH|^~#&: '#' is the escape character.
        $escapes = new EscapeSequences('|', '^', '~', '#', '&');

        $this->assertSame('A|B', $escapes->decode('A#F#B'));
        $this->assertSame('C:#TEMP', $escapes->decode('C:#E#TEMP'));
        $this->assertSame('A\F\B', $escapes->decode('A\F\B'));
        $this->assertSame(3, $escapes->length('A#F#B'));
    }

    public function testSeparatorsOfTheMessage(): void
    {
        // MSH*@!?$: field *, component @, repetition !, escape ?, sub-component $.
        $escapes = new EscapeSequences('*', '@', '!', '?', '$');

        $this->assertSame('*@$!?', $escapes->decode('?F??S??T??R??E?'));
    }

    public function testNoEscapeCharacter(): void
    {
        $escapes = new EscapeSequences('|', '^', '~', '', '&');

        $this->assertSame('A\F\B', $escapes->decode('A\F\B'));
        $this->assertSame(5, $escapes->length('A\F\B'));
    }
}
