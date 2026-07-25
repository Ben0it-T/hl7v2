<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Model\Message;

final class MessageFlattener
{
    /**
     * Flatten a message into a map of paths => values.
     *
     * Path format:
     * SEGMENT[segmentIndex]-FIELD[repeatIndex].COMPONENT.SUBCOMPONENT
     *
     * segmentIndex : 0-based
     * repeatIndex  : 0-based
     * field        : HL7 1-based offset
     * component    : HL7 1-based offset
     * subComponent : HL7 1-based offset
     *
     * Example:
     * [
     *     'PID[2]-3[0].1.1' => '123456789',
     *     'PID[2]-3[0].2.1' => '',
     *     'PID[2]-3[0].3.1' => '',
     *     'PID[2]-3[0].4.1' => 'AssigningAuthority',
     *     'PID[2]-3[0].5.1' => 'PI',
     * ]
     *
     * @param Message $message
     * @return array<string,string>
     */
    public function flatten(Message $message): array
    {
        $paths = [];

        foreach ($message->getSegments() as $segmentIndex => $segment) {
            $segmentName = $segment->getName();

            foreach ($segment->getFields() as $fieldIndex => $field) {
                // HL7 fields are 1-based
                $fieldOffset = $fieldIndex + 1;

                foreach ($field->getRepeats() as $repeatIndex => $repeat) {

                    foreach ($repeat->getComponents() as $componentIndex => $component) {
                        // HL7 components are 1-based
                        $componentOffset = $componentIndex + 1;

                        foreach ($component->getSubComponents() as $subComponentIndex => $subComponent) {
                            // HL7 sub-components are 1-based
                            $subComponentOffset = $subComponentIndex + 1;

                            $path = sprintf(
                                '%s[%d]-%d[%d].%d.%d',
                                $segmentName,
                                $segmentIndex,      // 0-based
                                $fieldOffset,       // 1-based
                                $repeatIndex,       // 0-based
                                $componentOffset,   // 1-based
                                $subComponentOffset // 1-based
                            );

                            $paths[$path] = $subComponent->getValue();
                        }
                    }
                }
            }
        }

        return $paths;
    }
}
