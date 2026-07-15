<?php
declare(strict_types=1);

namespace HL7v2;

use HL7v2\Exception\HL7Exception;
use HL7v2\Parser\HL7Parser;
use HL7v2\Model\Message;
use HL7v2\Validation\ValidationResult;

class HL7Message
{
    private ?Message $message = null;
    private HL7Parser $parser;
    private ?ValidationResult $validationResult = null;

    /**
     * Profiled message representation.
     * Legacy equivalent of msgData.
     *
     * @var array<string, mixed>
     */
    private array $profiledMessage = [];

    public function __construct()
    {
        $this->parser = new HL7Parser();
    }

    /**
     * Parse HL7 message string
     *
     * @param string $rawMessage
     * @throws HL7Exception If the message cannot be parsed.
     */
    public function parse(string $rawMessage): void
    {
        $this->message = $this->parser->parse($rawMessage);
    }

    /**
     * Get message
     *
     * @return Message|null
     */
    public function getMessage(): ?Message
    {
        return $this->message;
    }

    /**
     * Get profiled message representation.
     * Legacy equivalent of msgData.
     *
     * @return array<string, mixed>
     */
    public function getProfiledMessage(): array
    {
        return $this->profiledMessage;
    }

    /**
     * Get validation result
     *
     * @return ValidationResult|null
     */
    public function getValidationResult(): ?ValidationResult
    {
        return $this->validationResult;
    }
}
