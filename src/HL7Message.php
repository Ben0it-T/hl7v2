<?php
declare(strict_types=1);

namespace HL7v2;

use HL7v2\Exception\HL7Exception;
use HL7v2\Model\Message;
use HL7v2\Parser\HL7Parser;
use HL7v2\Profile\Profile;
use HL7v2\Profile\HL7Tables;
use HL7v2\Profile\ProfileLoader;
use HL7v2\Profile\HL7TableLoader;
use HL7v2\Serializer\HL7DatatypeXmlSerializer;
use HL7v2\Serializer\HL7StructuralXmlSerializer;
use HL7v2\Validation\Validator;
use HL7v2\Validation\ValidationResult;

use Psr\Log\LoggerInterface;

class HL7Message
{

    private bool $debug = false;
    private ?LoggerInterface $logger = null;

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



    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->parser = new HL7Parser();
    }

    /**
     * Enable or disable debug logging.
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
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
     * Validate the parsed HL7 message using profile definitions
     * and HL7 tables loaded from the specified profile path.
     *
     * @param string $profilePath Path to HL7 profile definitions.
     *
     * @return ValidationResult
     *
     * @throws HL7Exception If no message has been parsed
     *                      or if the profile/tables cannot be loaded.
     */
    public function validate(string $profilePath): ValidationResult
    {
        if ($this->message === null) {
            throw new HL7Exception(
                'No message parsed.'
            );
        }

        $profileLoader = new ProfileLoader($profilePath);
        $profile = $profileLoader->load(
            $this->message->getVersionId(),
            $this->message->getMessageCode(),
            $this->message->getTriggerEvent(),
            $this->message->getStructure()
        );

        $hl7TableLoader = new HL7TableLoader($profilePath);
        $tables = $hl7TableLoader->load($this->message->getVersionId());

        $validator = new Validator($this->logger);
        $validator->setDebug($this->debug);
        $this->validationResult = $validator->validate($this->message, $profile, $tables);
        $this->profiledMessage = $validator->getProfiledMessage();

        return $this->validationResult;
    }

    /**
     * Validate the parsed HL7 message using the provided profile
     * definition and HL7 tables.
     *
     * @param Profile $profile
     * @param HL7Tables $tables
     *
     * @return ValidationResult
     *
     * @throws HL7Exception if no message has been parsed.
     */
    public function validateWith(Profile $profile, HL7Tables $tables): ValidationResult
    {
        if ($this->message === null) {
            throw new HL7Exception(
                'No message parsed.'
            );
        }

        $validator = new Validator($this->logger);
        $validator->setDebug($this->debug);
        $this->validationResult = $validator->validate($this->message, $profile, $tables);
        $this->profiledMessage = $validator->getProfiledMessage();

        return $this->validationResult;
    }

    /**
     * Convert parsed message to structural XML.
     *
     * @param bool $includeNamespace
     *
     * @return string
     *
     * @throws HL7Exception If no message has been parsed.
     */
    public function toStructuralXml(bool $includeNamespace = true): string
    {
        if ($this->message === null) {
            throw new HL7Exception(
                'No message parsed.'
            );
        }

        $serializer = new HL7StructuralXmlSerializer();

        return $serializer->serialize($this->message, $includeNamespace);
    }

    /**
     * Convert profiled message to HL7 datatype-aware XML representation.
     *
     * @param bool $includeNamespace Export HL7 XML namespace.
     *
     * @return string
     *
     * @throws HL7Exception If no message has been parsed.
     */
    public function toDatatypeXml(bool $includeNamespace = true): string
    {
        if ($this->profiledMessage === []) {
            throw new HL7Exception(
                'No profiled message available. Validate the message first.'
            );
        }

        $serializer = new HL7DatatypeXmlSerializer();

        return $serializer->serialize($this->profiledMessage, $includeNamespace);
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
     * Get validation result.
     *
     * @return ValidationResult|null
     */
    public function getValidationResult(): ?ValidationResult
    {
        return $this->validationResult;
    }

}
