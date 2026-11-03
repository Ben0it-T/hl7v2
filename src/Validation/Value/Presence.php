<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

use HL7v2\Model\Component;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\SubComponent;

/**
 * Presence of an element in a message, read on its Raw value.
 *
 * The Explicit null "" is recognised at the level of a field, a repetition
 * and a component: it is the whole value of that element. In a sub-component
 * that has other sub-components beside it, "" is the literal value.
 *
 * Only the presence is told here: what an Explicit null satisfies or breaks
 * (usage, data type, table, length) is decided by the checks that read it.
 */
enum Presence
{
    /** Empty or absent. */
    case NotPresent;

    /** Explicit null: deliberately null, counts as present. */
    case ExplicitNull;

    /** Present with a value. */
    case Valued;

    /**
     * Presence of a field, all repetitions together.
     *
     * A single repetition gives its own presence (|""| is an Explicit null);
     * several repetitions give Valued as soon as one is present (""~"" too),
     * NotPresent otherwise.
     */
    public static function ofField(Field $field): self
    {
        $repeats = $field->getRepeats();
        if (count($repeats) === 1) {
            return self::ofRepeat($repeats[0]);
        }
        foreach ($repeats as $repeat) {
            if (self::ofRepeat($repeat) !== self::NotPresent) {
                return self::Valued;
            }
        }

        return self::NotPresent;
    }

    /**
     * Presence of a field repetition.
     *
     * A single component gives its own presence (|""| is an Explicit null);
     * several components give Valued as soon as one is present (|""^""| too),
     * NotPresent otherwise (|^^|).
     */
    public static function ofRepeat(FieldRepeat $repeat): self
    {
        $components = $repeat->getComponents();
        if (count($components) === 1) {
            return self::ofComponent($components[0]);
        }
        foreach ($components as $component) {
            if (self::ofComponent($component) !== self::NotPresent) {
                return self::Valued;
            }
        }

        return self::NotPresent;
    }

    /**
     * Presence of a component.
     *
     * A single sub-component "" is an Explicit null (^""^); otherwise Valued
     * as soon as one sub-component is not empty (^a&""^: "" is literal),
     * NotPresent when all are empty (^&^).
     */
    public static function ofComponent(Component $component): self
    {
        $subComponents = $component->getSubComponents();
        if (count($subComponents) === 1 && $subComponents[0]->getRawValue() === '""') {
            return self::ExplicitNull;
        }
        foreach ($subComponents as $subComponent) {
            if ($subComponent->getRawValue() !== '') {
                return self::Valued;
            }
        }

        return self::NotPresent;
    }

    /**
     * Presence of a sub-component.
     *
     * A sub-component is never an Explicit null: "" is its literal value.
     * When it is the only sub-component, ofComponent() sees the null.
     */
    public static function ofSubComponent(SubComponent $subComponent): self
    {
        return $subComponent->getRawValue() === '' ? self::NotPresent : self::Valued;
    }
}
