<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Parser;

use HL7v2\HL7Message;
use HL7v2\Model\Message;
use HL7v2\Model\ParseIssue;
use HL7v2\Parser\HL7Parser;
use HL7v2\Parser\InputEncoding;
use PHPUnit\Framework\TestCase;

/**
 * Conversion to UTF-8 according to MSH-18, and InputEncoding.
 */
final class HL7ParserEncodingTest extends TestCase
{
    private const NAME = 'DÉSIRÉ^JOËLLE';

    public function testIso88591IsConvertedToUtf8(): void
    {
        $raw = $this->encode($this->message('8859/1', self::NAME), 'ISO-8859-1');
        $message = (new HL7Parser())->parse($raw);

        $this->assertSame('DÉSIRÉ', $this->value($message, 1));
        $this->assertSame('JOËLLE', $this->value($message, 2));
        $this->assertSame('8859/1', $message->getCharacterSet());
        $this->assertSame('ISO-8859-1', $message->getSourceEncoding());
        $this->assertSame([], $message->getParseIssues());
    }

    public function testIso885915IsConvertedToUtf8(): void
    {
        $raw = $this->encode($this->message('8859/15', 'PRIX 10€^ŒUVRE'), 'ISO-8859-15');
        $message = (new HL7Parser())->parse($raw);

        $this->assertSame('PRIX 10€', $this->value($message, 1));
        $this->assertSame('ŒUVRE', $this->value($message, 2));
        $this->assertSame([], $message->getParseIssues());
    }

    public function testBig5IsConvertedBeforeSplitting(): void
    {
        // In BIG-5, 功 is A5 5C: its second byte is the escape character.
        $raw = $this->encode($this->message('BIG-5', '功^NAME'), 'BIG-5');
        $this->assertStringContainsString("\xA5\x5C", $raw);

        $message = (new HL7Parser())->parse($raw);

        $this->assertSame('功', $this->value($message, 1));
        $this->assertSame('NAME', $this->value($message, 2));
        $this->assertSame([], $message->getParseIssues());
    }

    public function testFirstRepetitionOfMsh18IsUsed(): void
    {
        $raw = $this->encode($this->message('8859/1~ISO IR87', self::NAME), 'ISO-8859-1');
        $message = (new HL7Parser())->parse($raw);

        $this->assertSame('8859/1', $message->getCharacterSet());
        $this->assertSame('DÉSIRÉ', $this->value($message, 1));
    }

    public function testEmptyMsh18MeansUtf8(): void
    {
        $message = (new HL7Parser())->parse($this->message('', self::NAME));

        $this->assertSame('', $message->getCharacterSet());
        $this->assertSame('UTF-8', $message->getSourceEncoding());
        $this->assertSame('DÉSIRÉ', $this->value($message, 1));
        $this->assertSame([], $message->getParseIssues());
    }

    public function testInvalidBytesAreReplacedAndReported(): void
    {
        // MSH-18 empty (UTF-8), but the bytes are ISO-8859-1.
        $raw = $this->encode($this->message('', self::NAME), 'ISO-8859-1');
        $message = (new HL7Parser())->parse($raw);

        $this->assertSame("D\u{FFFD}SIR\u{FFFD}", $this->value($message, 1));
        $this->assertSame(
            [['The message is not valid UTF-8; invalid bytes are replaced by U+FFFD.', null]],
            $this->issues($message)
        );
    }

    public function testAsciiWithAccentedBytesIsReported(): void
    {
        $raw = $this->encode($this->message('ASCII', self::NAME), 'ISO-8859-1');
        $message = (new HL7Parser())->parse($raw);

        $this->assertSame(
            [['The message is not valid ASCII; invalid bytes are replaced by U+FFFD.', null]],
            $this->issues($message)
        );
    }

    public function testUnknownCharacterSetIsReadAsUtf8(): void
    {
        $message = (new HL7Parser())->parse($this->message('FOO-CHARSET', self::NAME));

        $this->assertSame('FOO-CHARSET', $message->getCharacterSet());
        $this->assertSame('UTF-8', $message->getSourceEncoding());
        $this->assertSame('DÉSIRÉ', $this->value($message, 1));
        $this->assertSame(
            [["MSH-18: unknown character set 'FOO-CHARSET'; the message is read as UTF-8.", 'MSH-18']],
            $this->issues($message)
        );
    }

    public function testUnsupportedCharacterSetIsReadAsUtf8(): void
    {
        $message = (new HL7Parser())->parse($this->message('UNICODE UTF-16', self::NAME));

        $this->assertSame(
            [["MSH-18: unsupported character set 'UNICODE UTF-16'; the message is read as UTF-8.", 'MSH-18']],
            $this->issues($message)
        );
    }

    public function testUtf8InputIsNotConvertedAgain(): void
    {
        // A web form sends UTF-8, whatever MSH-18 declares.
        $message = (new HL7Parser())->parse($this->message('8859/1', self::NAME), InputEncoding::Utf8);

        $this->assertSame('DÉSIRÉ', $this->value($message, 1));
        $this->assertSame('8859/1', $message->getCharacterSet());
        $this->assertSame('UTF-8', $message->getSourceEncoding());
        $this->assertSame([], $message->getParseIssues());
    }

    public function testUtf8InputReadAsMsh18IsEncodedTwice(): void
    {
        // Why InputEncoding::Utf8 exists: UTF-8 read as ISO-8859-1 is corrupted.
        $message = (new HL7Parser())->parse($this->message('8859/1', self::NAME));

        // É is C3 89 in UTF-8; read as ISO-8859-1, it becomes Ã (C3) and U+0089.
        $this->assertSame("D\u{00C3}\u{0089}SIR\u{00C3}\u{0089}", $this->value($message, 1));
    }

    public function testInvalidUtf8InputIsReported(): void
    {
        $raw = $this->encode($this->message('8859/1', self::NAME), 'ISO-8859-1');
        $message = (new HL7Parser())->parse($raw, InputEncoding::Utf8);

        $this->assertSame(
            [['The message is not valid UTF-8 (input declared UTF-8); invalid bytes are replaced by U+FFFD.', null]],
            $this->issues($message)
        );
    }

    public function testToHl7RestoresTheReceivedEncoding(): void
    {
        $raw = $this->encode($this->message('8859/1', self::NAME), 'ISO-8859-1');
        $hl7 = new HL7Message();
        $hl7->parse($raw);

        $this->assertSame($raw, $hl7->toHl7());
    }

    public function testToHl7ReturnsUtf8ForUtf8Input(): void
    {
        $raw = $this->message('8859/1', self::NAME);
        $hl7 = new HL7Message();
        $hl7->parse($raw, InputEncoding::Utf8);

        $this->assertSame($raw, $hl7->toHl7());
    }

    /**
     * A message in UTF-8, with MSH-18 and a patient name in PID-5.
     */
    private function message(string $msh18, string $name): string
    {
        return "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5||||||$msh18\r"
            . "PID|1||1^^^AUTH^PI||$name^^^^^L";
    }

    /**
     * @return list<array{string, ?string}> Message and short location of each parse issue.
     */
    private function issues(Message $message): array
    {
        return array_map(
            static fn (ParseIssue $issue): array => [$issue->message, $issue->location?->toShortString()],
            $message->getParseIssues()
        );
    }

    private function encode(string $utf8, string $encoding): string
    {
        return (string) mb_convert_encoding($utf8, $encoding, 'UTF-8');
    }

    /**
     * Decoded value of a component of PID-5.
     */
    private function value(Message $message, int $component): string
    {
        $value = $message->getSegment(1)
            ?->getField(5)
            ?->getRepeat(0)
            ?->getComponent($component)
            ?->getSubComponent(1)
            ?->getDecodedValue();
        $this->assertNotNull($value);

        return $value;
    }
}
