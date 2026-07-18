<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\Component;
use HL7v2\Model\SubComponent;

use HL7v2\Profile\Profile;
use HL7v2\Profile\HL7Tables;

use HL7v2\Serializer\HL7StringSerializer;
use HL7v2\Validation\ValidationContext;
use HL7v2\Validation\ValidationResult;

use Psr\Log\LoggerInterface;

class Validator
{
    private bool $debug = false;

    private ?LoggerInterface $logger = null;
    private HL7StringSerializer $serializer;

    private Message $message;
    private Profile $profile;
    private ValidationContext $context;
    private ValidationResult $validationResult;

    /**
     * HL7 tables indexed by table number.
     *
     * @var array<string, mixed>
     */
    private array $hl7Tables = [];

    /**
     * Profiled message representation.
     * Legacy equivalent of msgData.
     *
     * @var array<string, mixed>
     */
    private array $profiledMessage = [];


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
     * Get profiled message.
     *
     * @return array<string, mixed>
     */
    public function getProfiledMessage(): array
    {
        return $this->profiledMessage;
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
        $this->profile = $profile;
        $this->hl7Tables = $tables->getTables();

        $this->validationResult = new ValidationResult();
        $this->profiledMessage = [];

        $this->message = $message;

        $messageSegmentNames = $message->getSegmentNames();
        $this->context = new ValidationContext();
        $this->context->profileSegmentNames = $profile->getSegmentNames();
        $this->context->notDefinedSegments = array_values(
            array_diff(
                $messageSegmentNames,
                $this->context->profileSegmentNames
            )
        );

        $this->context->notPresentSegments = array_values(
            array_diff(
                $this->context->profileSegmentNames,
                $messageSegmentNames
            )
        );

        // Create root group definition.
        $rootGroupDef = [
            'Type'     => 'group',
            'Name'     => $this->message->getStructure(),
            'Usage'    => 'R',
            'Min'      => '1',
            'Max'      => '1',
            'LongName' => $this->message->getStructure(),
            'segments' => $this->profile->getDefinition(),
        ];

        $this->log("-Validator- Validation begin.");

        $this->log("-Validator- Segment names in profile: " . implode(", ", $this->context->profileSegmentNames));
        $this->log("-Validator- Segment names in message: " . implode(", ", $messageSegmentNames));
        $this->log("-Validator- Not defined segments: " . implode(", ", $this->context->notDefinedSegments));
        $this->log("-Validator- Not present segments: " . implode(", ", $this->context->notPresentSegments));

        // Validate root group
        $profiledMessage = $this->validateGroup(
            $rootGroupDef,
            $this->message->getStructure()
        );

        if (!isset($profiledMessage[0])) {
            throw new \LogicException(
                'Expected root profiled message.'
            );
        }

        $this->profiledMessage = $profiledMessage[0];

        // Process remaining message segments
        if ($this->context->messageSegmentIndex < $this->message->countSegments()) {
            $this->log("-Validator- There are remaining message segments.");

            while ($this->context->messageSegmentIndex < $this->message->countSegments()) {
                // Current message segment.
                $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                // Current message segment name.
                if ($segment === null) {
                    throw new \LogicException('Expected segment.');
                }

                $segmentName = $segment->getName();

                if (in_array($segmentName, $this->context->notDefinedSegments, true)) {
                    // not defined segment
                    $description = "Segment '{$segmentName}' is not defined in the message profile.";
                    $this->log("-Segment- >> message '{$segmentName}' segment is not defined.");

                    $this->validationResult->addTestReport([
                        'Location'    => $this->message->getStructure(),
                        'Description' => $description,
                        'Type'        => "Structure",
                        'Result'      => false,
                    ]);

                    $this->validationResult->addValidationReport([
                        "type"            => "Segment",
                        "name"            => $segmentName,
                        "longName"        => "Not defined segment",
                        "usage"           => "",
                        "card"            => "",
                        "elementExists"   => true,
                        "elementError"    => true,
                        "elementReps"     => 1,
                        "elementComments" => $description,
                    ]);

                    $this->profiledMessage["segments"][] = $this->createNotDefinedSegment($segment);

                } else {
                    // segment is not expected here
                    $segmentDef = $this->profile->findSegmentDefinition($segmentName);

                    if ($segmentDef === []) {
                        throw new \LogicException(
                            "Segment definition '{$segmentName}' not found."
                        );
                    }

                    $description = "Segment '{$segmentName}' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";
                    $this->log("-Segment- >> message '{$segmentName}' segment is not expected here.");

                    $this->validationResult->addTestReport([
                        'Location'    => $this->message->getStructure(),
                        'Description' => $description,
                        'Type'        => "Structure",
                        'Result'      => false,
                    ]);

                    $this->validationResult->addValidationReport([
                        "type"            => "Segment",
                        "name"            => $segmentName,
                        "longName"        => $segmentDef["LongName"],
                        "usage"           => $segmentDef["Usage"],
                        "card"            => "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]",
                        "elementExists"   => true,
                        "elementError"    => true,
                        "elementReps"     => 1,
                        "elementComments" => $description,
                    ]);

                    $this->profiledMessage["segments"][] = $this->validateNotExpectedSegment($segment, $segmentDef, $this->message->getStructure());

                }

                $this->context->messageSegmentIndex++;

            }
        }

        $this->log("-Validator- Validation end.");

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
     * Validates the group against its profile definition.
     * updates validation reports,
     * and returns its profiled representation.
     *
     * This method primarily acts as a navigation engine
     * synchronizing Profile and Message structures.
     *
     * It is based on five navigation states:
     * - Group not found
     * - Segment not defined
     * - Segment not expected
     * - Segment appears later
     * - Segment matched
     *
     * @param array<string, mixed> $groupDef
     * @param string $location
     *
     * @return list<array<string, mixed>>
     */
    private function validateGroup(array $groupDef, string $location): array
    {
        $groupArray = [];

        // ---
        // Group state
        // ---

        // Segments defined in the group.
        $segmentsInGroup = $this->profile->getSegmentNamesInGroup($groupDef);

        // First segment names in group hierarchy.
        $firstSegmentsInGroup = $this->profile->getFirstSegmentNamesInGroup($groupDef);
        $firstSegmentNameInGroup = $firstSegmentsInGroup[0];

        // Current message segment.
        $currentSegment = $this->message->getSegment($this->context->messageSegmentIndex);

        // Current message segment name.
        $currentSegmentName = $currentSegment?->getName() ?? '';

        // Group existence.
        $isGroupExists = $this->context->isGroupExists($this->message, $firstSegmentNameInGroup);

        // Group repetitions.
        $groupRepetitions = $groupDef['Name'] === $this->message->getStructure()
            ? 1
            : $this->context->countGroupRepetitions($this->message, $firstSegmentNameInGroup, $segmentsInGroup);

        if ($groupRepetitions === 0) {
            $isGroupExists = false;
        }

        // Repeating group flag.
        $isGroupRepeating = $groupRepetitions > 1;

        if ($isGroupRepeating) {
            $this->context->parentGroupFirstSegments[] = $firstSegmentNameInGroup;
        }

        // Navigation state.
        /** @var NavigationState|null $navigationState */
        $navigationState = null;

        $this->log("-Group- --- Group '{$groupDef["Name"]}' begin.");
        $this->log("-Group- Group '".$groupDef["Name"]."' " . (($groupDef["Name"] === $this->message->getStructure()) ? "is" : "is not") . " the root group.");
        $this->log("-Group- First segment name in group: ".$firstSegmentNameInGroup);
        $this->log("-Group- First segment names in group hierarchy: " . (implode(", ", $firstSegmentsInGroup)));
        $this->log("-Group- Segments defined in the group: " . (implode(", ", $segmentsInGroup)));
        $this->log("-Group- Segments in message: " . (implode(", ", $this->message->getSegmentNames())));

        $this->log("-Group- isGroupExists: " . ($isGroupExists ? "true" : "false"));
        $this->log("-Group- groupRepetitions: $groupRepetitions");
        $this->log("-Group- isGroupRepeating: " . ($isGroupRepeating ? "true" : "false"));

        $this->log("-Group- First segment names of repeating parent groups: " . (implode(", ", $this->context->parentGroupFirstSegments)));
        $this->log("-Group- Segment found: {$currentSegmentName} - location: {$this->context->messageSegmentIndex}");


        // ---
        // Group validation
        // ---

        $groupError = false;
        $groupComments = '';

        // Usage.
        $usage = $this->checkUsage(
            $groupDef['Usage'],
            $isGroupExists,
            'Group',
            "'{$groupDef["Name"]}'"
        );

        if (!$usage['result']) {
            $groupError = true;
            $groupComments .= $usage['description'] . " ";
        }

        // Cardinality.
        $cardinality = $this->checkCardinality(
            $groupDef['Min'],
            $groupDef['Max'],
            $groupRepetitions,
            $groupDef['Usage'],
            'Group',
            "'{$groupDef["Name"]}'"
        );

        if (!$cardinality['result']) {
            $groupError = true;
        }
        $groupComments .= $cardinality['description'] . " ";


        // ---
        // Navigation state resolution
        // ---

        if (!$isGroupExists) {
            // The group does not exist in message.
            $navigationState = NavigationState::GROUP_NOT_FOUND;

        } elseif (in_array($currentSegmentName, $this->context->notDefinedSegments, true)) {
            // The group exists but message segment is not defined.
            $navigationState = NavigationState::SEGMENT_NOT_DEFINED;

        } elseif ($currentSegmentName !== $firstSegmentNameInGroup && !in_array($currentSegmentName, $this->context->parentGroupFirstSegments, true)) {
            // The group exists but message segment is not the first segment of the group or of the parent group.
            $navigationState = NavigationState::SEGMENT_NOT_EXPECTED;

        } else {
            // The group exists and message segment is the first segment of the group or of the parent group.
            $navigationState = NavigationState::SEGMENT_MATCHED;
        }

        $this->log("-Group- Navigation state: {$navigationState->name}");


        // ---
        // Navigation state handling
        // ---

        switch ($navigationState) {

            case NavigationState::GROUP_NOT_FOUND:
                // Move on and return.
                $this->log("-Group- Group '{$groupDef['Name']}' not found in message. Move on.");
                $this->log("-Group- --- Group '{$groupDef["Name"]}' end");

                $this->validationResult->addTestReport([
                    'Location'    => $this->message->getStructure(),
                    'Description' => $usage['description'],
                    'Type'        => $usage['type'],
                    'Result'      => $usage['result'],
                ]);

                $this->validationResult->addTestReport([
                    'Location'    => $this->message->getStructure(),
                    'Description' => $cardinality['description'],
                    'Type'        => $cardinality['type'],
                    'Result'      => $cardinality['result'],
                ]);

                $this->validationResult->addValidationReport([
                    "type"            => "Group",
                    "name"            => "---",
                    "longName"        => "--- {$groupDef["Name"]} begin",
                    "usage"           => $groupDef["Usage"],
                    "card"            => "[" . $groupDef["Min"] . ".." . $groupDef["Max"] . "]",
                    "elementExists"   => $isGroupExists,
                    "elementError"    => $groupError,
                    "elementReps"     => $groupRepetitions,
                    "elementComments" => trim($groupComments),
                ]);

                $this->validationResult->addValidationReport([
                    "type"            => "Group",
                    "name"            => "---",
                    "longName"        => "--- {$groupDef["Name"]} end",
                    "usage"           => $groupDef["Usage"],
                    "card"            => "[" . $groupDef["Min"] . ".." . $groupDef["Max"] . "]",
                    "elementExists"   => $isGroupExists,
                    "elementError"    => $groupError,
                    "elementReps"     => "",
                    "elementComments" => "",
                ]);

                // Profile moves on. Message does not move.
                $this->context->profileSegmentIndex += count($segmentsInGroup);

                return $groupArray;

            case NavigationState::SEGMENT_NOT_DEFINED:
                // Move back and return.
                $this->log("-Group- Group '{$groupDef['Name']}' found in message.");
                $this->log("-Group- Segment '{$currentSegmentName}' is not defined. Move back.");
                $this->log("-Group- --- Group '{$groupDef["Name"]}' end");

                $segmentRepetitions = $this->message->countSegmentRepetitions($currentSegmentName, $this->context->messageSegmentIndex);
                $description = "Segment '{$currentSegmentName}' is not defined in the message profile. Found {$segmentRepetitions} time(s).";

                $this->validationResult->addTestReport([
                    'Location'    => $this->message->getStructure(),
                    'Description' => $description,
                    'Type'        => "Structure",
                    'Result'      => false,
                ]);

                $this->validationResult->addValidationReport([
                    "type"            => "Segment",
                    "name"            => $currentSegmentName,
                    "longName"        => "Not defined segment",
                    "usage"           => "",
                    "card"            => "",
                    "elementExists"   => true,
                    "elementError"    => true,
                    "elementReps"     => $segmentRepetitions,
                    "elementComments" => $description,
                ]);

                // Create not defined segments and move message on.
                for ($i = 0; $i < $segmentRepetitions; $i++) {
                    $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                    if ($segment === null) {
                        throw new \LogicException('Expected segment repetition.');
                    }

                    $groupArray[] = $this->createNotDefinedSegment($segment);
                    $this->context->messageSegmentIndex++; // Move message on.
                }

                // Move back.
                $this->context->moveBack = true;

                return $groupArray;

            case NavigationState::SEGMENT_NOT_EXPECTED:
                // Move back and return.
                $this->log("-Group- Group '{$groupDef['Name']}' found in message.");
                $this->log("-Group- Segment '{$currentSegmentName}' exists in profile but is not expected here. Move back.");
                $this->log("-Group- --- Group '{$groupDef["Name"]}' end");

                $segmentRepetitions = $this->message->countSegmentRepetitions($currentSegmentName, $this->context->messageSegmentIndex);
                $segmentDef = $this->profile->findSegmentDefinition($currentSegmentName);

                if ($segmentDef === []) {
                    throw new \LogicException("Segment definition '{$currentSegmentName}' not found.");
                }

                $description = "Segment '{$currentSegmentName}' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure. Found {$segmentRepetitions} time(s).";

                $this->validationResult->addTestReport([
                    'Location'    => $this->message->getStructure(),
                    'Description' => $description,
                    'Type'        => "Structure",
                    'Result'      => false,
                ]);

                $this->validationResult->addValidationReport([
                    "type"            => "Segment",
                    "name"            => $currentSegmentName,
                    "longName"        => $segmentDef["LongName"],
                    "usage"           => $segmentDef["Usage"],
                    "card"            => "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]",
                    "elementExists"   => true,
                    "elementError"    => true,
                    "elementReps"     => $segmentRepetitions,
                    "elementComments" => $description,
                ]);

                // Create not expected segments and move message on.
                for ($i = 0; $i < $segmentRepetitions; $i++) {
                    $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                    if ($segment === null) {
                        throw new \LogicException('Expected segment repetition.');
                    }

                    $groupArray[] = $this->validateNotExpectedSegment($segment, $segmentDef, $location);
                    $this->context->messageSegmentIndex++; // Move message on.
                }

                // Move back.
                $this->context->moveBack = true;

                return $groupArray;

            case NavigationState::SEGMENT_MATCHED:
                // Continue validation
                $this->log("-Group- Group '{$groupDef['Name']}' found in message.");

                if ($groupDef["Name"] !== $this->message->getStructure()) {
                    $this->validationResult->addTestReport([
                        'Location'    => $this->message->getStructure(),
                        'Description' => $usage['description'],
                        'Type'        => $usage['type'],
                        'Result'      => $usage['result'],
                    ]);

                    $this->validationResult->addTestReport([
                        'Location'    => $this->message->getStructure(),
                        'Description' => $cardinality['description'],
                        'Type'        => $cardinality['type'],
                        'Result'      => $cardinality['result'],
                    ]);
                }

                break;

            default:
                throw new \LogicException('Unsupported navigation state.');
        }

        // ---
        // Group repetitions
        // ---

        $profileSegmentIndex = $this->context->profileSegmentIndex;

        // Current group repetitions

        for ($groupRep = 1; $groupRep <= $groupRepetitions; $groupRep++) {
            $this->context->profileSegmentIndex = $profileSegmentIndex;

            $this->log("-Group- group '{$groupDef['Name']}' rep $groupRep/$groupRepetitions");
            if ($groupDef["Name"] !== $this->message->getStructure()) {
                $this->validationResult->addValidationReport([
                    "type"            => "Group",
                    "name"            => "---",
                    "longName"        => "--- {$groupDef["Name"]} begin" . (($groupRepetitions > 1) ? " (Rep. $groupRep/$groupRepetitions)" : ""),
                    "usage"           => $groupDef["Usage"],
                    "card"            => "[" . $groupDef["Min"] . ".." . $groupDef["Max"] . "]",
                    "elementExists"   => $isGroupExists,
                    "elementError"    => $groupError,
                    "elementReps"     => $groupRepetitions,
                    "elementComments" => trim($groupComments),
                ]);
            }

            // Create group structure
            $group = [
                "Type"     => $groupDef["Type"],
                "Name"     => $groupDef["Name"],
                "LongName" => $groupDef["LongName"],
                "segments" => [],
            ];

            // ---
            // Group children
            // ---

            $childCount = count($groupDef['segments']);

            for ($childIndex = 0; $childIndex < $childCount; $childIndex++) {

                $childDef = $groupDef['segments'][$childIndex];

                if ($childDef['Type'] === 'segment') {

                    // Segment.
                    $segment = $this->message->getSegment($this->context->messageSegmentIndex);
                    $segmentName = $segment?->getName() ?? '';
                    $this->log("-Segment- Profile segment: {$childDef["Name"]} (location: {$this->context->profileSegmentIndex}). Message segment: {$segmentName} (location: {$this->context->messageSegmentIndex})");

                    if ($childDef["Name"] === $segmentName) {
                        // SEGMENT_MATCHED
                        $this->log("-Segment- >> Profile segment found. Validate '{$segmentName}' segment. Move on");

                        $segmentComments = "";
                        $segmentError = false;
                        $segmentReps = 1;

                        // if segment is not the first segment of the group
                        if ($childIndex > 0) {
                            $segmentReps = $this->message->countSegmentRepetitions($segmentName, $this->context->messageSegmentIndex);
                        }

                        // check usage
                        $segmentUsage = $this->checkUsage(
                            $childDef['Usage'],
                            true,
                            'Segment',
                            "'{$childDef["Name"]}'"
                        );

                        if (!$segmentUsage['result']) {
                            $segmentError = true;
                            $segmentComments .= $segmentUsage['description'] . " ";
                        }

                        // check cardinality
                        $segmentCardinality = $this->checkCardinality(
                            $childDef['Min'],
                            $childDef['Max'],
                            $segmentReps,
                            $childDef['Usage'],
                            'Segment',
                             "'{$childDef["Name"]}'"
                        );

                        if (!$segmentCardinality['result']) {
                            $segmentError = true;
                        }
                        $segmentComments .= $segmentCardinality['description'] . " ";


                        // addTestReport
                        $this->validationResult->addTestReport([
                            'Location'    => $this->message->getStructure(),
                            'Description' => $segmentUsage['description'],
                            'Type'        => $segmentUsage['type'],
                            'Result'      => $segmentUsage['result'],
                        ]);

                        $this->validationResult->addTestReport([
                            'Location'    => $this->message->getStructure(),
                            'Description' => $segmentCardinality['description'],
                            'Type'        => $segmentCardinality['type'],
                            'Result'      => $segmentCardinality['result'],
                        ]);

                        // addValidationReport
                        $this->validationResult->addValidationReport([
                            "type"            => "Segment",
                            "name"            => $childDef["Name"],
                            "longName"        => $childDef["LongName"],
                            "usage"           => $childDef["Usage"],
                            "card"            => "[" . $childDef["Min"] . ".." . $childDef["Max"] . "]",
                            "elementExists"   => true,
                            "elementError"    => $segmentError,
                            "elementReps"     => $segmentReps,
                            "elementComments" => trim($segmentComments),
                        ]);

                        // Validate segment
                        for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                            $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                            if ($segment === null) {
                                throw new \LogicException('Expected segment repetition.');
                            }

                            $segmentArray = $this->validateSegment($segment, $childDef, $childDef["Name"]);
                            $segmentArray["hasError"] = $segmentError;
                            $segmentArray["comments"] = trim($segmentComments);
                            $group["segments"][] = $segmentArray;
                            $this->context->messageSegmentIndex++; // Message: Move on
                        }

                        // Profile: Move On.
                        $this->context->profileSegmentIndex++;

                    } else {
                        $this->log("-Segment- >> Profile segment not found.");
                        $segmentComments = "";
                        $segmentError = false;

                        // check usage
                        $segmentUsage = $this->checkUsage(
                            $childDef['Usage'],
                            false,
                            'Segment',
                            "'{$childDef["Name"]}'"
                        );

                        if (!$segmentUsage['result']) {
                            $segmentError = true;
                            $segmentComments .= $segmentUsage['description'] . " ";
                        }

                        // check cardinality
                        $segmentCardinality = $this->checkCardinality(
                            $childDef['Min'],
                            $childDef['Max'],
                            0,
                            $childDef['Usage'],
                            'Segment',
                             "'{$childDef["Name"]}'"
                        );

                        if (!$segmentCardinality['result']) {
                            $segmentError = true;
                        }
                        $segmentComments .= $segmentCardinality['description'] . " ";

                        if (in_array($childDef["Name"], $this->context->notPresentSegments, true)) {
                            // Case 1: profile segment name is not present in message. Move On.
                            $this->log("-Segment- >> profile segment '{$childDef["Name"]}' is not present in message (Case 1). Move on.");

                            $this->validationResult->addTestReport([
                                'Location'    => $this->message->getStructure(),
                                'Description' => $segmentUsage['description'],
                                'Type'        => $segmentUsage['type'],
                                'Result'      => $segmentUsage['result'],
                            ]);

                            $this->validationResult->addTestReport([
                                'Location'    => $this->message->getStructure(),
                                'Description' => $segmentCardinality['description'],
                                'Type'        => $segmentCardinality['type'],
                                'Result'      => $segmentCardinality['result'],
                            ]);

                            $this->validationResult->addValidationReport([
                                "type"            => "Segment",
                                "name"            => $childDef["Name"],
                                "longName"        => $childDef["LongName"],
                                "usage"           => $childDef["Usage"],
                                "card"            => "[" . $childDef["Min"] . ".." . $childDef["Max"] . "]",
                                "elementExists"   => true,
                                "elementError"    => $segmentError,
                                "elementReps"     => 0,
                                "elementComments" => trim($segmentComments),
                            ]);

                            // Profile: Move On.
                            $this->context->profileSegmentIndex++;

                        } elseif (in_array($segmentName, $this->context->notDefinedSegments, true)) {
                            // Case 2: message segment is not defined. Message: move on. Profile: move back.
                            $this->log("-Segment- >> message segment '{$segmentName}' is not defined (Case 2). Move back.");
                            $segmentReps = $this->message->countSegmentRepetitions($segmentName, $this->context->messageSegmentIndex);
                            $description = "Segment '{$segmentName}' is not defined in the message profile. Found {$segmentReps} time(s).";

                            $this->validationResult->addTestReport([
                                'Location'    => $this->message->getStructure(),
                                'Description' => $description,
                                'Type'        => "Structure",
                                'Result'      => false,
                            ]);

                            $this->validationResult->addValidationReport([
                                "type"            => "Segment",
                                "name"            => $segmentName,
                                "longName"        => "Not defined segment",
                                "usage"           => "",
                                "card"            => "",
                                "elementExists"   => true,
                                "elementError"    => true,
                                "elementReps"     => $segmentReps,
                                "elementComments" => trim($description),
                            ]);

                            for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                                $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                                if ($segment === null) {
                                    throw new \LogicException('Expected segment repetition.');
                                }

                                $group["segments"][] = $this->createNotDefinedSegment($segment);
                                $this->context->messageSegmentIndex++; // Message: Move on
                            }

                            // Move back.
                            $this->context->moveBack = true;

                        } else {
                            // Case 3: message segment exists in profile but is not expected here
                            $this->log("-Segment- >> message '{$segmentName}' segment exists in profile but is not expected here (Case 3).");

                            $nextSegmentsOfTheGroup = array_slice($segmentsInGroup, $childIndex + 1);
                            $this->log("-Segment- >> next segments in group: " . (implode(", ", $nextSegmentsOfTheGroup)));

                            if (in_array($segmentName, $nextSegmentsOfTheGroup, true)) {
                                // a. message segment name appears later in the group. Move on.
                                $case = NavigationState::SEGMENT_APPEARS_LATER;
                                $this->log("-Segment- >> message '{$segmentName}' segment appears later in the group (a). Move on.");

                            } elseif (in_array($segmentName, $segmentsInGroup, true) && !$isGroupRepeating) {
                                // b. message segment name is in the group but is not expected here. Move back.
                                $case = NavigationState::SEGMENT_NOT_EXPECTED;
                                $this->log("-Segment- >> message '{$segmentName}' segment is in the group but is not expected here (b). Move back.");

                            } elseif (in_array($segmentName, $segmentsInGroup, true)) {
                                // c. message segment name appears later in a repetition of the group. Move on.
                                $case = NavigationState::SEGMENT_APPEARS_LATER;
                                $this->log("-Segment- >> message '{$segmentName}' segment appears later in a repetition of the group (c). Move on.");

                            } elseif (in_array($segmentName, $this->context->parentGroupFirstSegments, true)) {
                                // d. message segment name appears in a repetition of the parent group. Move on.
                                $case = NavigationState::SEGMENT_APPEARS_LATER;
                                $this->log("-Segment- >> message '{$segmentName}' segment appears in a repetition of the parent group (d). Move on.");

                            } elseif ($this->context->isSegmentLaterInProfileStructure($segmentName)) {
                                // e. message segment name appears later in the profile. Move on.
                                $case = NavigationState::SEGMENT_APPEARS_LATER;
                                $this->log("-Segment- >> message '{$segmentName}' segment appears later in the profile (e). Move on.");

                            } elseif ($segmentName != "") {
                                // f. segment exists in profile but is not expected here
                                $case = NavigationState::SEGMENT_NOT_EXPECTED;
                                $this->log("-Segment- >> message '{$segmentName}' segment exists in profile but is not expected here (f). Move back.");

                            } else {
                                // g. end of message
                                $case = NavigationState::SEGMENT_APPEARS_LATER;
                                $this->log("-Segment- >> End of msgParse (g). childIndex: {$childIndex}. Profile segment index: {$this->context->profileSegmentIndex}. Segment name: '$segmentName' ({$this->context->messageSegmentIndex}). Move on.");

                            }

                            switch ($case) {

                                case NavigationState::SEGMENT_APPEARS_LATER:
                                    // Message: doesn't move. Profile: move on.
                                    $this->validationResult->addTestReport([
                                        'Location'    => $this->message->getStructure(),
                                        'Description' => $segmentUsage['description'],
                                        'Type'        => $segmentUsage['type'],
                                        'Result'      => $segmentUsage['result'],
                                    ]);

                                    $this->validationResult->addTestReport([
                                        'Location'    => $this->message->getStructure(),
                                        'Description' => $segmentCardinality['description'],
                                        'Type'        => $segmentCardinality['type'],
                                        'Result'      => $segmentCardinality['result'],
                                    ]);

                                    $this->validationResult->addValidationReport([
                                        "type"            => "Segment",
                                        "name"            => $childDef["Name"],
                                        "longName"        => $childDef["LongName"],
                                        "usage"           => $childDef["Usage"],
                                        "card"            => "[" . $childDef["Min"] . ".." . $childDef["Max"] . "]",
                                        "elementExists"   => false,
                                        "elementError"    => $segmentError,
                                        "elementReps"     => 0,
                                        "elementComments" => trim($segmentComments),
                                    ]);

                                    // Profile: Move On.
                                    $this->context->profileSegmentIndex++;

                                    break;

                                case NavigationState::SEGMENT_NOT_EXPECTED:
                                    // Message: move on. Profile: move back.

                                    $segmentReps = $this->message->countSegmentRepetitions($segmentName, $this->context->messageSegmentIndex);
                                    $segmentDef  = $this->profile->findSegmentDefinition($segmentName);

                                    if ($segmentDef === []) {
                                        throw new \LogicException("Segment definition '{$segmentName}' not found.");
                                    }

                                    $description = "Segment '{$segmentName}' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure. Found {$segmentReps} time(s).";

                                    $this->validationResult->addTestReport([
                                        'Location'    => $this->message->getStructure(),
                                        'Description' => $description,
                                        'Type'        => "Structure",
                                        'Result'      => false,
                                    ]);

                                    $this->validationResult->addValidationReport([
                                        "type"            => "Segment",
                                        "name"            => $segmentName,
                                        "longName"        => $segmentDef["LongName"],
                                        "usage"           => $segmentDef["Usage"],
                                        "card"            => "[" . $segmentDef["Min"] . ".." . $segmentDef["Max"] . "]",
                                        "elementExists"   => true,
                                        "elementError"    => true,
                                        "elementReps"     => $segmentReps,
                                        "elementComments" => trim($description),
                                    ]);

                                    for ($cnt = 0; $cnt < $segmentReps; $cnt++) {
                                        $segment = $this->message->getSegment($this->context->messageSegmentIndex);

                                        if ($segment === null) {
                                            throw new \LogicException('Expected segment repetition.');
                                        }

                                        $group["segments"][] = $this->validateNotExpectedSegment($segment, $segmentDef, $location);
                                        $this->context->messageSegmentIndex++; // Message: Move on
                                    }

                                    // Move back.
                                    $this->context->moveBack = true;

                                    break;
                            }

                        }
                    }

                } elseif ($childDef['Type'] === 'group') {

                    // Nested group.

                    $data = $this->validateGroup($childDef, $location);
                    foreach ($data as $occurrence) {
                        $group["segments"][] = $occurrence;
                    }

                }

                // Move back - Reprocess current profile child.
                if ($this->context->moveBack) {
                    $childIndex--;
                    $this->context->moveBack = false;
                }
            }


            $groupArray[] = $group;

            if ($groupDef["Name"] !== $this->message->getStructure()) {
                $this->validationResult->addValidationReport([
                    "type"            => "Group",
                    "name"            => "---",
                    "longName"        => "--- {$groupDef["Name"]} end",
                    "usage"           => "",
                    "card"            => "",
                    "elementExists"   => $isGroupExists,
                    "elementError"    => $groupError,
                    "elementReps"     => "",
                    "elementComments" => "",
                ]);
            }
        }


        // ---
        // Group repetitions cleanup
        // ---

        if ($isGroupRepeating) {
            array_pop($this->context->parentGroupFirstSegments);
        }


        $this->log("-Group- --- Group '{$groupDef["Name"]}' end");
        return $groupArray;
    }

    /**
     * Validate a segment that is defined in the profile
     * but not expected at the current position.
     *
     * Validates the segment against its profile definition,
     * and returns the profiled representation of the segment.
     *
     * The resulting profiled representation is flagged as
     * invalid due to the segment positioning error.
     *
     * @param Segment $segment
     * @param array<string, mixed> $segmentDef
     * @param string $location
     *
     * @return array<string, mixed>
     */
    private function validateNotExpectedSegment(Segment $segment, array $segmentDef, string $location): array
    {
        $segmentArray = $this->validateSegment(
            $segment,
            $segmentDef,
            $location
        );

        $segmentArray['hasError'] = true;
        $segmentArray['comments'] = "Segment '" . $segment->getName() . "' is defined in the message profile, but error in position (sequence) within the hierarchy of the message structure.";

        return $segmentArray;
    }

    /**
     * Validate segment.
     *
     * Validates the segment against its profile definition,
     * updates validation reports,
     * and returns the profiled representation of the segment.
     *
     * TODO:
     * Refactor after complete Validator migration.
     *
     * @param Segment|null $segment
     * @param array<string, mixed> $segmentDef
     * @param string $location
     *
     * @return array<string, mixed>
     */
    private function validateSegment(?Segment $segment, array $segmentDef, string $location): array
    {
        $elementName = $segmentDef["Name"];

        // Create segment structure
        $segmentArray = [
            "Type"     => $segmentDef["Type"],
            "Name"     => $segmentDef["Name"],
            "LongName" => $segmentDef["LongName"],
            "hasError" => "",
            "comments" => "",
            "fields"   => [],
        ];

        foreach ($segmentDef['fields'] as $i => $fieldDef) {
            $field = $segment !== null
                ? $segment->getField($i + 1)
                : null;

            $profiledField = $this->validateField(
                $field,
                $fieldDef,
                "{$location}-" . ($i + 1)
            );
            //
            // Legacy validator adds the field only when the profiled
            // representation is not empty (count($data) > 0).
            // Current implementation always returns a profiled field.
            //
            // TODO:
            // Decide whether to preserve this behaviour or always include
            // profile-defined fields in the profiled message.
            if (count($profiledField) > 0) {
                $segmentArray['fields'][$i + 1] = $profiledField;
            }
        }

        // Check if there are more fields in message Segment
        if ($segment !== null && $segment->countFields() > count($segmentDef['fields'])) {
            $this->log(
                "-Segment- There are more fields in segment '{$elementName}'."
            );

            for ($i = count($segmentDef["fields"]); $i < $segment->countFields(); $i++) {
                $field = $segment->getField($i + 1);

                if ($field === null) {
                    throw new \LogicException('Unexpected null field.');
                }

                $fieldPosition = $i + 1;
                $fieldLocation = "{$elementName}-{$fieldPosition}";
                $description = "Field '{$fieldLocation}' is not expected in segment '$elementName' structure.";

                $notDefinedField = $this->createNotDefinedField($field, $location, $fieldPosition);
                foreach ($notDefinedField as &$repeat) {
                    $repeat["hasError"] = true;
                    $repeat["comments"] = $description;
                }
                unset($repeat);

                $segmentArray["fields"][$i + 1] = $notDefinedField;

                $this->log(
                    "-Field- {$fieldLocation} : {$description} Found {$field->countRepeats()} rep(s)."
                );

                $this->validationResult->addTestReport([
                    'Location'    => $fieldLocation,
                    'Description' => $description,
                    'Type'        => "Element not expected",
                    'Result'      => false,
                ]);

                $this->validationResult->addValidationReport([
                    "type"            => "Field",
                    "location"        => $fieldLocation,
                    "name"            => "{$elementName}.{$fieldPosition}",
                    "longName"        => "Not defined field",
                    "usage"           => "",
                    "card"            => "",
                    "datatype"        => "UNKNOWN",
                    "length"          => "",
                    "itemNo"          => "",
                    "table"           => "",
                    "reference"       => "",
                    "impNote"         => "",
                    "elementValue"    => $this->serializer->serializeField($field, $this->message),
                    "elementExists"   => true,
                    "elementError"    => true,
                    "elementComments" => trim($description),
                ]);
            }
        }

        return $segmentArray;
    }

    /**
     * Validate field.
     *
     * Validates the field against its profile definition,
     * updates validation reports,
     * and returns the profiled representation of the field.
     *
     * TODO:
     * Refactor after complete Validator migration.
     *
     * @param Field|null $field
     * @param array<string, mixed> $fieldDef
     * @param string $location
     *
     * @return array<int, array<string, mixed>>
     */
    private function validateField(?Field $field, array $fieldDef, string $location): array
    {
        $value = $field !== null
            ? $this->serializer->serializeField($field, $this->message)
            : '';
        $repeats = $field !== null && $value !== ''
            ? $field->countRepeats()
            : 0;
        $exists = (
            $field !== null
            && $value !== ''
        );
        $elementName = "'{$fieldDef['LongName']}' ({$fieldDef['Name']})";
        $hasError = false;
        $comments = '';
        $fieldArray = [];

        $this->log(
            "-Field- $location : field exists: "
            . ($exists ? 'true' : 'false')
            . " ({$fieldDef['Usage']}) "
            . " - repeats: {$repeats}"
        );

        // check usage
        $usage = $this->checkUsage(
            $fieldDef['Usage'],
            $exists,
            'Field',
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


        // check cardinality
        $cardinality = $this->checkCardinality(
            $fieldDef['Min'],
            $fieldDef['Max'],
            $repeats,
            $fieldDef['Usage'],
            'Field',
            $elementName
        );

        $this->validationResult->addTestReport([
            'Location'    => $location,
            'Description' => $cardinality['description'],
            'Type'        => $cardinality['type'],
            'Result'      => $cardinality['result'],
        ]);

        if (!$cardinality['result']) {
            $hasError = true;
        }
        $comments .= $cardinality['description'] . " ";

        if ($exists) {
            foreach ($field->getRepeats() as $repeatIndex => $fieldRepeat) {
                $fieldValue = $this->serializer->serializeFieldRepeat(
                    $fieldRepeat,
                    $this->message
                );
                $repeatHasError = $hasError;
                $repeatComments = $comments;

                // check length
                if ($fieldDef["Length"] !== "") {
                    $lengthCheck = $this->checkLength(
                        (int) $fieldDef["Length"],
                        $fieldValue,
                        'Field',
                        $elementName
                    );

                    $this->validationResult->addTestReport([
                        'Location'    => $location,
                        'Description' => $lengthCheck['description'],
                        'Type'        => $lengthCheck['type'],
                        'Result'      => $lengthCheck['result'],
                    ]);

                    if (!$lengthCheck['result']) {
                        $repeatHasError = true;
                        $repeatComments .= $lengthCheck['description'] . " ";
                    }
                }

                // check table - only if has no components
                if (
                    !isset($fieldDef['components'])
                    && $fieldDef['Table'] !== ""
                    && isset($this->hl7Tables[$fieldDef['Table']])
                ) {
                    if (!empty($this->hl7Tables[$fieldDef['Table']]['elements'])) {
                        $tableCheck = $this->checkHL7Table(
                            $fieldDef["Table"],
                            $fieldValue,
                            'Field',
                            $elementName
                        );

                        $this->validationResult->addTestReport([
                            'Location'    => $location,
                            'Description' => $tableCheck['description'],
                            'Type'        => $tableCheck['type'],
                            'Result'      => $tableCheck['result'],
                        ]);

                        if (!$tableCheck['result']) {
                            $repeatHasError = true;
                        }
                        $repeatComments .= $tableCheck['description'] . " ";
                    }
                }

                //
                // Legacy validator did not propagate errors from unexpected child
                // elements to parent Field validation reports.
                //
                // The refactored validator updates the field validation report after
                // child validation so that structural errors detected on unexpected
                // components are also reflected on the parent field.
                //
                $reportIndex = $this->validationResult->addValidationReport([
                    "type"            => "Field",
                    "location"        => $location,
                    "name"            => $fieldDef["Name"] . ( ($repeats > 1) ? " (Rep. " . ($repeatIndex + 1) . ")" : ""),
                    "longName"        => $fieldDef["LongName"],
                    "usage"           => $fieldDef["Usage"],
                    "card"            => "[". $fieldDef["Min"] . ".." . $fieldDef["Max"] . "]",
                    "datatype"        => $fieldDef["Datatype"],
                    "length"          => $fieldDef["Length"],
                    "itemNo"          => $fieldDef["Item"],
                    "table"           => $fieldDef["Table"],
                    "reference"       => $fieldDef["Chapter"],
                    "impNote"         => "",
                    "elementValue"    => $fieldValue,
                    "elementExists"   => $exists,
                    "elementError"    => $repeatHasError,
                    "elementComments" => trim($repeatComments),
                ]);

                // Create field structure
                $repeatArray = [
                    "Type"     => "field",
                    "Name"     => $fieldDef["Name"],
                    "LocName"  => $location,
                    "LongName" => $fieldDef["LongName"],
                    "Datatype" => $fieldDef["Datatype"],
                    "hasError" => $repeatHasError,
                    "comments" => trim($repeatComments),
                    "value"    => $fieldValue,
                ];

                // Validate components
                if (isset($fieldDef["components"])) {
                    $repeatArray["components"] = [];

                    foreach ($fieldDef['components'] as $i => $componentDef) {
                        $component = $fieldRepeat->getComponent($i + 1);
                        $profiledComponent = $this->validateComponent(
                            $component,
                            $componentDef,
                            "{$location}." . ($i + 1)
                        );
                        //
                        // Legacy validator adds the component only when the profiled
                        // representation is not empty (count($data) > 0).
                        //
                        // TODO:
                        // Decide whether to preserve this behaviour or always include
                        // profile-defined components in the profiled message.
                        if (count($profiledComponent) > 0) {
                            $repeatArray['components'][$i + 1] = $profiledComponent;
                        }
                    }

                    // Check if there are more components in message
                    if ($fieldRepeat->countComponents() > count($fieldDef["components"])) {
                        $this->log(
                            "-Field- There are more components in Field '{$location}'."
                        );
                        for ($i = count($fieldDef["components"]); $i < $fieldRepeat->countComponents(); $i++) {
                            $component = $fieldRepeat->getComponent($i + 1);

                            if ($component === null) {
                                throw new \LogicException('Unexpected null component.');
                            }

                            $componentPosition = $i + 1;
                            $componentLocation = "{$location}.{$componentPosition}";

                            $description = "Component '{$componentLocation}' is not expected in Field '$location' structure.";

                            $notDefinedComponent = $this->createNotDefinedComponent($component, $location, $componentPosition);
                            $notDefinedComponent["hasError"] = true;
                            $notDefinedComponent["comments"] = $description;
                            $repeatArray["components"][$i + 1] = $notDefinedComponent;

                            $this->log(
                                "-Component- {$componentLocation} :  {$description}"
                            );

                            $this->validationResult->addTestReport([
                                'Location'    => $componentLocation,
                                'Description' => $description,
                                'Type'        => "Element not expected",
                                'Result'      => false,
                            ]);

                            $repeatHasError = true;
                            $repeatComments .= $description . ' ';

                            $this->validationResult->addValidationReport([
                                "type"            => "Component",
                                "location"        => $componentLocation,
                                "name"            => "UNKNOWN.{$componentPosition}",
                                "longName"        => "Not defined component",
                                "usage"           => "",
                                "datatype"        => "UNKNOWN",
                                "length"          => "",
                                "table"           => "",
                                "impNote"         => "",
                                "elementValue"    => $this->serializer->serializeComponent($component, $this->message),
                                "elementExists"   => true,
                                "elementError"    => true,
                                "elementComments" => trim($description),
                            ]);
                        }
                    }
                } elseif ($fieldRepeat->countComponents() > 1) {
                    // There is no Component in the profile
                    // Check if there are more than one Component in message
                    $this->log(
                        "-Field- Components are not expected in Field '{$location}' structure."
                    );
                    for ($i = 0; $i < $fieldRepeat->countComponents(); $i++) {
                        $component = $fieldRepeat->getComponent($i + 1);

                        if ($component === null) {
                            throw new \LogicException('Unexpected null component.');
                        }

                        $componentPosition = $i + 1;
                        $componentLocation = "{$location}.{$componentPosition}";

                        $description = "Component '{$componentLocation}' is not expected in Field '$location' structure.";

                        $notDefinedComponent = $this->createNotDefinedComponent($component, $location, $componentPosition);
                        $notDefinedComponent["hasError"] = true;
                        $notDefinedComponent["comments"] = $description;
                        $repeatArray["components"][$i + 1] = $notDefinedComponent;

                        $this->log(
                            "-Component- {$componentLocation} :  {$description}"
                        );

                        $this->validationResult->addTestReport([
                            'Location'    => $componentLocation,
                            'Description' => $description,
                            'Type'        => "Element not expected",
                            'Result'      => false,
                        ]);

                        $repeatHasError = true;
                        $repeatComments .= $description . ' ';

                        $this->validationResult->addValidationReport([
                            "type"            => "Component",
                            "location"        => $componentLocation,
                            "name"            => "UNKNOWN.{$componentPosition}",
                            "longName"        => "Not defined component",
                            "usage"           => "",
                            "datatype"        => "UNKNOWN",
                            "length"          => "",
                            "table"           => "",
                            "impNote"         => "",
                            "elementValue"    => $this->serializer->serializeComponent($component, $this->message),
                            "elementExists"   => true,
                            "elementError"    => true,
                            "elementComments" => trim($description),
                        ]);
                    }
                }

                // Update field validation report
                $this->validationResult->updateValidationReport(
                    $reportIndex,
                    [
                        'elementError'    => $repeatHasError,
                        'elementComments' => trim($repeatComments),
                    ]
                );

                $fieldArray[] = $repeatArray;
            }
        } else {
            // Profiled representation rules
            //
            // A field is represented in the profiled message only if it is
            // physically present in the HL7 message.
            //
            // Examples
            //
            // PD1||U||||||||||N
            //
            //     PD1-1 is represented (present, empty)
            //     PD1-2 is represented (value = U)
            //     ...
            //     PD1-12 is represented (value = N)
            //
            //     PD1-13 is not represented
            //     PD1-14 is not represented
            //     PD1-15 is not represented
            //
            // PD1||U||||||||||N|
            //
            //     PD1-13 is represented (present, empty)
            //
            // PD1||U||||||||||N|||
            //
            //     PD1-13 is represented (present, empty)
            //     PD1-14 is represented (present, empty)
            //     PD1-15 is represented (present, empty)
            if ($field !== null && $value === '') {
                $fieldArray[0] = [
                    "Type"     => "field",
                    "Name"     => $fieldDef["Name"],
                    "LocName"  => $location,
                    "LongName" => $fieldDef["LongName"],
                    "Datatype" => $fieldDef["Datatype"],
                    "hasError" => $hasError,
                    "comments" => trim($comments),
                    "value"    => "",
                ];
            }

            $this->validationResult->addValidationReport([
                "type"            => "Field",
                "location"        => $location,
                "name"            => $fieldDef["Name"],
                "longName"        => $fieldDef["LongName"],
                "usage"           => $fieldDef["Usage"],
                "card"            => "[". $fieldDef["Min"] . ".." . $fieldDef["Max"] . "]",
                "datatype"        => $fieldDef["Datatype"],
                "length"          => $fieldDef["Length"],
                "itemNo"          => $fieldDef["Item"],
                "table"           => $fieldDef["Table"],
                "reference"       => $fieldDef["Chapter"],
                "impNote"         => "",
                "elementValue"    => "",
                "elementExists"   => $exists,
                "elementError"    => $hasError,
                "elementComments" => trim($comments),
            ]);
        }

        return $fieldArray;
    }

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
                    }
                    $comments .= $tableCheck['description'] . " ";
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
                    $profiledSubComponent = $this->validateSubComponent(
                        $subComponent,
                        $subComponentDef,
                        "{$location}." . ($i + 1)
                    );
                    //
                    // Legacy validator adds the sub-component only when the profiled
                    // representation is not empty (count($data) > 0).
                    //
                    // TODO:
                    // Decide whether to preserve this behaviour or always include
                    // profile-defined sub-components in the profiled message.
                    if (count($profiledSubComponent) > 0) {
                        $componentArray['subcomponents'][$i + 1] = $profiledSubComponent;
                    }
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

        return $component !== null ? $componentArray : [];
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
                    }
                    $comments .= $tableCheck['description'] . " ";
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

        return $subComponent !== null ? $subComponentArray : [];
    }


    // ---
    // --- Create not defined element functions
    // ---

    /**
     * Create profiled representation of a segment
     * not defined in the profile.
     *
     * @param Segment $segment
     *
     * @return array<string, mixed>
     */
    private function createNotDefinedSegment(Segment $segment): array
    {
        $segmentName = $segment->getName();

        $profiledSegment = [
            "Type"     => "segment",
            "Name"     => "$segmentName",
            "LongName" => "not defined segment",
            "hasError" => true,
            "comments" => "Segment '{$segmentName}' is not defined in the message profile.",
            "fields"   => [],
        ];


        for ($fieldPosition = 1; $fieldPosition <= $segment->countFields(); $fieldPosition++) {
            $field = $segment->getField($fieldPosition);

            if ($field === null) {
                continue;
            }

            $profiledSegment['fields'][$fieldPosition] = $this->createNotDefinedField($field, $segmentName, $fieldPosition);
        }
        return $profiledSegment;
    }

    /**
     * Create profiled representation of a field
     * not defined in the profile.
     *
     * @param Field $field
     * @param string $location
     * @param int $position
     *
     * @return array<int, array<string, mixed>>
     */
    private function createNotDefinedField(Field $field, string $location, int $position): array
    {
        $profiledField = [];

        foreach ($field->getRepeats() as $index => $repeat) {
            $profiledField[$index] = [
                "Type"     => "field",
                "Name"     => "$location.$position",
                "LocName"  => "$location-$position",
                "LongName" => "",
                "Datatype" => "UNKNOWN",
                "hasError" => "",
                "comments" => "",
                "value"    => $this->serializer->serializeFieldRepeat($repeat, $this->message),
            ];

            if ($repeat->countComponents() > 1) {
                $profiledComponents = [];

                foreach ($repeat->getComponents() as $idx => $component) {
                    $profiledComponents[$idx + 1] = $this->createNotDefinedComponent($component, "{$location}-{$position}", $idx + 1);
                }

                $profiledField[$index]["components"] = $profiledComponents;
            }
        }

        return $profiledField;
    }

    /**
     * Create profiled representation of a component
     * not defined in the profile.
     *
     * @param Component $component
     * @param string $location
     * @param int $position
     *
     * @return array<string, mixed>
     */
    private function createNotDefinedComponent(Component $component, string $location, int $position): array
    {
        $profiledComponent = [
            "Type" => "component",
            "Name" => "UNKNOWN.$position",
            "LocName" => "$location.$position",
            "LongName" => "",
            "Datatype" => "UNKNOWN",
            "hasError" => "",
            "comments" => "",
            "value" => $this->serializer->serializeComponent($component, $this->message),
        ];

        if ($component->countSubComponents() > 1) {
            $profiledSubComponents = [];

            foreach ($component->getSubComponents() as $index => $subComponent) {
                $profiledSubComponents[$index + 1] = $this->createNotDefinedSubComponent($subComponent, "$location.$position", $index + 1);
            }

            $profiledComponent["subcomponents"] = $profiledSubComponents;
        }

        return $profiledComponent;
    }

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
