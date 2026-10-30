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
use HL7v2\Parser\InputEncoding;
use HL7v2\Parser\CharacterSet;

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
     * @param InputEncoding $inputEncoding Encoding of $raw (default: as declared by MSH-18).
     * @return Message
     * @throws HL7Exception
     */
    public function parse(string $raw, InputEncoding $inputEncoding = InputEncoding::Msh18): Message
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

        // Convert to UTF-8 before splitting: in some multi-byte character
        // sets (BIG-5), a byte of a character may equal a separator.
        [$raw, $characterSet, $sourceEncoding, $parseIssues] =
            $this->toUtf8($raw, $matches[2], $matches[4], $inputEncoding);

        $message = new Message();

        $message->setCharacterSet($characterSet->value, $sourceEncoding);
        foreach ($parseIssues as $issue) {
            $message->addParseIssue($issue);
        }

        $message->setSeparators($fieldSeparator, $componentSeparator, $fieldRepeatSeparator, $escapeChar, $subComponentSeparator);
        $escapeSequences = $message->getEscapeSequences();

        // Split message to segments (CR / LF)
        $segments = preg_split("/[\n\r" . $this->segmentSeparator . ']/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        if ($segments === false) { // array<int,string>|false
            throw new HL7Exception('Unable to split message into segments.');
        }

        // Get message metadata
        $MSH = explode($fieldSeparator, $segments[0]);
        $messageType = explode($componentSeparator, $MSH[8] ?? ''); // MSH-9
        $messageVersionID = explode($componentSeparator, $MSH[11] ?? ''); // MSH-12

        // Message type, trigger event and structure as written in MSH-9
        // (the structure is empty in HL7 2.3 and 2.3.1).
        $message->setMetadata($messageType[0], $messageType[1] ?? '', $messageType[2] ?? '', $messageVersionID[0], $messageVersionID[1] ?? '', $messageVersionID[2] ?? '');

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
                            // MSH-2 holds the encoding characters: never decoded.
                            $component->addSubComponent(
                                new SubComponent(
                                    $subcomponentStr,
                                    $segmentName === "MSH" && $i === 1 ? null : $escapeSequences
                                )
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
     * Convert the message to UTF-8 according to MSH-18 (first repetition).
     *
     * A character set that is unknown or not supported is read as UTF-8.
     * Bytes that are invalid for the encoding are replaced by U+FFFD.
     * Each problem is returned as a parse issue: parsing never stops.
     *
     * @param non-empty-string $fieldSeparator
     * @param non-empty-string $repetitionSeparator
     *
     * @return array{string, CharacterSet, string, list<string>}
     *         UTF-8 message, character set, source encoding, parse issues.
     */
    private function toUtf8(string $raw, string $fieldSeparator, string $repetitionSeparator, InputEncoding $inputEncoding): array
    {
        $issues = [];

        $mshSegment = (string) strtok($raw, "\r\n");
        $msh18 = explode($repetitionSeparator, explode($fieldSeparator, $mshSegment)[17] ?? '')[0];
        $characterSet = CharacterSet::fromMsh18($msh18);

        if (!$characterSet->isSupported()) {
            $issues[] = sprintf(
                "MSH-18: %s character set '%s'; the message is read as UTF-8.",
                $characterSet->known ? 'unsupported' : 'unknown',
                $characterSet->value
            );
        }

        $sourceEncoding = $inputEncoding === InputEncoding::Utf8
            ? 'UTF-8'
            : ($characterSet->encoding ?? 'UTF-8');

        if (!mb_check_encoding($raw, $sourceEncoding)) {
            $issues[] = sprintf(
                'The message is not valid %s%s; invalid bytes are replaced by U+FFFD.',
                $sourceEncoding,
                $inputEncoding === InputEncoding::Utf8 ? ' (input declared UTF-8)' : ''
            );
        }

        $substitute = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        try {
            $utf8 = mb_convert_encoding($raw, 'UTF-8', $sourceEncoding);
        } finally {
            mb_substitute_character($substitute);
        }

        if ($utf8 === false) {
            throw new HL7Exception(sprintf('Unable to convert the message from %s to UTF-8.', $sourceEncoding));
        }

        return [$utf8, $characterSet, $sourceEncoding, $issues];
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
