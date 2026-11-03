<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Value;

use HL7v2\Model\Component;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\SubComponent;
use HL7v2\Parser\HL7Parser;
use HL7v2\Validation\Value\Presence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Presence of a field, a repetition, a component and a sub-component,
 * read on the Raw value of a parsed message.
 */
final class PresenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Presence}>
     */
    public static function fields(): iterable
    {
        // raw value of PID-3, presence of the field
        yield 'empty'                              => ['', Presence::NotPresent];
        yield 'empty components'                   => ['^^', Presence::NotPresent];
        yield 'empty sub-components'               => ['&', Presence::NotPresent];
        yield 'empty repetitions'                  => ['~', Presence::NotPresent];
        yield 'explicit null'                      => ['""', Presence::ExplicitNull];
        yield 'value'                              => ['DOE', Presence::Valued];
        yield 'value in a later component'         => ['^^X', Presence::Valued];
        yield 'value in a later repetition'        => ['~DOE', Presence::Valued];
        yield 'explicit null in each repetition'   => ['""~""', Presence::Valued];
        yield 'explicit null, then empty'          => ['""~', Presence::Valued];
        yield 'explicit null in each component'    => ['""^""', Presence::Valued];
        yield 'explicit null in a component'       => ['^""', Presence::Valued];
        yield 'literal "" in a sub-component'      => ['""&', Presence::Valued];
        yield 'not an explicit null: one quote'    => ['"', Presence::Valued];
        yield 'not an explicit null: with a space' => ['"" ', Presence::Valued];
        yield 'not an explicit null: escaped'      => ['\X2222\\', Presence::Valued];
    }

    #[DataProvider('fields')]
    public function testField(string $raw, Presence $presence): void
    {
        $this->assertSame($presence, Presence::ofField($this->field($raw)));
    }

    public function testRepetition(): void
    {
        $field = $this->field('DOE~""~~^&');

        $this->assertSame(Presence::Valued, Presence::ofRepeat($this->repeat($field, 0)));
        $this->assertSame(Presence::ExplicitNull, Presence::ofRepeat($this->repeat($field, 1)));
        $this->assertSame(Presence::NotPresent, Presence::ofRepeat($this->repeat($field, 2)));
        $this->assertSame(Presence::NotPresent, Presence::ofRepeat($this->repeat($field, 3)));
    }

    public function testComponent(): void
    {
        $repeat = $this->repeat($this->field('DOE^""^^&^A&""^""&""'), 0);

        $this->assertSame(Presence::Valued, Presence::ofComponent($this->component($repeat, 1)));
        $this->assertSame(Presence::ExplicitNull, Presence::ofComponent($this->component($repeat, 2)));
        $this->assertSame(Presence::NotPresent, Presence::ofComponent($this->component($repeat, 3)));
        $this->assertSame(Presence::NotPresent, Presence::ofComponent($this->component($repeat, 4)));
        // "" beside another sub-component is a literal value.
        $this->assertSame(Presence::Valued, Presence::ofComponent($this->component($repeat, 5)));
        $this->assertSame(Presence::Valued, Presence::ofComponent($this->component($repeat, 6)));
    }

    public function testSubComponentIsNeverAnExplicitNull(): void
    {
        $component = $this->component($this->repeat($this->field('A&""&'), 0), 1);

        $this->assertSame(Presence::Valued, Presence::ofSubComponent($this->subComponent($component, 1)));
        $this->assertSame(Presence::Valued, Presence::ofSubComponent($this->subComponent($component, 2)));
        $this->assertSame(Presence::NotPresent, Presence::ofSubComponent($this->subComponent($component, 3)));
    }

    public function testElementsWithoutChildrenAreNotPresent(): void
    {
        $this->assertSame(Presence::NotPresent, Presence::ofField(new Field()));
        $this->assertSame(Presence::NotPresent, Presence::ofRepeat(new FieldRepeat()));
        $this->assertSame(Presence::NotPresent, Presence::ofComponent(new Component()));
        $this->assertSame(Presence::NotPresent, Presence::ofSubComponent(new SubComponent('')));
    }

    /**
     * PID-3 of a message whose PID-3 is the given Raw value.
     */
    private function field(string $raw): Field
    {
        $message = (new HL7Parser())->parse(
            "MSH|^~\\&|APP|FAC|APP|FAC|20261103||ADT^A01|1|P|2.5\rPID|||" . $raw
        );
        $field = $message->getSegments()[1]->getField(3);
        $this->assertNotNull($field);

        return $field;
    }

    /**
     * @param int $index 0-based, as stored by Field.
     */
    private function repeat(Field $field, int $index): FieldRepeat
    {
        $repeat = $field->getRepeat($index);
        $this->assertNotNull($repeat);

        return $repeat;
    }

    private function component(FieldRepeat $repeat, int $position): Component
    {
        $component = $repeat->getComponent($position);
        $this->assertNotNull($component);

        return $component;
    }

    private function subComponent(Component $component, int $position): SubComponent
    {
        $subComponent = $component->getSubComponent($position);
        $this->assertNotNull($subComponent);

        return $subComponent;
    }
}
