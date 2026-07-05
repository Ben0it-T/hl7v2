<?php
declare(strict_types=1);

namespace HL7v2\Profile;

class Profile
{
    /**
     * Raw profile definition loaded from JSON.
     *
     * @var array<mixed,mixed>
     */
    private array $definition;


    /**
     * Create profile from raw JSON definition.
     *
     * @param array<mixed,mixed> $definition
     */
    public function __construct(array $definition)
    {
        $this->definition = $definition;
    }

    /**
     * Get raw profile definition.
     *
     * @return array<mixed,mixed>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    /**
     * Find segment definition in profile.
     *
     * @param string $segmentName
     * @return array<mixed,mixed>
     */
    public function findSegmentDefinition(string $segmentName): array
    {
        return $this->findSegmentDefinitionInGroup($segmentName, $this->definition);
    }

    /**
     * Find segment definition in a profile group.
     *
     * @param string $segmentName
     * @param array<mixed,mixed> $segGroup
     * @return array<mixed,mixed>
     */
    private function findSegmentDefinitionInGroup(string $segmentName, array $segGroup): array
    {
        foreach ($segGroup as $child) {
            if ($child['Type'] === 'segment') {
                if ($segmentName === $child['Name']) {
                    return $child;
                }
            } elseif ($child['Type'] === 'group') {
                $definition = $this->findSegmentDefinitionInGroup($segmentName, $child['segments']);
                if ($definition !== []) {
                    return $definition;
                }
            }
        }

        return [];
    }


    /**
     * Get all segment names from profile.
     *
     * @return string[]
     */
    public function getSegmentNames(): array
    {
        return $this->getSegmentNamesInDefinition($this->definition);
    }

    /**
     * Get all segment names from a profile group.
     *
     * @param array<mixed,mixed> $segGroup
     * @return string[]
     */
    public function getSegmentNamesInGroup(array $segGroup): array
    {
        return $this->getSegmentNamesInDefinition($segGroup);
    }

    /**
     * Extract segment names from a profile definition.
     *
     * @param array<mixed,mixed> $segGroup
     * @return string[]
     */
    private function getSegmentNamesInDefinition(array $segGroup): array
    {
        $segmentNames = [];

        foreach ($segGroup as $child) {
            if ($child['Type'] === 'segment') {
                $segmentNames[] = $child['Name'];
            } elseif ($child['Type'] === 'group') {
                $segmentNames = array_merge($segmentNames, $this->getSegmentNamesInDefinition($child['segments']));
            }
        }

        return $segmentNames;
    }

    /**
     * Get first segment names from a profile group.
     *
     * @param array<mixed,mixed> $segGroup
     * @return string[]
     */
    public function getFirstSegmentNamesInGroup(array $segGroup): array
    {
        $segmentNames = [];

        foreach ($segGroup['segments'] as $index => $child) {
            if ($index === 0 && $child['Type'] === 'segment') {
                $segmentNames[] = $child['Name'];
            } elseif ($child['Type'] === 'group') {
                $segmentNames = array_merge($segmentNames, $this->getFirstSegmentNamesInGroup($child));
            }
        }

        return $segmentNames;
    }

}
