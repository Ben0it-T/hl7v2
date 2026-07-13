<?php
declare(strict_types=1);

namespace HL7v2\Validation;

/**
 * Navigation states used by validateGroup()
 * to synchronize message and profile traversal.
 */
enum NavigationState
{
    case GROUP_NOT_FOUND;       // Move On
    case SEGMENT_MATCHED;       // Validate
    case SEGMENT_NOT_DEFINED;   // Move Back
    case SEGMENT_NOT_EXPECTED;  // Move Back
    case SEGMENT_APPEARS_LATER; // Move On
}
