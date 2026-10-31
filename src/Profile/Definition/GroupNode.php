<?php
declare(strict_types=1);

namespace HL7v2\Profile\Definition;

use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;

/**
 * A group of a message structure (the root is the structure itself:
 * ORU_R01, R [1..1]).
 */
final readonly class GroupNode extends StructureNode
{
    /**
     * @param list<StructureNode> $children In profile order; for a choice group, the alternatives.
     * @param bool $choice True for a choice group: each occurrence holds exactly one of its children.
     */
    public function __construct(
        string $name,
        Usage $usage,
        Cardinality $cardinality,
        public array $children,
        public bool $choice = false,
        ?string $source = null
    ) {
        [$entrySegments, $emptyContent] = $choice
            ? self::choiceEntries($children)
            : self::sequenceEntries($children);

        parent::__construct(
            $name,
            $usage,
            $cardinality,
            $cardinality->min === 0 || $emptyContent,
            $entrySegments,
            $source
        );
    }

    /**
     * Sequence: the entry segments of the children up to the first one that
     * cannot be skipped, included. The content can be empty when every child
     * can be skipped.
     *
     * @param list<StructureNode> $children
     *
     * @return array{list<string>, bool} Entry segments, and whether the content can be empty.
     */
    private static function sequenceEntries(array $children): array
    {
        $entrySegments = [];
        foreach ($children as $child) {
            $entrySegments = [...$entrySegments, ...$child->entrySegments];
            if (!$child->skippable) {
                return [array_values(array_unique($entrySegments)), false];
            }
        }

        return [array_values(array_unique($entrySegments)), true];
    }

    /**
     * Choice: the entry segments of every alternative. The content can be
     * empty when one alternative can be skipped.
     *
     * @param list<StructureNode> $children
     *
     * @return array{list<string>, bool} Entry segments, and whether the content can be empty.
     */
    private static function choiceEntries(array $children): array
    {
        $entrySegments = [];
        $emptyContent = false;
        foreach ($children as $child) {
            $entrySegments = [...$entrySegments, ...$child->entrySegments];
            $emptyContent = $emptyContent || $child->skippable;
        }

        return [array_values(array_unique($entrySegments)), $emptyContent];
    }
}
