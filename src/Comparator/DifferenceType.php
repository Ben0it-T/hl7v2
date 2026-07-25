<?php
declare(strict_types=1);

namespace HL7v2\Comparator;

enum DifferenceType
{
    case ADDED;
    case REMOVED;
    case CHANGED;
}
