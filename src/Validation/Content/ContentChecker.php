<?php
declare(strict_types=1);

namespace HL7v2\Validation\Content;

use HL7v2\Model\Field;
use HL7v2\Model\Location;
use HL7v2\Model\Message;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentField;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Serializer\HL7StringSerializer;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedSegment;
use HL7v2\Validation\Value\Presence;

/**
 * Judges the content of the segments of a Profiled message, in message
 * order, against the definition of each segment:
 * - a placed segment against the definition of its node (with the field
 *   overrides of the message);
 * - a not expected segment against its nearest definition;
 * - a not defined segment is not judged: it has no definition.
 *
 * For each field of the definition:
 * - not present: requiredAbsent if R (no second finding for its minimum);
 *   otherwise no finding, the minimum of C and CE depending on a condition
 *   that is not evaluated;
 * - present (an Explicit null included): notAllowedPresent if X, and
 *   nothing more on that field; W, B, C or CE: one finding; then the count
 *   of repetitions, as received: beyond the maximum, or under the minimum of
 *   an R field;
 * - each repetition with a value: its HL7 length, counted on its Raw value.
 *   An Explicit null has no length to check.
 * A finding on a field is attached to the segment element; its Location
 * gives the field (PID[1]-3) or the repetition (PID[1]-3[2]).
 */
final class ContentChecker
{
    /**
     * Recorder of the checks of the current call to check().
     */
    private CheckRecorder $recorder;

    /**
     * Profiled message of the current call to check().
     */
    private ProfiledMessage $profiled;

    /**
     * Message of the current call to check(): separators and escape sequences.
     */
    private Message $message;

    private HL7StringSerializer $serializer;

    public function __construct()
    {
        $this->serializer = new HL7StringSerializer();
    }

    /**
     * Check the content of each segment of a Profiled message, and attach
     * each finding to its segment element.
     *
     * @param ProfiledMessage $profiled Profiled message given by StructureChecker::check().
     * @param Message $message The message the tree was built from.
     * @param CheckRecorder $recorder Records each check (TestReport entry, Finding).
     *
     * @return void
     */
    public function check(ProfiledMessage $profiled, Message $message, CheckRecorder $recorder): void
    {
        $this->profiled = $profiled;
        $this->message = $message;
        $this->recorder = $recorder;

        foreach ($profiled->root->segments() as $item) {
            $node = $item instanceof SegmentOccurrence ? $item->node : $item->nearest;
            if ($node === null) {
                continue; // Not defined segment: no definition to judge it against.
            }
            $this->checkSegment($item, $node->definition);
        }
    }

    /**
     * Record a check, and attach its finding to the segment element.
     *
     * @param SegmentOccurrence|UnplacedSegment $element
     * @param Check $check
     *
     * @return void
     */
    private function record(SegmentOccurrence|UnplacedSegment $element, Check $check): void
    {
        $finding = $this->recorder->record($check);
        if ($finding !== null) {
            $this->profiled->attach($element, $finding);
        }
    }

    /**
     * Check the fields of a segment against a segment definition.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param SegmentDefinition $definition Definition of its node, or nearest definition.
     *
     * @return void
     */
    private function checkSegment(SegmentOccurrence|UnplacedSegment $item, SegmentDefinition $definition): void
    {
        foreach ($definition->fields as $field) {
            $this->checkField($item, $field, $item->segment->getField($field->position));
        }
    }

    /**
     * Check one field: usage, count of repetitions, length of each repetition.
     *
     * @param SegmentOccurrence|UnplacedSegment $item Segment element that holds the field.
     * @param SegmentField $field Field at its position in the segment definition.
     * @param Field|null $value Field of the message; null when the segment stops before it.
     *
     * @return void
     */
    private function checkField(SegmentOccurrence|UnplacedSegment $item, SegmentField $field, ?Field $value): void
    {
        $definition = $field->definition;
        $label = "Field '{$definition->longName}' ({$definition->id})";
        $usage = $field->usage;
        $location = $this->location($item, $field->position);

        $presence = $value === null ? Presence::NotPresent : Presence::ofField($value);
        if ($value === null || $presence === Presence::NotPresent) {
            $this->record($item, $usage === Usage::Required
                ? $this->newCheck($item, 'Required element', false, "$label is required.", FindingType::RequiredAbsent, $location)
                : $this->newCheck($item, 'Usage', true, "$label usage is {$usage->value}; absent.", null, $location));

            return;
        }

        if ($usage === Usage::NotSupported) {
            $this->record($item, $this->newCheck($item, 'Element not allowed', false, "$label is not allowed.", FindingType::NotAllowedPresent, $location));

            return;
        }

        [$type, $finding, $description] = match ($usage) {
            Usage::Withdrawn => ['Element not allowed', FindingType::WithdrawnPresent, "$label is withdrawn and should not be used."],
            Usage::Backward => ['Usage', FindingType::BackwardPresent, "$label is retained for backward compatibility."],
            Usage::Conditional, Usage::ConditionalOrEmpty => ['Conditional', FindingType::ConditionalPresent,
                "$label usage is {$usage->value}: the condition is not evaluated."],
            default => ['Usage', null, "$label usage is {$usage->value}."],
        };
        $this->record($item, $this->newCheck($item, $type, $finding !== FindingType::WithdrawnPresent, $description, $finding, $location));

        $this->checkRepetitionCount($item, $field, $value->countRepeats(), $label, $location);

        foreach ($value->getRepeats() as $index => $repeat) {
            if ($definition->maxLength === null || Presence::ofRepeat($repeat) !== Presence::Valued) {
                continue;
            }
            $this->checkLength(
                $item,
                $this->serializer->serializeFieldRepeat($repeat, $this->message),
                $definition->maxLength,
                $label . ($value->countRepeats() > 1 ? ' (Rep. ' . ($index + 1) . ')' : ''),
                $this->location($item, $field->position, $index + 1)
            );
        }
    }

    /**
     * Check the count of repetitions of a present field, as received.
     * The minimum is judged for an R field only: for C and CE it depends on
     * a condition that is not evaluated, and O, RE, B, W have a minimum of 0.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param SegmentField $field
     * @param int $count Repetitions received (at least 1).
     * @param string $label
     * @param Location $location Location of the field.
     *
     * @return void
     */
    private function checkRepetitionCount(
        SegmentOccurrence|UnplacedSegment $item,
        SegmentField $field,
        int $count,
        string $label,
        Location $location
    ): void {
        $cardinality = $field->cardinality;
        $written = $cardinality->toString();

        $description = match (true) {
            $cardinality->max !== null && $count > $cardinality->max =>
                "$label cardinality is $written. Must have no more than {$cardinality->max} repetition(s) (found $count).",
            $field->usage === Usage::Required && $count < $cardinality->min =>
                "$label cardinality is $written. Must have at least {$cardinality->min} repetition(s) (found $count).",
            default => null,
        };

        $this->record($item, $description === null
            ? $this->newCheck($item, 'Cardinality', true, "$label cardinality is $written. Found $count time(s).", null, $location)
            : $this->newCheck($item, 'Cardinality', false, $description, FindingType::Cardinality, $location));
    }

    /**
     * Check the HL7 length of a Raw value against a maximum length.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $raw Raw value of the element (a field repetition).
     * @param int $maxLength
     * @param string $label
     * @param Location $location
     *
     * @return void
     */
    private function checkLength(
        SegmentOccurrence|UnplacedSegment $item,
        string $raw,
        int $maxLength,
        string $label,
        Location $location
    ): void {
        $length = $this->message->getEscapeSequences()->length($raw);
        $result = $length <= $maxLength;

        $this->record($item, $this->newCheck(
            $item,
            'Length',
            $result,
            $result
                ? "$label length ($length) does not exceed the length defined in the message profile ($maxLength)."
                : "$label length ($length) exceeds the length defined in the message profile ($maxLength).",
            $result ? null : FindingType::Length,
            $location
        ));
    }

    /**
     * Build a check on an element of a segment, with the positions of the segment.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $type TestReport type.
     * @param bool $result
     * @param string $description
     * @param FindingType|null $finding
     * @param Location $location
     *
     * @return Check
     */
    private function newCheck(
        SegmentOccurrence|UnplacedSegment $item,
        string $type,
        bool $result,
        string $description,
        ?FindingType $finding,
        Location $location
    ): Check {
        return new Check(
            $type, $result, $description, $finding, $location,
            $item->path === '' ? null : $item->path, $item->position
        );
    }

    /**
     * Location of a field, or of one of its repetitions, in a segment element.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param int $field Field position (1-based).
     * @param int|null $repetition Repetition (1-based); null for the whole field.
     *
     * @return Location
     */
    private function location(SegmentOccurrence|UnplacedSegment $item, int $field, ?int $repetition = null): Location
    {
        return new Location(
            $item->segment->getName(),
            $this->profiled->rankOf($item->position),
            $field,
            $repetition
        );
    }
}
