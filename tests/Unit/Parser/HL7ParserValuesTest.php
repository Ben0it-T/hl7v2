<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Parser;

use HL7v2\Model\Message;
use HL7v2\Model\SubComponent;
use HL7v2\Parser\HL7Parser;
use PHPUnit\Framework\TestCase;

/**
 * Raw and Decoded values of the parsed sub-components.
 */
final class HL7ParserValuesTest extends TestCase
{
    public function testRawAndDecodedValues(): void
    {
        $message = (new HL7Parser())->parse(
            "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
            . "PID|1||1^^^AUTH^PI||O\\T\\BRIEN^JANE\\S\\MARIE^^^^^L\r"
        );

        $family = $this->subComponent($message, 1, 5, 1);
        $this->assertSame('O\T\BRIEN', $family->getValue());
        $this->assertSame('O\T\BRIEN', $family->getRawValue());
        $this->assertSame('O&BRIEN', $family->getDecodedValue());

        $given = $this->subComponent($message, 1, 5, 2);
        $this->assertSame('JANE^MARIE', $given->getDecodedValue());
    }

    public function testEncodingCharactersOfTheMessageAreUsed(): void
    {
        $message = (new HL7Parser())->parse(
            "MSH|^~#&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
            . "PID|1||1^^^AUTH^PI||A#F#B^C\\F\\D\r"
        );

        $this->assertSame('A|B', $this->subComponent($message, 1, 5, 1)->getDecodedValue());
        $this->assertSame('C\F\D', $this->subComponent($message, 1, 5, 2)->getDecodedValue());
        $this->assertSame('#', $message->getEscapeSequences()->decode('#E#'));
    }

    public function testMshEncodingFieldsAreNotDecoded(): void
    {
        $message = (new HL7Parser())->parse(
            "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
        );

        $this->assertSame('|', $this->subComponent($message, 0, 1, 1)->getDecodedValue());
        $this->assertSame('^~\\&', $this->subComponent($message, 0, 2, 1)->getDecodedValue());
    }

    /**
     * Sub-component 1 of the first repetition. The model counts segments and
     * repetitions from 0, fields and components from 1.
     */
    private function subComponent(Message $message, int $segmentIndex, int $field, int $component): SubComponent
    {
        $subComponent = $message->getSegment($segmentIndex)
            ?->getField($field)
            ?->getRepeat(0)
            ?->getComponent($component)
            ?->getSubComponent(1);
        $this->assertNotNull($subComponent);

        return $subComponent;
    }
}
