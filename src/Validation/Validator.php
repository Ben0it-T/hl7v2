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

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Enable or disable debug mode.
     *
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Write debug log.
     *
     * @param string $message
     */
    protected function log(string $message): void
    {
        if ($this->debug && $this->logger !== null) {
            $this->logger->debug($message);
        }
    }

    /**
     * Validate HL7 message against profile.
     *
     * @param Message $message
     * @param Profile $profile
     * @param HL7Tables $tables
     *
     * @return ValidationResult
     */
    public function validate(Message $message, Profile $profile, HL7Tables $tables): ValidationResult
    {
        $this->log('-Validator- Validation started');
        return new ValidationResult();
    }


    // ---
    // --- Check functions
    // ---

    /**
     * Check element usage.
     *  Group, Segment, Field, Component, SubComponent.
     *
     * Supported usage codes:
     * - R: Required
     * - X: Not allowed
     * - C: Conditional
     * - O: Optional
     *
     * Examples:
     * - R + absent  => false
     * - R + present => true
     * - X + present => false
     * - C + present => true
     * - O + absent  => true
     *
     * @param string $elementUsage
     * @param bool $elementExists
     * @param string $elementType
     * @param string $elementName
     *
     * @return array{
     *     result: bool,
     *     type: string,
     *     description: string
     * }
     */
    private function checkUsage(string $elementUsage, bool $elementExists, string $elementType, string $elementName): array
    {

        if ($elementUsage === 'R' && !$elementExists) {

            $description = "$elementType $elementName is required.";
            $type = 'Required element';
            $result = false;

        } elseif ($elementUsage === 'X' && $elementExists) {

            $description = "$elementType $elementName is not allowed.";
            $type = 'Element not allowed';
            $result = false;

        } elseif ($elementUsage === 'C' && $elementExists) {

            $description =
                "$elementType $elementName optionality is set as 'conditional'. "
                . "Refer to the specification to check the optionality which applies in the context of this message.";

            $type = 'Conditional';
            $result = true;

        } else {

            $description =
                "$elementType $elementName usage is $elementUsage.";

            $type = 'Usage';
            $result = true;
        }

        $this->log("-$elementType- $type: $description");

        return [
            'result' => $result,
            'type' => $type,
            'description' => $description,
        ];
    }

}
