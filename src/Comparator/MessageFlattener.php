<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

use HL7v2\Model\Location;
use HL7v2\Model\Message;

/**
 * Flatten a message into its elements and its values.
 *
 * Every element present in the message is listed, empty or not, in message
 * order: segment, field, repetition, component, sub-component. A segment is
 * ranked among the segments of the same name, and every index is counted
 * from 1. A field and its first repetition have different keys.
 *
 * Element keys:
 *     PID[1]             segment
 *     PID[1]-3           field
 *     PID[1]-3[2]        repetition
 *     PID[1]-3[2].4      component
 *     PID[1]-3[2].4.1    sub-component
 *
 * Example, for the segments
 *     MSH|^~\&|APP
 *     PID|1||100001^^^HOSP||DOE^JANE
 *
 * elements (key: parent, empty):
 *     MSH[1]             null           no
 *     ...
 *     PID[1]             null           no
 *     PID[1]-1           PID[1]         no
 *     PID[1]-1[1]        PID[1]-1       no
 *     PID[1]-1[1].1      PID[1]-1[1]    no
 *     PID[1]-1[1].1.1    PID[1]-1[1].1  no
 *     PID[1]-2           PID[1]         yes   (empty field)
 *     PID[1]-2[1]        PID[1]-2       yes
 *     ...
 *     PID[1]-3[1].2.1    PID[1]-3[1].2  yes   (empty component)
 *     ...
 *
 * values (Raw values of the non-empty sub-components):
 *     PID[1]-1[1].1.1 => '1'
 *     PID[1]-3[1].1.1 => '100001'
 *     PID[1]-3[1].4.1 => 'HOSP'
 *     PID[1]-5[1].1.1 => 'DOE'
 *     PID[1]-5[1].2.1 => 'JANE'
 */
final class MessageFlattener
{
    /**
     * Flatten a message into its elements and its values.
     *
     * @param Message $message
     *
     * @return array{
     *     elements: array<string, array{location: Location, parent: ?string, empty: bool}>,
     *     values: array<string, string>
     * } Elements by key; Raw values of the non-empty sub-components by key.
     */
    public function flatten(Message $message): array
    {
        /** @var array<string, Location> $locations */
        $locations = [];
        /** @var array<string, ?string> $parents */
        $parents = [];
        $nonEmpty = [];
        $values = [];
        $ranks = [];

        foreach ($message->getSegments() as $segment) {
            $name = $segment->getName();
            $rank = $ranks[$name] = ($ranks[$name] ?? 0) + 1;

            $segmentKey = "{$name}[{$rank}]";
            $locations[$segmentKey] = new Location($name, $rank);
            $parents[$segmentKey] = null;

            foreach (array_values($segment->getFields()) as $f => $field) {
                $fieldNumber = $f + 1;
                $fieldKey = "{$segmentKey}-{$fieldNumber}";
                $locations[$fieldKey] = new Location($name, $rank, $fieldNumber);
                $parents[$fieldKey] = $segmentKey;

                foreach (array_values($field->getRepeats()) as $r => $repeat) {
                    $repetition = $r + 1;
                    $repeatKey = "{$fieldKey}[{$repetition}]";
                    $locations[$repeatKey] = new Location($name, $rank, $fieldNumber, $repetition);
                    $parents[$repeatKey] = $fieldKey;

                    foreach (array_values($repeat->getComponents()) as $c => $component) {
                        $componentNumber = $c + 1;
                        $componentKey = "{$repeatKey}.{$componentNumber}";
                        $locations[$componentKey] = new Location($name, $rank, $fieldNumber, $repetition, $componentNumber);
                        $parents[$componentKey] = $repeatKey;

                        foreach (array_values($component->getSubComponents()) as $s => $subComponent) {
                            $subComponentNumber = $s + 1;
                            $subComponentKey = "{$componentKey}.{$subComponentNumber}";
                            $locations[$subComponentKey] = new Location(
                                $name,
                                $rank,
                                $fieldNumber,
                                $repetition,
                                $componentNumber,
                                $subComponentNumber
                            );
                            $parents[$subComponentKey] = $componentKey;

                            $value = $subComponent->getRawValue();
                            if ($value !== '') {
                                $values[$subComponentKey] = $value;
                                // A value makes the sub-component and all its ancestors non-empty.
                                for ($key = $subComponentKey; $key !== null; $key = $parents[$key]) {
                                    $nonEmpty[$key] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        $elements = [];
        foreach ($locations as $key => $location) {
            $elements[$key] = [
                'location' => $location,
                'parent'   => $parents[$key],
                'empty'    => !isset($nonEmpty[$key]),
            ];
        }

        return ['elements' => $elements, 'values' => $values];
    }
}
