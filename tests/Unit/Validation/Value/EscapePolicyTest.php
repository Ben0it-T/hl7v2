<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Value;

use HL7v2\Model\EscapeKind;
use HL7v2\Model\EscapeSequences;
use HL7v2\Validation\Value\EscapePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Escape sequences allowed by primitive data type: FT, TX and ST only,
 * the same for every HL7 version.
 */
final class EscapePolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<EscapeKind>}>
     */
    public static function allowedKinds(): iterable
    {
        yield 'FT' => ['FT', [
            EscapeKind::Separator,
            EscapeKind::Highlighting,
            EscapeKind::Hexadecimal,
            EscapeKind::Local,
            EscapeKind::CharacterSet,
            EscapeKind::Formatting,
        ]];
        yield 'TX' => ['TX', [
            EscapeKind::Separator,
            EscapeKind::Highlighting,
            EscapeKind::Hexadecimal,
            EscapeKind::Local,
            EscapeKind::CharacterSet,
        ]];
        yield 'ST' => ['ST', [EscapeKind::Separator, EscapeKind::CharacterSet]];
        foreach (['ID', 'IS', 'NM', 'SI', 'DT', 'DTM', 'TM', 'TN', 'GTS', 'FN', 'XPN', 'CF', ''] as $dataType) {
            yield $dataType === '' ? 'no type' : $dataType => [$dataType, []];
        }
    }

    /**
     * @param list<EscapeKind> $allowed
     */
    #[DataProvider('allowedKinds')]
    public function testAllows(string $dataType, array $allowed): void
    {
        $policy = new EscapePolicy();

        foreach (EscapeKind::cases() as $kind) {
            $this->assertSame(
                in_array($kind, $allowed, true),
                $policy->allows($dataType, $kind),
                sprintf('%s, %s', $dataType, $kind->name)
            );
        }
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function values(): iterable
    {
        // primitive data type, raw value, sequences not allowed (as written)
        yield 'FT, every kind'            => ['FT', '\H\A\N\\\F\\\X41\\\Zx\\\C2D41\\\.br\\', []];
        yield 'TX, formatting'            => ['TX', 'A\.br\B\F\C', ['\.br\\']];
        yield 'ST, separators'            => ['ST', 'O\T\BRIEN', []];
        yield 'ST, character set'         => ['ST', '\C2D41\ABC', []];
        yield 'ST, in order of the value' => ['ST', '\H\A\F\B\N\\\X41\\\Zx\\', ['\H\\', '\N\\', '\X41\\', '\Zx\\']];
        yield 'ID, escaped code'          => ['ID', 'A\F\B', ['\F\\']];
        yield 'NM, hexadecimal'           => ['NM', '\X31\\', ['\X31\\']];
        yield 'NM, no sequence'           => ['NM', '12.5', []];
        yield 'IS, empty'                 => ['IS', '', []];
    }

    /**
     * @param list<string> $notAllowed
     */
    #[DataProvider('values')]
    public function testNotAllowed(string $dataType, string $raw, array $notAllowed): void
    {
        $scan = (new EscapeSequences())->scan($raw);

        $this->assertSame($notAllowed, (new EscapePolicy())->notAllowed($scan, $dataType));
    }

    public function testMalformedSequencesAreLeftToTheScan(): void
    {
        $scan = (new EscapeSequences())->scan('A\Q\B\F\C');

        $this->assertSame(['\F\\'], (new EscapePolicy())->notAllowed($scan, 'NM'));
        $this->assertSame([], (new EscapePolicy())->notAllowed($scan, 'ST'));
        $this->assertSame(['\Q\\'], $scan->malformed);
    }

    public function testSequencesAreWrittenWithTheEscapeCharacterOfTheMessage(): void
    {
        $scan = (new EscapeSequences('|', '^', '~', '#', '&'))->scan('A#H#B#N#');

        $this->assertSame(['#H#', '#N#'], (new EscapePolicy())->notAllowed($scan, 'ST'));
    }
}
