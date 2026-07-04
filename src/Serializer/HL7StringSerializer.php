<?php
declare(strict_types=1);

namespace HL7v2\Serializer;

use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\Component;

class HL7StringSerializer
{
    public function serialize(Message $message, bool $pretty = false): string
    {
        $segments = [];

        foreach ($message->getSegments() as $segment) {
            $segments[] = $this->segmentToString($segment, $message);
        }

        return implode(
            $pretty ? "\n" : $message->getSegmentSeparator(),
            $segments
        );
    }

    private function segmentToString(
        Segment $segment,
        Message $message
    ): string {

        $parts = [$segment->getName()];
        $fields = $segment->getFields();

        if ($segment->getName() === 'MSH') {
            array_shift($fields); // ignore MSH-1
        }

        foreach ($fields as $field) {
            $parts[] = $this->fieldToString($field, $message);
        }

        return implode(
            $message->getFieldSeparator(),
            $parts
        );
    }

    private function fieldToString(
        Field $field,
        Message $message
    ): string {

        $repeats = [];

        foreach ($field->getRepeats() as $repeat) {
            $repeats[] = $this->repeatToString($repeat, $message);
        }

        return implode(
            $message->getFieldRepeatSeparator(),
            $repeats
        );
    }

    private function repeatToString(
        FieldRepeat $repeat,
        Message $message
    ): string {

        $components = [];

        foreach ($repeat->getComponents() as $component) {
            $components[] = $this->componentToString(
                $component,
                $message
            );
        }

        return implode(
            $message->getComponentSeparator(),
            $components
        );
    }

    private function componentToString(
        Component $component,
        Message $message
    ): string {

        $values = [];

        foreach ($component->getSubComponents() as $subComponent) {
            $values[] = $subComponent->getValue();
        }

        return implode(
            $message->getSubComponentSeparator(),
            $values
        );
    }
}
