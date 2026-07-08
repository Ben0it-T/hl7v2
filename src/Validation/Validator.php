<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Message;
use HL7v2\Model\Component;
use HL7v2\Model\SubComponent;

use HL7v2\Profile\Profile;
use HL7v2\Profile\HL7Tables;

use HL7v2\Serializer\HL7StringSerializer;
use HL7v2\Validation\ValidationResult;

use Psr\Log\LoggerInterface;

class Validator
{

    private bool $debug = false;

    private Message $message;
    private HL7StringSerializer $serializer;

    /**
     * HL7 tables indexed by table number.
     *
     * @var array<string, mixed>
     */
    private array $hl7Tables = [];

    private ?LoggerInterface $logger = null;
    private ValidationResult $validationResult;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->serializer = new HL7StringSerializer();
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
        $this->hl7Tables = $tables->getTables();
        $this->validationResult = new ValidationResult();
        $this->message = $message;

        $this->log('-Validator- Validation started');
        return $this->validationResult;
    }


    // ---
    // --- Check functions
    // ---

    /**
     * Check element usage.
     * Applies to: Group, Segment, Field, Component, SubComponent.
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

    /**
     * Check element cardinality ([min..max]).
     * Applies to: Group, Segment, Field
     *
     * Examples:
     * - [1..1]  with 1 occurrence  => valid
     * - [1..*]  with 25 occurrences => valid
     * - [1..3]  with 0 occurrence  => minimum not met
     * - [1..3]  with 4 occurrences => maximum exceeded
     * - [1..3]  with 0 occurrence and O usage => valid
     *
     * @param string $min
     * @param string $max
     * @param int $elementCnt
     * @param string $elementUsage
     * @param string $elementType
     * @param string $elementName
     *
     * @return array{
     *     result: bool,
     *     type: string,
     *     description: string
     * }
     */
    private function checkCardinality(string $min, string $max, int $elementCnt, string $elementUsage, string $elementType, string $elementName): array
    {
        $maxStr = $max;

        $max = ($maxStr === '*')
            ? INF
            : (int) $maxStr;

        $min = (int) $min;

        if (($elementCnt < $min) && $elementUsage === 'R') {

            $description =
                "$elementType $elementName cardinality is [$min..$maxStr]. "
                . "Must have at least $min repetition(s) (found $elementCnt).";

            $result = false;
            $type = 'Cardinality';

        } elseif ($elementCnt > $max) {

            $description =
                "$elementType $elementName cardinality is [$min..$maxStr]. "
                . "Must have no more than $maxStr repetition(s) (found $elementCnt).";

            $result = false;
            $type = 'Cardinality';

        } else {

            $description =
                "$elementType $elementName cardinality is [$min..$maxStr]. "
                . "Found $elementCnt time(s).";

            $result = true;
            $type = 'Cardinality';
        }

        $this->log(
            "-$elementType- $type: $description"
        );

        return [
            'result' => $result,
            'type' => $type,
            'description' => $description,
        ];
    }

    /**
     * Check element length.
     * Applies to: Field, Component, SubComponent.
     *
     * Examples:
     * - max length 20, value length 10 => valid
     * - max length 20, value length 20 => valid
     * - max length 20, value length 21 => invalid
     *
     * @param int $length
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     *
     * @return array{
     *     result: bool,
     *     type: string,
     *     description: string
     * }
     */
    private function checkLength(int $length, string $elementValue, string $elementType, string $elementName): array
    {
        $type = 'Length';
        $result = mb_strlen($elementValue) <= $length;
        $description =
            "$elementType $elementName length "
            . ($result ? 'does not exceed' : 'exceeds')
            . " the length defined in the message profile ($length).";

        $this->log(
            "-$elementType- $type: $description"
        );

        return [
            'result' => $result,
            'type' => $type,
            'description' => $description,
        ];
    }

    /**
     * Check HL7 table value.
     * Applies to: Field, Component, SubComponent.
     *
     * Validation is currently case-insensitive to preserve legacy Validator behavior.
     *
     * @param string $table
     * @param string $elementValue
     * @param string $elementType
     * @param string $elementName
     *
     * @return array{
     *     result: bool,
     *     type: string,
     *     description: string
     * }
     */
    private function checkHL7Table(string $table, string $elementValue, string $elementType, string $elementName): array
    {
        $type = 'Table';

        // Preserve legacy behaviour (case-insensitive comparison).
        // TODO: Audit HL7 tables and switch to strict case-sensitive validation.
        $result = in_array(
            strtoupper($elementValue),
            array_map(
                'strtoupper',
                $this->hl7Tables[$table]['elements']
            )
        );

        $description =
            "$elementType $elementName value ($elementValue) "
            . ($result ? 'exists in' : 'not in')
            . " table $table ("
            . ($this->hl7Tables[$table]['type'] === 'HL7'
                ? 'HL7 standard'
                : 'User defined')
            . " tables).";

        $this->log(
            "-$elementType- $type: $description"
        );

        return [
            'result' => $result,
            'type' => $type,
            'description' => $description,
        ];
    }


    // ---
    // --- Validate functions
    // ---


    /**
     * Validate component.
     *
     * Validates the component against its profile definition,
     * updates validation reports,
     * and returns the profiled representation of the component.
     *
     * TODO:
     * Refactor after complete Validator migration.
     *
     * @param Component|null $component
     * @param array<string, mixed> $componentDef
     * @param string $location
     *
     * @return array<string, mixed>
     */
    private function validateComponent(?Component $component, array $componentDef, string $location): array
    {
        $value = $component !== null
            ? $this->serializer->serializeComponent($component, $this->message)
            : '';

        $exists = (
            $component !== null
            && $value !== ''
        );
        $elementName = "'{$componentDef['LongName']}' ({$componentDef['Name']})";
        $hasError = false;
        $comments = '';

        $this->log(
            "-Component- $location : component exists: "
            . ($exists ? 'true' : 'false')
            . " ({$componentDef['Usage']})"
        );

        // check usage
        $usage = $this->checkUsage(
            $componentDef['Usage'],
            $exists,
            'Component',
            $elementName
        );

        $this->validationResult->addTestReport([
            'Location'    => $location,
            'Description' => $usage['description'],
            'Type'        => $usage['type'],
            'Result'      => $usage['result'],
        ]);

        if (!$usage['result']) {
            $hasError = true;
            $comments .= $usage['description'] . " ";
        }

        if ($exists) {

            // check length
            if ($componentDef["maxLength"] !== "") {
                $lengthCheck = $this->checkLength(
                    (int) $componentDef["maxLength"],
                    $value,
                    'Component',
                    $elementName
                );

                $this->validationResult->addTestReport([
                    'Location'    => $location,
                    'Description' => $lengthCheck['description'],
                    'Type'        => $lengthCheck['type'],
                    'Result'      => $lengthCheck['result'],
                ]);

                if (!$lengthCheck['result']) {
                    $hasError = true;
                    $comments .= $lengthCheck['description'] . " ";
                }
            }

            // check table - only if simple component
            if (
                !isset($componentDef['components'])
                && $componentDef['Table'] !== ""
                && isset($this->hl7Tables[$componentDef['Table']])
            ) {
                if (!empty($this->hl7Tables[$componentDef['Table']]['elements'])) {
                    $tableCheck = $this->checkHL7Table(
                        $componentDef["Table"],
                        $value,
                        'Component',
                        $elementName
                    );

                    $this->validationResult->addTestReport([
                        'Location'    => $location,
                        'Description' => $tableCheck['description'],
                        'Type'        => $tableCheck['type'],
                        'Result'      => $tableCheck['result'],
                    ]);

                    if (!$tableCheck['result']) {
                        $hasError = true;
                        $comments .= $tableCheck['description'] . " ";
                    }
                }
            }

        }

        $this->validationResult->addValidationReport([
            "type"            => "Component",
            "location"        => $location,
            "name"            => $componentDef["Name"],
            "longName"        => $componentDef["LongName"],
            "usage"           => $componentDef["Usage"],
            "datatype"        => $componentDef["Type"],
            "length"          => $componentDef["maxLength"],
            "table"           => $componentDef["Table"],
            "impNote"         => "",
            "elementValue"    => $value,
            "elementExists"   => $exists,
            "elementError"    => $hasError,
            "elementComments" => trim($comments),
        ]);

        $componentArray = [
            "Type"     => "component",
            "Name"     => $componentDef["Name"],
            "LocName"  => $location,
            "LongName" => $componentDef["LongName"],
            "Datatype" => $componentDef["Type"],
            "hasError" => $hasError,
            "comments" => trim($comments),
            "value"    => $value,
        ];


        if ($exists) {
            // Note:
            //  If Component is absent
            //  Don't validate sub-component

            if (isset($componentDef["components"])) {
                // validate subComponents
                $componentArray["subcomponents"] = [];

                foreach ($componentDef['components'] as $i => $subComponentDef) {
                    $subComponent = $component->getSubComponent($i + 1);

                    $componentArray['subcomponents'][$i + 1] = $this->validateSubComponent(
                        $subComponent,
                        $subComponentDef,
                        "{$location}." . ($i + 1)
                    );
                }

                // Check if there are more subComponent in message - Element not expected
                if ($component->countSubComponents() > count($componentDef["components"])) {
                    $this->log(
                        "-Component- There are more subComponents in component '{$location}'."
                    );
                    for ($i = count($componentDef["components"]); $i < $component->countSubComponents(); $i++) {
                        $subComponent = $component->getSubComponent($i + 1);

                        if ($subComponent === null) {
                            throw new \LogicException('Unexpected null sub-component.');
                        }

                        $subComponentPosition = $i + 1;
                        $subComponentLocation = "{$location}.{$subComponentPosition}";

                        $description = "SubComponent '{$subComponentLocation}' is not expected in Component '$location' structure.";

                        $notDefinedSubComponent = $this->createNotDefinedSubComponent($subComponent, $location, $subComponentPosition);
                        $notDefinedSubComponent["hasError"] = true;
                        $notDefinedSubComponent["comments"] = $description;
                        $componentArray["subcomponents"][$i + 1] = $notDefinedSubComponent;

                        $this->log(
                            "-SubComponent- {$subComponentLocation} :  {$description}"
                        );

                        $this->validationResult->addTestReport([
                            'Location'    => $subComponentLocation,
                            'Description' => $description,
                            'Type'        => "Element not expected",
                            'Result'      => false,
                        ]);

                        $this->validationResult->addValidationReport([
                            "type"            => "SubComponent",
                            "location"        => $subComponentLocation,
                            "name"            => "UNKNOWN.{$subComponentPosition}",
                            "longName"        => "Not defined subcomponent",
                            "usage"           => "",
                            "datatype"        => "UNKNOWN",
                            "length"          => "",
                            "constantValue"   => "",
                            "table"           => "",
                            "impNote"         => "",
                            "elementValue"    => $subComponent->getValue(),
                            "elementExists"   => true,
                            "elementError"    => true,
                            "elementComments" => trim($description),
                        ]);
                    }
                }

            } elseif ($component->countSubComponents() > 1) {
                // There is no subComponent in the profile
                // Check if there are more than one SubComponent in message

                $this->log(
                    "-Component- SubComponent are not expected in Component '{$location}' structure."
                );

                for ($i = 0; $i < $component->countSubComponents(); $i++) {
                    $subComponent = $component->getSubComponent($i + 1);

                    if ($subComponent === null) {
                        throw new \LogicException('Unexpected null sub-component.');
                    }

                    $subComponentPosition = $i + 1;
                    $subComponentLocation = "{$location}.{$subComponentPosition}";

                    $description = "SubComponent '{$subComponentLocation}' is not expected in Component '$location' structure.";

                    $notDefinedSubComponent = $this->createNotDefinedSubComponent($subComponent, $location, $subComponentPosition);
                    $notDefinedSubComponent["hasError"] = true;
                    $notDefinedSubComponent["comments"] = $description;
                    $componentArray["subcomponents"][$i + 1] = $notDefinedSubComponent;

                    $this->log(
                        "-SubComponent- {$subComponentLocation} :  {$description}"
                    );

                    $this->validationResult->addTestReport([
                        'Location'    => $subComponentLocation,
                        'Description' => $description,
                        'Type'        => "Element not expected",
                        'Result'      => false,
                    ]);

                    $this->validationResult->addValidationReport([
                        "type"            => "SubComponent",
                        "location"        => $subComponentLocation,
                        "name"            => "UNKNOWN.{$subComponentPosition}",
                        "longName"        => "Not defined subcomponent",
                        "usage"           => "",
                        "datatype"        => "UNKNOWN",
                        "length"          => "",
                        "constantValue"   => "",
                        "table"           => "",
                        "impNote"         => "",
                        "elementValue"    => $subComponent->getValue(),
                        "elementExists"   => true,
                        "elementError"    => true,
                        "elementComments" => trim($description),
                    ]);
                }
            }

        }

        return $componentArray;
    }



    /**
     * Validate sub-component.
     *
     * Validates the sub-component against its profile definition,
     * updates validation reports,
     * and returns the profiled representation of the sub-component.
     *
     * TODO:
     * Refactor after complete Validator migration.
     *
     * @param SubComponent|null $subComponent
     * @param array<string, mixed> $subComponentDef
     * @param string $location
     *
     * @return array<string, mixed>
     */
    private function validateSubComponent(?SubComponent $subComponent, array $subComponentDef, string $location): array
    {
        $exists = (
            $subComponent !== null
            && $subComponent->getValue() !== ''
        );
        $value = $subComponent?->getValue() ?? '';
        $elementName = "'{$subComponentDef['LongName']}' ({$subComponentDef['Name']})";
        $hasError = false;
        $comments = '';

        $this->log(
            "-SubComponent- $location : subcomponent exists: "
            . ($exists ? 'true' : 'false')
            . " ({$subComponentDef['Usage']})"
        );

        // check usage
        $usage = $this->checkUsage(
            $subComponentDef['Usage'],
            $exists,
            'SubComponent',
            $elementName
        );

        $this->validationResult->addTestReport([
            'Location'    => $location,
            'Description' => $usage['description'],
            'Type'        => $usage['type'],
            'Result'      => $usage['result'],
        ]);

        if (!$usage['result']) {
            $hasError = true;
            $comments .= $usage['description'] . " ";
        }

        if ($exists) {

            // check length
            if ($subComponentDef["maxLength"] !== "") {
                $lengthCheck = $this->checkLength(
                    (int) $subComponentDef["maxLength"],
                    $value,
                    'SubComponent',
                    $elementName
                );

                $this->validationResult->addTestReport([
                    'Location'    => $location,
                    'Description' => $lengthCheck['description'],
                    'Type'        => $lengthCheck['type'],
                    'Result'      => $lengthCheck['result'],
                ]);

                if (!$lengthCheck['result']) {
                    $hasError = true;
                    $comments .= $lengthCheck['description'] . " ";
                }
            }

            // check table
            if ($subComponentDef['Table'] !== "" && isset($this->hl7Tables[$subComponentDef['Table']])) {
                if (!empty($this->hl7Tables[$subComponentDef['Table']]['elements'])) {
                    $tableCheck = $this->checkHL7Table(
                        $subComponentDef["Table"],
                        $value,
                        'SubComponent',
                        $elementName
                    );

                    $this->validationResult->addTestReport([
                        'Location'    => $location,
                        'Description' => $tableCheck['description'],
                        'Type'        => $tableCheck['type'],
                        'Result'      => $tableCheck['result'],
                    ]);

                    if (!$tableCheck['result']) {
                        $hasError = true;
                        $comments .= $tableCheck['description'] . " ";
                    }
                }
            }

        }

        $this->validationResult->addValidationReport([
            "type"            => "SubComponent",
            "location"        => $location,
            "name"            => $subComponentDef["Name"],
            "longName"        => $subComponentDef["LongName"],
            "usage"           => $subComponentDef["Usage"],
            "datatype"        => $subComponentDef["Type"],
            "length"          => $subComponentDef["maxLength"],
            "constantValue"   => "",
            "table"           => $subComponentDef["Table"],
            "impNote"         => "",
            "elementValue"    => $value,
            "elementExists"   => $exists,
            "elementError"    => $hasError,
            "elementComments" => trim($comments),
        ]);

        // profiled representation of the sub-component
        $subComponentArray = [
            "Type"     => "subcomponent",
            "Name"     => $subComponentDef["Name"],
            "LocName"  => $location,
            "LongName" => $subComponentDef["LongName"],
            "Datatype" => $subComponentDef["Type"],
            "hasError" => $hasError,
            "comments" => trim($comments),
            "value"    => $value,
        ];

        return $subComponentArray;
    }


    // ---
    // --- Create not defined element functions
    // ---

    /**
     * Create profiled representation of a sub-component
     * not defined in the profile.
     *
     * @param SubComponent $subComponent
     * @param string $location
     * @param int $position
     *
     * @return array<string, mixed>
     */
    private function createNotDefinedSubComponent(SubComponent $subComponent, string $location, int $position): array
    {
        return [
            "Type"     => "subcomponent",
            "Name"     => "UNKNOWN.$position",
            "LocName"  => "$location.$position",
            "LongName" => "",
            "Datatype" => "UNKNOWN",
            "hasError" => false,
            "comments" => "",
            "value"    => $subComponent->getValue(),
        ];
    }

}
