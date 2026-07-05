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
}
