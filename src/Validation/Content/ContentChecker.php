<?php
declare(strict_types=1);

namespace HL7v2\Validation\Content;

use HL7v2\Model\Component;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\Location;
use HL7v2\Model\Message;
use HL7v2\Model\SubComponent;
use HL7v2\Profile\Definition\ComponentDefinition;
use HL7v2\Profile\Definition\DataTypeDefinition;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentField;
use HL7v2\Profile\Definition\TableType;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Serializer\HL7StringSerializer;
use HL7v2\Validation\Check;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\Content\LeafTable;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\UnplacedSegment;
use HL7v2\Validation\Value\DisplayableText;
use HL7v2\Validation\Value\EscapePolicy;
use HL7v2\Validation\Value\Presence;
use HL7v2\Validation\Value\PrimitiveValidators;
use HL7v2\Validation\Value\TableMembership;

/**
 * Judges the content of the segments of a Profiled message, in message
 * order, against the definition of each segment:
 * - a placed segment against the definition of its node (with the field
 *   overrides of the message);
 * - a not expected segment against its nearest definition;
 * - a not defined segment is not judged: it has no definition.
 *
 * Usage, at every level (field, component, sub-component):
 * - not present: requiredAbsent if R (no second finding for its minimum);
 *   otherwise no finding, the minimum of C and CE depending on a condition
 *   that is not evaluated;
 * - present (an Explicit null included): notAllowedPresent if X, and
 *   nothing more on that element; W, B, C or CE: one finding.
 *
 * For each field of the definition: its usage; the count of repetitions,
 * as received: beyond the maximum, or under the minimum of an R field; then
 * each repetition with a value: its HL7 length, counted on its Raw value, and
 * its content according to the data type of the field.
 *
 * Content of a value, according to its data type (dataTypes.json):
 * - composite: each component of the type, its usage and HL7 length; a
 *   component with a value and of a composite type: each sub-component, its
 *   usage and HL7 length;
 * - a composite type at the sub-component level cannot be split further
 *   (no separator is left): the value of the sub-component is the first
 *   component of its type, judged by the rules of that component (usage,
 *   HL7 length); the other components cannot be sent and are not judged.
 *   A TS in a DR stays a TS, its value is its TS.1;
 * - primitive (the leaf): the escape sequences of its Raw value, well formed
 *   and allowed by its data type; then its data type: the text types (ST,
 *   TX, FT, GTS, ID, IS, TN) by the characters written outside the escape
 *   sequences (displayable only), any other type by the validator of the
 *   type on the Decoded value (a type without validator gives
 *   noDatatypeValidator); then its table, on the Decoded value.
 *   The leaf of a primitive field or component is its first sub-component;
 *   what follows it is in excess.
 * An Explicit null, or an element without value, has nothing below it to judge.
 *
 * Elements in excess (elementInExcess, one finding per element, its content
 * not judged): an element present (an Explicit null included) at a position
 * the definition does not have: a field beyond the last field of the
 * segment; a component beyond the components of a composite, or after the
 * first one of a primitive field; a sub-component beyond the components of a
 * composite component, or after the first one of a primitive component. HL7
 * asks a receiving application to ignore them (HL7 v2.5 section 2.6.2); a
 * profile does not allow them, as an X element (section 2.12). A composite at
 * the sub-component level has no separator left: nothing is in excess there.
 *
 * A varies field takes the data type named by another field of its segment:
 * OBX-5 the data type of OBX-2 (HL7 v2.5 section 7.4.2.5, "An observation
 * value is always represented as the data type specified in OBX-2"; the same
 * in v2.3 to v2.4). With a value, it is judged as a field of that type. The
 * data type is undetermined (variesUndetermined, and nothing judged below the
 * repetitions, their length aside) when that field is empty, when its value
 * is not a data type of the profile directory, or when no field gives the
 * data type. A value of OBX-2 outside table 0125 is judged by the table check
 * of OBX-2 itself; a data type of the profile directory is used even then.
 *
 * Table of a leaf: the table of its element in the profile, else the table
 * passed down by its parent; the table of a composite is not checked, except
 * for a coded element, CE, CNE or CWE (HL7 v2.5 sections 2.A.6, 2.A.8,
 * 2.A.13; HL7 v2.4 sections 2.9.3, 2.9.8, 2.9.11):
 * - component 1 against the table its coding system (component 3)
 *   designates: HL7nnnn is table nnnn, a table id of the profile is that
 *   table, any other coding system is not a table of the profile and
 *   component 1 is not checked; component 3 empty means the HL7 coding
 *   system: the table of the element;
 * - component 4 against the table its coding system (component 6)
 *   designates, in the same way; component 6 empty: not checked;
 * - a coded element at the sub-component level has no component 3: the
 *   table of the element.
 * Outcome, value not in the table:
 * - CNE, "coded with no exceptions" (the table "may not be extended with
 *   local values"): tableClosed, whatever the type of the table; no local
 *   code (Z...); a local coding system (L, 99zzz) in component 3 is not
 *   allowed either (tableClosed);
 * - otherwise, a table closed by the profile (rules.json): tableClosed;
 * - CWE, "coded with exceptions" (the table "may be extended with local
 *   values"): tableUser, whatever the type of the table;
 * - otherwise according to the type of the table: tableHL7, tableUser.
 * A local code (Z...) is accepted in the tables of localCodeTables, except
 * for a CNE; a table absent from the profile, or without codes, gives
 * tableUnavailable. An empty component 1 of a CWE (text only) is not
 * checked: the leaf has no value.
 *
 * A finding is attached to the segment element; its Location gives the
 * element (PID[1]-3, PID[1]-3[2], PID[1]-5.10, PID[1]-5.10.1).
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

    private EscapePolicy $escapePolicy;

    private PrimitiveValidators $validators;

    private TableMembership $tables;

    private DisplayableText $displayable;

    /**
     * Coded data types: their component 1 and 4 are checked against the table
     * their coding system designates.
     */
    private const CODED_TYPES = ['CE', 'CNE', 'CWE'];

    /**
     * Varies fields, by segment and position: the position of the field that
     * names their data type.
     *
     * @var array<string, array<int, int>>
     */
    private const VARIES_TYPE_FIELDS = ['OBX' => [5 => 2]];

    /**
     * @param ProfileDirectory $directory Profile directory of the message
     *        profile: definitions of the data types, tables and rules.
     */
    public function __construct(
        private readonly ProfileDirectory $directory
    ) {
        $this->serializer = new HL7StringSerializer();
        $this->escapePolicy = new EscapePolicy();
        $this->validators = PrimitiveValidators::standard();
        $this->tables = new TableMembership();
        $this->displayable = new DisplayableText();
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

        $defined = count($definition->fields);
        foreach ($item->segment->getFields() as $index => $field) {
            $position = $index + 1;
            if ($position > $defined && Presence::ofField($field) !== Presence::NotPresent) {
                $this->recordExcess(
                    $item,
                    'Field',
                    $this->location($item, $position),
                    "segment {$definition->name} has $defined field(s)"
                );
            }
        }
    }

    /**
     * Check one field: usage, count of repetitions, then the length and the
     * content of each repetition with a value.
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
        $location = $this->location($item, $field->position);

        $presence = $value === null ? Presence::NotPresent : Presence::ofField($value);
        if (!$this->checkUsage($item, $field->usage, $presence, $label, $location) || $value === null) {
            return;
        }

        $this->checkRepetitionCount($item, $field, $value->countRepeats(), $label, $location);

        $type = $this->dataType($definition->dataType);
        if ($type->name === 'varies') {
            $type = $this->variesType($item, $field, $value, $label, $location);
        }
        // MSH-1 (field separator) and MSH-2 (encoding characters) hold the
        // separators and the escape character themselves: their Raw value is
        // not an escaped value (MSH-2 "^~\&" would read as a malformed "\&").
        $escapes = !($item->segment->getName() === 'MSH' && $field->position <= 2);

        foreach ($value->getRepeats() as $index => $repeat) {
            if (Presence::ofRepeat($repeat) !== Presence::Valued) {
                continue;
            }
            $repLabel = $label . ($value->countRepeats() > 1 ? ' (Rep. ' . ($index + 1) . ')' : '');
            $repLocation = $this->location($item, $field->position, $index + 1);

            if ($definition->maxLength !== null) {
                $this->checkLength(
                    $item,
                    $this->serializer->serializeFieldRepeat($repeat, $this->message),
                    $definition->maxLength,
                    $repLabel,
                    $repLocation
                );
            }

            if ($type === null) {
                continue;
            }
            if ($type->isPrimitive()) {
                if ($escapes) {
                    $this->checkLeaf(
                        $item,
                        $type->name,
                        $repeat->getComponent(1)?->getSubComponent(1),
                        new LeafTable($definition->table),
                        $repLabel,
                        $repLocation
                    );
                    $this->checkExcessComponents($item, $repeat->getComponents(), 1, $type->name, $repLocation);
                }
                continue;
            }
            $this->checkComponents($item, $type, $definition->table, $repeat, $repLocation);
        }
    }

    /**
     * Data type of a varies field, given by another field of its segment
     * (OBX-5 by OBX-2), recorded in the TestReport.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param SegmentField $field The varies field.
     * @param Field $value Its value.
     * @param string $label
     * @param Location $location Location of the field.
     *
     * @return DataTypeDefinition|null Null when the field has no value, or when its
     *                                 data type is undetermined (variesUndetermined).
     */
    private function variesType(
        SegmentOccurrence|UnplacedSegment $item,
        SegmentField $field,
        Field $value,
        string $label,
        Location $location
    ): ?DataTypeDefinition {
        $valued = array_filter(
            $value->getRepeats(),
            static fn (FieldRepeat $repeat): bool => Presence::ofRepeat($repeat) === Presence::Valued
        );
        if ($valued === []) {
            return null;
        }

        $segment = $item->segment->getName();
        $typePosition = self::VARIES_TYPE_FIELDS[$segment][$field->position] ?? null;
        if ($typePosition === null) {
            $this->record($item, $this->newCheck($item, 'Datatype', false,
                "$label data type is undetermined: no field gives the data type of a varies field in $segment.",
                FindingType::VariesUndetermined, $location));

            return null;
        }

        $typeRepeat = $item->segment->getField($typePosition)?->getRepeat(0);
        $name = $typeRepeat !== null && Presence::ofRepeat($typeRepeat) === Presence::Valued
            ? $typeRepeat->getComponent(1)?->getSubComponent(1)?->getDecodedValue() ?? ''
            : '';
        if ($name === '') {
            $this->record($item, $this->newCheck($item, 'Datatype', false,
                "$label data type is undetermined: $segment-$typePosition is empty.",
                FindingType::VariesUndetermined, $location));

            return null;
        }

        $type = $name === 'varies' ? null : $this->directory->dataType($name);
        $this->record($item, $type === null
            ? $this->newCheck($item, 'Datatype', false,
                "$label data type is undetermined: $segment-$typePosition value ($name) is not a data type of the profile.",
                FindingType::VariesUndetermined, $location)
            : $this->newCheck($item, 'Datatype', true,
                "$label data type is $name, given by $segment-$typePosition.", null, $location));

        return $type;
    }

    /**
     * Check the components of a field repetition of a composite type.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param DataTypeDefinition $type Composite type of the field.
     * @param string|null $table Table of the field.
     * @param FieldRepeat $repeat Repetition with a value.
     * @param Location $location Location of the repetition.
     *
     * @return void
     */
    private function checkComponents(
        SegmentOccurrence|UnplacedSegment $item,
        DataTypeDefinition $type,
        ?string $table,
        FieldRepeat $repeat,
        Location $location
    ): void {
        $valueAt = static function (int $position) use ($repeat): string {
            $component = $repeat->getComponent($position);

            return $component !== null && Presence::ofComponent($component) === Presence::Valued
                ? $component->getSubComponent(1)?->getDecodedValue() ?? ''
                : '';
        };

        foreach ($type->components as $definition) {
            $this->checkComponent(
                $item,
                $definition,
                $repeat->getComponent($definition->position),
                $this->codedTable($type, $table, $definition->position, $valueAt),
                self::locate($location, $definition->position)
            );
        }

        $this->checkExcessComponents($item, $repeat->getComponents(), count($type->components), $type->name, $location);
    }

    /**
     * Check one component: usage, then, with a value, its length and its
     * content (sub-components of a composite, or the leaf of a primitive).
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param ComponentDefinition $definition
     * @param Component|null $component Null when the repetition stops before it.
     * @param LeafTable $table Table passed down by the field (a CE).
     * @param Location $location Location of the component.
     *
     * @return void
     */
    private function checkComponent(
        SegmentOccurrence|UnplacedSegment $item,
        ComponentDefinition $definition,
        ?Component $component,
        LeafTable $table,
        Location $location
    ): void {
        $label = "Component '{$definition->longName}' ({$definition->id})";
        $presence = $component === null ? Presence::NotPresent : Presence::ofComponent($component);
        if (!$this->checkUsage($item, $definition->usage, $presence, $label, $location)
            || $component === null
            || $presence !== Presence::Valued
        ) {
            return;
        }

        if ($definition->maxLength !== null) {
            $this->checkLength(
                $item,
                $this->serializer->serializeComponent($component, $this->message),
                $definition->maxLength,
                $label,
                $location
            );
        }

        $type = $this->dataType($definition->dataType);
        if ($type->isPrimitive()) {
            $this->checkLeaf(
                $item,
                $type->name,
                $component->getSubComponent(1),
                $definition->table !== null ? new LeafTable($definition->table) : $table,
                $label,
                $location
            );
            $this->checkExcessSubComponents($item, $component->getSubComponents(), 1, $type->name, $location);

            return;
        }

        $valueAt = static function (int $position) use ($component): string {
            $subComponent = $component->getSubComponent($position);

            return $subComponent !== null && Presence::ofSubComponent($subComponent) === Presence::Valued
                ? $subComponent->getDecodedValue()
                : '';
        };

        foreach ($type->components as $subDefinition) {
            $this->checkSubComponent(
                $item,
                $subDefinition,
                $component->getSubComponent($subDefinition->position),
                $this->codedTable($type, $definition->table, $subDefinition->position, $valueAt),
                self::locate($location, null, $subDefinition->position)
            );
        }

        $this->checkExcessSubComponents($item, $component->getSubComponents(), count($type->components), $type->name, $location);
    }

    /**
     * Check one sub-component: usage, then, with a value, its length and its
     * leaf. A sub-component of a composite type is judged as that type: its
     * value is the first component of the type, judged by the rules of that
     * component; the other components cannot be sent and are not judged.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param ComponentDefinition $definition
     * @param SubComponent|null $subComponent Null when the component stops before it.
     * @param LeafTable $table Table passed down by the component (a CE).
     * @param Location $location Location of the sub-component.
     *
     * @return void
     */
    private function checkSubComponent(
        SegmentOccurrence|UnplacedSegment $item,
        ComponentDefinition $definition,
        ?SubComponent $subComponent,
        LeafTable $table,
        Location $location
    ): void {
        $label = "SubComponent '{$definition->longName}' ({$definition->id})";
        $presence = $subComponent === null ? Presence::NotPresent : Presence::ofSubComponent($subComponent);
        if (!$this->checkUsage($item, $definition->usage, $presence, $label, $location)
            || $subComponent === null
            || $presence !== Presence::Valued
        ) {
            return;
        }

        $raw = $subComponent->getRawValue();
        if ($definition->maxLength !== null) {
            $this->checkLength($item, $raw, $definition->maxLength, $label, $location);
        }

        if ($definition->table !== null) {
            $table = new LeafTable($definition->table, null, self::codedType($definition->dataType));
        }
        $type = $this->dataType($definition->dataType);
        while (!$type->isPrimitive()) {
            $first = $type->component(1)
                ?? throw new \LogicException("Data type {$type->name} has no component.");
            $label = "Component '{$first->longName}' ({$first->id}) of $label";
            if (!$this->checkUsage($item, $first->usage, Presence::Valued, $label, $location)) {
                return;
            }
            if ($first->maxLength !== null) {
                $this->checkLength($item, $raw, $first->maxLength, $label, $location);
            }
            if ($first->table !== null) {
                $table = new LeafTable($first->table, null, self::codedType($first->dataType));
            }
            $type = $this->dataType($first->dataType);
        }

        $this->checkLeaf($item, $type->name, $subComponent, $table, $label, $location);
    }

    /**
     * Record each component of a repetition beyond those of its data type.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param array<int, Component> $components Components of the repetition, from index 0.
     * @param int $defined Number of components of the data type (1 for a primitive).
     * @param string $dataType Data type of the repetition.
     * @param Location $location Location of the repetition.
     *
     * @return void
     */
    private function checkExcessComponents(
        SegmentOccurrence|UnplacedSegment $item,
        array $components,
        int $defined,
        string $dataType,
        Location $location
    ): void {
        foreach ($components as $index => $component) {
            $position = $index + 1;
            if ($position > $defined && Presence::ofComponent($component) !== Presence::NotPresent) {
                $this->recordExcess($item, 'Component', self::locate($location, $position), "data type $dataType has $defined component(s)");
            }
        }
    }

    /**
     * Record each sub-component of a component beyond those of its data type.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param array<int, SubComponent> $subComponents Sub-components of the component, from index 0.
     * @param int $defined Number of components of the data type (1 for a primitive).
     * @param string $dataType Data type of the component.
     * @param Location $location Location of the component.
     *
     * @return void
     */
    private function checkExcessSubComponents(
        SegmentOccurrence|UnplacedSegment $item,
        array $subComponents,
        int $defined,
        string $dataType,
        Location $location
    ): void {
        foreach ($subComponents as $index => $subComponent) {
            $position = $index + 1;
            if ($position > $defined && Presence::ofSubComponent($subComponent) !== Presence::NotPresent) {
                $this->recordExcess($item, 'SubComponent', self::locate($location, null, $position), "data type $dataType has $defined component(s)");
            }
        }
    }

    /**
     * Record an element in excess.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $kind Field, Component or SubComponent.
     * @param Location $location Location of the element in excess.
     * @param string $reason What the definition has.
     *
     * @return void
     */
    private function recordExcess(SegmentOccurrence|UnplacedSegment $item, string $kind, Location $location, string $reason): void
    {
        $this->record($item, $this->newCheck($item, 'Element in excess', false,
            "$kind {$location->toShortString()} is in excess: $reason.",
            FindingType::ElementInExcess, $location));
    }

    /**
     * Check the usage of an element against its presence.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param Usage $usage
     * @param Presence $presence
     * @param string $label
     * @param Location $location
     *
     * @return bool True when the element is present and allowed: its content
     *              is to be judged.
     */
    private function checkUsage(
        SegmentOccurrence|UnplacedSegment $item,
        Usage $usage,
        Presence $presence,
        string $label,
        Location $location
    ): bool {
        if ($presence === Presence::NotPresent) {
            $this->record($item, $usage === Usage::Required
                ? $this->newCheck($item, 'Required element', false, "$label is required.", FindingType::RequiredAbsent, $location)
                : $this->newCheck($item, 'Usage', true, "$label usage is {$usage->value}; absent.", null, $location));

            return false;
        }

        if ($usage === Usage::NotSupported) {
            $this->record($item, $this->newCheck($item, 'Element not allowed', false, "$label is not allowed.", FindingType::NotAllowedPresent, $location));

            return false;
        }

        [$type, $finding, $description] = match ($usage) {
            Usage::Withdrawn => ['Element not allowed', FindingType::WithdrawnPresent, "$label is withdrawn and should not be used."],
            Usage::Backward => ['Usage', FindingType::BackwardPresent, "$label is retained for backward compatibility."],
            Usage::Conditional, Usage::ConditionalOrEmpty => ['Conditional', FindingType::ConditionalPresent,
                "$label usage is {$usage->value}: the condition is not evaluated."],
            default => ['Usage', null, "$label usage is {$usage->value}."],
        };
        $this->record($item, $this->newCheck($item, $type, $finding !== FindingType::WithdrawnPresent, $description, $finding, $location));

        return true;
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
     * @param string $raw Raw value of the element.
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
     * Check a leaf (a primitive value): its escape sequences, then its data
     * type and its table.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $dataType Primitive data type of the leaf.
     * @param SubComponent|null $leaf
     * @param LeafTable $table Table to check the leaf against.
     * @param string $label
     * @param Location $location Location of the element the leaf belongs to.
     *
     * @return void
     */
    private function checkLeaf(
        SegmentOccurrence|UnplacedSegment $item,
        string $dataType,
        ?SubComponent $leaf,
        LeafTable $table,
        string $label,
        Location $location
    ): void {
        // The data type of a varies value (OBX-5) is given by another field:
        // it cannot be judged against "varies".
        if ($leaf === null || $dataType === 'varies') {
            return;
        }

        $raw = $leaf->getRawValue();
        $this->checkEscapes($item, $dataType, $raw, $label, $location);
        if ($raw === '') {
            return;
        }

        $value = $leaf->getDecodedValue();
        $this->checkDataType($item, $dataType, $raw, $value, $label, $location);
        $this->checkTable($item, $table, $value, $label, $location);
    }

    /**
     * Check the escape sequences of a leaf: well formed, and allowed by its
     * data type. Recorded only when the Raw value holds the escape character:
     * a value without it has no sequence to judge.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $dataType Primitive data type of the leaf.
     * @param string $raw Raw value of the leaf.
     * @param string $label
     * @param Location $location
     *
     * @return void
     */
    private function checkEscapes(
        SegmentOccurrence|UnplacedSegment $item,
        string $dataType,
        string $raw,
        string $label,
        Location $location
    ): void {
        $escapeChar = $this->message->getEscapeChar();
        if ($escapeChar === '' || !str_contains($raw, $escapeChar)) {
            return;
        }

        $scan = $this->message->getEscapeSequences()->scan($raw);

        $malformed = $scan->malformed;
        $this->record($item, $malformed === []
            ? $this->newCheck($item, 'Escape', true, "$label escape sequences are well formed.", null, $location)
            : $this->newCheck($item, 'Escape', false,
                "$label has malformed escape sequence(s): " . implode(', ', $malformed) . '.',
                FindingType::EscapeMalformed, $location));

        $notAllowed = $this->escapePolicy->notAllowed($scan, $dataType);
        $this->record($item, $notAllowed === []
            ? $this->newCheck($item, 'Escape', true, "$label escape sequences are allowed in data type $dataType.", null, $location)
            : $this->newCheck($item, 'Escape', false,
                "$label has escape sequence(s) not allowed in data type $dataType: " . implode(', ', $notAllowed) . '.',
                FindingType::EscapeNotAllowed, $location));
    }

    /**
     * Check a leaf against its data type: a text type by its written
     * characters (an ID or an IS is also checked against its table), any
     * other type by the validator of the type on its Decoded value.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param string $dataType Primitive data type of the leaf.
     * @param string $raw Raw value, not empty.
     * @param string $value Decoded value.
     * @param string $label
     * @param Location $location
     *
     * @return void
     */
    private function checkDataType(
        SegmentOccurrence|UnplacedSegment $item,
        string $dataType,
        string $raw,
        string $value,
        string $label,
        Location $location
    ): void {
        if (in_array($dataType, DisplayableText::DATA_TYPES, true)) {
            $controls = $this->displayable->controlCharacters($this->message->getEscapeSequences()->text($raw));
            $this->record($item, $controls === []
                ? $this->newCheck($item, 'Datatype', true, "$label value has displayable characters only ($dataType).", null, $location)
                : $this->newCheck($item, 'Datatype', false,
                    "$label value has control character(s) " . implode(', ', $controls) . ", not allowed in data type $dataType.",
                    FindingType::Datatype, $location));

            return;
        }

        $validator = $this->validators->get($dataType);
        if ($validator === null) {
            $this->record($item, $this->newCheck($item, 'Datatype', true,
                "$label data type is $dataType: no validator, value not checked.",
                FindingType::NoDatatypeValidator, $location));

            return;
        }

        $reason = $validator->check($value);
        $this->record($item, $reason === null
            ? $this->newCheck($item, 'Datatype', true, "$label value is a valid $dataType.", null, $location)
            : $this->newCheck($item, 'Datatype', false, "$label value is not a valid $dataType: $reason",
                FindingType::Datatype, $location));
    }

    /**
     * Check the Decoded value of a leaf against its table.
     *
     * @param SegmentOccurrence|UnplacedSegment $item
     * @param LeafTable $table
     * @param string $value Decoded value, not empty.
     * @param string $label
     * @param Location $location
     *
     * @return void
     */
    private function checkTable(
        SegmentOccurrence|UnplacedSegment $item,
        LeafTable $table,
        string $value,
        string $label,
        Location $location
    ): void {
        if ($table->id === null) {
            if ($table->codingSystem === null) {
                return;
            }
            $this->record($item, $table->codedType === 'CNE' && $this->tables->isLocalCodingSystem($table->codingSystem)
                ? $this->newCheck($item, 'Table', false,
                    "$label value ($value) has the local coding system '{$table->codingSystem}': a CNE allows no local value.",
                    FindingType::TableClosed, $location)
                : $this->newCheck($item, 'Table', true,
                    "$label value ($value) not checked: coding system '{$table->codingSystem}' is not a table of the profile.",
                    null, $location));

            return;
        }

        $id = $table->id;
        $source = $table->codingSystem === null ? '' : " (coding system '{$table->codingSystem}')";
        $definition = $this->directory->table($id);
        if ($definition === null || $definition->codes === []) {
            $this->record($item, $this->newCheck($item, 'Table', true,
                "$label value ($value) not checked: table $id$source "
                    . ($definition === null ? 'is not in the profile.' : 'has no codes in the profile.'),
                FindingType::TableUnavailable, $location));

            return;
        }

        $kind = $definition->type->value . ' table';
        $rules = $this->directory->rules;

        if ($this->tables->contains($definition, $value)) {
            $this->record($item, $this->newCheck($item, 'Table', true,
                "$label value ($value) exists in table $id$source ($kind).", null, $location));

            return;
        }

        $cne = $table->codedType === 'CNE';
        $cwe = $table->codedType === 'CWE';

        if (!$cne && $rules->acceptsLocalCodes($id) && preg_match('/^Z[A-Z0-9_]*$/', $value) === 1) {
            $this->record($item, $this->newCheck($item, 'Table', true,
                "$label value ($value) accepted as a local code of table $id$source ($kind).", null, $location));

            return;
        }

        $closed = $rules->isClosedTable($id);
        $this->record($item, $this->newCheck($item, 'Table', false,
            "$label value ($value) not in table $id$source ($kind" . match (true) {
                $cne    => ', closed by the CNE data type',
                $closed => ', closed by the profile',
                $cwe    => '; the CWE data type allows local values',
                default => '',
            } . ').',
            match (true) {
                $cne || $closed                       => FindingType::TableClosed,
                $cwe                                  => FindingType::TableUser,
                $definition->type === TableType::HL7  => FindingType::TableHL7,
                default                               => FindingType::TableUser,
            },
            $location));
    }

    /**
     * Table passed down by a composite to one of its components: for a coded
     * element (CE, CNE, CWE), components 1 and 4 against the table their
     * coding system designates (components 3, 6); component 1 against the
     * table of the element when component 3 is empty. Nothing for any other
     * composite, or any other component.
     *
     * @param DataTypeDefinition $type Composite type.
     * @param string|null $table Table of the composite element.
     * @param int $position Position of the component (1-based).
     * @param callable(int): string $valueAt Decoded value of a component of
     *        the composite element, empty when it has no value.
     *
     * @return LeafTable
     */
    private function codedTable(DataTypeDefinition $type, ?string $table, int $position, callable $valueAt): LeafTable
    {
        if (!in_array($type->name, self::CODED_TYPES, true) || ($position !== 1 && $position !== 4)) {
            return LeafTable::none();
        }

        $codedType = self::codedType($type->name);
        $codingSystem = $valueAt($position + 2);
        if ($codingSystem === '') {
            return $position === 1 ? new LeafTable($table, null, $codedType) : LeafTable::none();
        }

        // The alternate components (4 to 6) of a CNE may hold a local code.
        if ($position === 4 && $this->tables->isLocalCodingSystem($codingSystem)) {
            $codedType = null;
        }

        return new LeafTable($this->tableDesignatedBy($codingSystem), $codingSystem, $codedType);
    }

    /**
     * The coded type whose rules judge a value outside its table.
     *
     * @param string $dataType
     *
     * @return string|null CNE or CWE; null for any other data type (a CE included).
     */
    private static function codedType(string $dataType): ?string
    {
        return in_array($dataType, ['CNE', 'CWE'], true) ? $dataType : null;
    }

    /**
     * Table designated by a coding system (table 0396): HL7nnnn is table
     * nnnn; a table id of the profile is that table.
     *
     * @param string $codingSystem Coding system, as received.
     *
     * @return string|null Null when the coding system is not a table of the profile.
     */
    private function tableDesignatedBy(string $codingSystem): ?string
    {
        if (preg_match('/^HL7(\d{4})$/', $codingSystem, $matches) === 1) {
            return $matches[1];
        }

        return $this->directory->table($codingSystem) !== null ? $codingSystem : null;
    }

    /**
     * Get a data type definition of the profile directory.
     *
     * @param string $name
     *
     * @return DataTypeDefinition
     *
     * @throws \LogicException When the profile directory does not define it
     *                         (the loader checks every reference).
     */
    private function dataType(string $name): DataTypeDefinition
    {
        return $this->directory->dataType($name)
            ?? throw new \LogicException("Data type $name is not defined in the profile directory.");
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

    /**
     * Location of a component of a repetition, or of a sub-component of a
     * component.
     *
     * @param Location $location Location of the repetition, or of the component.
     * @param int|null $component Component position (1-based); null to keep that of $location.
     * @param int|null $subComponent Sub-component position (1-based).
     *
     * @return Location
     */
    private static function locate(Location $location, ?int $component, ?int $subComponent = null): Location
    {
        return new Location(
            $location->segment,
            $location->segmentRank,
            $location->field,
            $location->repetition,
            $component ?? $location->component,
            $subComponent
        );
    }
}
