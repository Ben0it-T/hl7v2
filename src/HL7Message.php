<?php
declare(strict_types=1);

namespace HL7v2;

use HL7v2\Exception\HL7Exception;
use HL7v2\Parser\HL7Parser;
use HL7v2\Model\Message;

class HL7Message
{
    private ?Message $message = null;
    private HL7Parser $parser;

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
}
