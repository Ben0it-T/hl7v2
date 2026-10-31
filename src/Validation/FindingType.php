<?php
declare(strict_types=1);

namespace HL7v2\Validation;

/**
 * Type of a finding: the closed vocabulary of the keys of "severities" in
 * rules.json, each of which gives the severity of the findings of that type.
 */
enum FindingType: string
{
    /** Required element (R) absent. */
    case RequiredAbsent = 'requiredAbsent';

    /** Element not allowed (X) present. */
    case NotAllowedPresent = 'notAllowedPresent';

    /** Minimum or maximum cardinality not respected. */
    case Cardinality = 'cardinality';

    /** Segment known by the profile, but not expected at its position. */
    case SegmentNotExpected = 'segmentNotExpected';

    /** Segment unknown to the profile (not a Z segment). */
    case SegmentNotDefined = 'segmentNotDefined';

    /** Z segment unknown to the profile. */
    case ZSegmentNotDefined = 'zSegmentNotDefined';

    /** Field, component or sub-component in excess. */
    case ElementInExcess = 'elementInExcess';

    /** Escape sequence malformed (unclosed, unknown code, invalid data). */
    case EscapeMalformed = 'escapeMalformed';

    /** Escape sequence not allowed in the data type (\H\ in an NM). */
    case EscapeNotAllowed = 'escapeNotAllowed';

    /** Data type of a varies element (OBX-5) undetermined: OBX-2 absent or unknown. */
    case VariesUndetermined = 'variesUndetermined';

    /** Withdrawn element (W) present. */
    case WithdrawnPresent = 'withdrawnPresent';

    /** Backward compatible element (B) present. */
    case BackwardPresent = 'backwardPresent';

    /** Conditional element (C) present, condition not evaluated. */
    case ConditionalPresent = 'conditionalPresent';

    /** Value not valid for its data type. */
    case Datatype = 'datatype';

    /** Value longer than its maximum length. */
    case Length = 'length';

    /** Value not in an HL7 table. */
    case TableHL7 = 'tableHL7';

    /** Value not in a User table. */
    case TableUser = 'tableUser';

    /** Value not in a table closed by the profile. */
    case TableClosed = 'tableClosed';

    /** Table absent from the profile: no check done. */
    case TableUnavailable = 'tableUnavailable';

    /** No validator for the data type: no check done. */
    case NoDatatypeValidator = 'noDatatypeValidator';

    /** Problem met while parsing (character set, invalid bytes). */
    case ParseIssue = 'parseIssue';
}
