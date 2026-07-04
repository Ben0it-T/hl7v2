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
}
