<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\Field;

class Segment
{
    private string $name;

    /**
     * @var Field[]
     */
    private array $fields = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add field
     *
     * @param Field $field
     */
    public function addField(Field $field): void
    {
        $this->fields[] = $field;
    }

    /**
     * Get field
     *
     * HL7 positions are 1-based.
     *
     * @param int $index
     * @return Field|null
     */
    public function getField(int $index): ?Field
    {
        return $this->fields[$index - 1] ?? null;
    }

    /**
     * Get fields
     *
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
