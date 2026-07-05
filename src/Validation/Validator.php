<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Message;
use HL7v2\Profile\Profile;
use HL7v2\Profile\HL7Tables;

use HL7v2\Validation\ValidationResult;

use Psr\Log\LoggerInterface;

class Validator
{

    private bool $debug = false;
    private ?LoggerInterface $logger = null;

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    protected function log(string $message): void
    {
        if ($this->debug && $this->logger !== null) {
            $this->logger->debug($message);
        }
    }

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function validate(Message $message, Profile $profile, HL7Tables $tables): ValidationResult
    {
        $this->log('-Validator- Validation started');
        return new ValidationResult();
    }
}
