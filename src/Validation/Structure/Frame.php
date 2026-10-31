<?php
declare(strict_types=1);

namespace HL7v2\Validation\Structure;

use HL7v2\Profile\Definition\GroupNode;

/**
 * A level of the stack of StructureMatcher: a group occurrence being walked,
 * its current child, and how many occurrences of that child it holds so far.
 *
 * @internal Used by StructureMatcher.
 */
final class Frame
{
    /**
     * Index of the current child in GroupNode::$children.
     */
    public int $childIndex = 0;

    /**
     * Occurrences of the current child placed so far, including the one
     * being walked.
     */
    public int $repetition = 0;

    public function __construct(
        public readonly GroupNode $group
    ) {
        //
    }
}
