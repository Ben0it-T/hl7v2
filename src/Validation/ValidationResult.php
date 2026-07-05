<?php
declare(strict_types=1);

namespace HL7v2\Validation;

class ValidationResult
{
    /**
     * @var string[]
     */
    private array $messages = [];

    /**
     * Add new message.
     *
     * @param string $message
     */
    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Get messages.
     *
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
