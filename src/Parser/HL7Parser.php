<?php
declare(strict_types=1);

namespace HL7v2\Parser;

use HL7v2\Exception\HL7Exception;
use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\Component;
use HL7v2\Model\SubComponent;

class HL7Parser
{
    private string $segmentSeparator = "\r"; // 0x0D (CR) chr(13)

    /**
     * Parse HL7 message string
     * Splits message to segments and creates a very simple representation of the message.
     *
     * Message
     * └── Segment
     *      └── Field
     *           └── FieldRepeat
     *                └── Component
     *                     └── SubComponent
     *
     * @param string $raw the HL7 message string
     * @return Message
     * @throws HL7Exception
     */
    public function parse(string $raw): Message
    {
        // Remove any leading CR/LF or whitespace that may precede the
        // first MSH segment. Such characters are transport artefacts and
        // are not part of the HL7 message.
        $raw = ltrim($raw);

        if (empty($raw)) {
            throw new HL7Exception('Message is empty.');
        }


        // Check if message is a valid message
        //
        // Before any segment, field, repetition or component can be parsed,
        // the delimiter characters must be extracted from the MSH segment.
        //
        // Extract control string (first nine chars):
        //   - first three chars: first segment name (MSH)
        //   - char 4: field separator (MSH-1)
        //   - char 5 to 8: encoding characters (MSH-2)
        //   - char 9: must be a field separator
        //
        // If this control string is invalid, the parser cannot determine
        // how the remainder of the message is encoded and parsing must stop.

        $mshControlString = substr($raw, 0, 9);
        if (!preg_match('/^([A-Z0-9]{3})(.)(.)(.)(.)(.)(.)/', $mshControlString, $matches)) {
            // --------->    MSH         |  ^  ~  \  &  |
            //
            // Captured groups:
            // [1] Segment name           => MSH
            // [2] Field separator        => |
            // [3] Component separator    => ^
            // [4] Repetition separator   => ~
            // [5] Escape character       => \
            // [6] Subcomponent separator => &
            // [7] Field separator        => | (must match [2])
            throw new HL7Exception('This is not a valid message. Please check MSH segment.');
        }
        // first segment name must be "MSH"
        if ($matches[1] !== "MSH") {
            throw new HL7Exception('This is not a valid message. MSH segment not found.');
        }
        // field separator must be the same as the first field separator
        if ($matches[2] !== $matches[7]) {
            throw new HL7Exception('This is not a valid message. Invalid field separator in control string.');
        }

        // Get field separator (MSH-1) and encoding characters (MSH-2)
        $fieldSeparator        = $matches[2];   // '|'
        $componentSeparator    = $matches[3];   // '^'
        $fieldRepeatSeparator  = $matches[4];   // '~'
        $escapeChar            = $matches[5];   // '\'
        $subComponentSeparator = $matches[6];   // '&'

        $message = new Message();

        $message->setSeparators($fieldSeparator, $componentSeparator, $fieldRepeatSeparator, $escapeChar, $subComponentSeparator);

        // Split message to segments (CR / LF)
        $segments = preg_split("/[\n\r" . $this->segmentSeparator . ']/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        if ($segments === false) { // array<int,string>|false
            throw new HL7Exception('Unable to split message into segments.');
        }

        // Get message metadata
        $MSH = explode($fieldSeparator, $segments[0]);
        $messageType = explode($componentSeparator, $MSH[8] ?? ''); // MSH-9
        $messageVersionID = explode($componentSeparator, $MSH[11] ?? ''); // MSH-12

        $type = $messageType[0];
        if ($type === "ACK") {
            $message->setMetadata("ACK", "ACK", "ACK", $messageVersionID[0], $messageVersionID[1] ?? '', $messageVersionID[2] ?? '');
        }
        else {
            $message->setMetadata($type, $messageType[1] ?? '', $messageType[2] ?? '', $messageVersionID[0], $messageVersionID[1] ?? '', $messageVersionID[2] ?? '');
        }

        // Parse raw message (naively)
        foreach ($segments as $segmentStr) {

            $fields = explode($fieldSeparator, $segmentStr);
            $segmentName = $fields[0];

            $segment = new Segment($segmentName);

            // Fields
            for ($i = 1; $i < count($fields); $i++) {

                if ($segmentName === "MSH" && $i === 1) {
                    // set MSH-1 (fieldSeparator)
                    $segment->addField(
                        $this->createFieldFromValue($fieldSeparator)
                    );
                }

                $field = new Field();

                // field repeats (~)
                $fieldRepeats = explode($fieldRepeatSeparator, $fields[$i]);

                if ($segmentName === "MSH" && $i === 1) {
                    // MSH-2 = '^~\&'
                    $fieldRepeats = array( 0 => $fields[$i] );
                }

                foreach ($fieldRepeats as $rep => $fieldRepeatStr) {

                    $repeat = new FieldRepeat();

                    // components (^)
                    $components = explode($componentSeparator, $fieldRepeatStr);

                    if ($segmentName === "MSH" && $i === 1) {
                        // MSH-2 = '^~\&'
                        $components = array( 0 => $fieldRepeatStr);
                    }

                    foreach ($components as $componentStr) {

                        $component = new Component();
                        // subcomponents (&)
                        $subcomponents = explode($subComponentSeparator, $componentStr);
                        if ($segmentName === "MSH" && $i === 1) {
                            // MSH-2 = '^~\&'
                            $subcomponents = array( 0 => $componentStr);
                        }

                        foreach ($subcomponents as $subcomponentStr) {
                            $component->addSubComponent(
                                new SubComponent($subcomponentStr)
                            );
                        }

                        $repeat->addComponent($component);
                    }

                    $field->addRepeat($repeat);

                }

                $segment->addField($field);
            }

            $message->addSegment($segment);
        }

        return $message;
    }

    /**
     * Create field from value
     *
     * @param string $value
     * @return Field
     */
    private function createFieldFromValue(string $value): Field
    {
        $field = new Field();

        $repeat = new FieldRepeat();

        $component = new Component();
        $component->addSubComponent(
            new SubComponent($value)
        );

        $repeat->addComponent($component);

        $field->addRepeat($repeat);

        return $field;
    }

}
