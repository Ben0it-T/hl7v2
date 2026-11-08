<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Value;

use HL7v2\Validation\Value\DisplayableText;
use PHPUnit\Framework\TestCase;

/**
 * Displayable characters of a text value (ST, TX, FT, GTS, ID, IS, TN): no
 * control character (Unicode Cc).
 */
final class DisplayableTextTest extends TestCase
{
    public function testDisplayableText(): void
    {
        $text = new DisplayableText();

        $this->assertSame([], $text->controlCharacters('almost any data at all'));
        $this->assertSame([], $text->controlCharacters('  leading spaces are allowed.'));
        // Characters of the character set of the message.
        $this->assertSame([], $text->controlCharacters('Hélène Ærø 東京 €'));
        $this->assertSame([], $text->controlCharacters("\u{00A0}\u{00FF}"));
        $this->assertSame([], $text->controlCharacters(''));
    }

    public function testControlCharacters(): void
    {
        $text = new DisplayableText();

        $this->assertSame(['U+0009'], $text->controlCharacters("A\tB\tC"));
        $this->assertSame(['U+0000', 'U+001F', 'U+007F'], $text->controlCharacters("\x00A\x1FB\x7F"));
        // C1 controls.
        $this->assertSame(['U+0080', 'U+009F'], $text->controlCharacters("\u{0080}\u{009F}"));
    }

    public function testDataTypes(): void
    {
        $this->assertSame(['ST', 'TX', 'FT', 'GTS', 'ID', 'IS', 'TN'], DisplayableText::DATA_TYPES);
    }
}
