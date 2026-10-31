<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\StructureNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Loader of the normalized profile format (step 7.1, ADR 0004): the real
 * directories of profiles-next/, and forged directories for format errors.
 */
final class ProfileDirectoryTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles-next';

    private ?string $forged = null;

    protected function tearDown(): void
    {
        if ($this->forged !== null) {
            foreach (array_merge(glob("{$this->forged}/structures/*") ?: [], glob("{$this->forged}/*.json") ?: []) as $file) {
                unlink($file);
            }
            @rmdir("{$this->forged}/structures");
            @rmdir($this->forged);
            $this->forged = null;
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function directories(): iterable
    {
        foreach (['2.3', '2.3.1', '2.4', '2.5', '2.5-IHEPAMFR'] as $profileId) {
            yield $profileId => ["json-$profileId"];
        }
    }

    #[DataProvider('directories')]
    public function testEveryMessageProfileOfARealDirectoryLoads(string $directoryName): void
    {
        $directory = ProfileDirectory::open(self::PROFILES . "/$directoryName");

        $files = glob(self::PROFILES . "/$directoryName/structures/*.json") ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            [$code, $event, $structure] = explode('-', basename($file, '.json'), 3);
            $profile = $directory->messageProfile($code, $event, $structure);

            $this->assertSame(basename($file, '.json'), $profile->name);
            $this->assertSame($structure, $profile->root->name);
            $this->assertSame(Usage::Required, $profile->root->usage);
            $this->assertSame('[1..1]', $profile->root->cardinality->toString());
            $this->assertSame('MSH', $profile->root->children[0]->name);
        }
    }

    public function testDirectoryDefinitions(): void
    {
        $directory = ProfileDirectory::open(self::PROFILES . '/json-2.5/');

        $this->assertSame(self::PROFILES . '/json-2.5', $directory->path);
        $this->assertSame('2.5', $directory->hl7Version);
        $this->assertSame('', $directory->base);

        $pid = $directory->segment('PID');
        $this->assertNotNull($pid);
        $pid3 = $pid->field(3);
        $this->assertNotNull($pid3);
        $this->assertSame(3, $pid3->position);
        $this->assertSame(Usage::Required, $pid3->usage);
        $this->assertSame('[1..*]', $pid3->cardinality->toString());
        $this->assertSame('PID.3', $pid3->definition->id);
        $this->assertSame('CX', $pid3->definition->dataType);
        $this->assertSame(250, $pid3->definition->maxLength);
        $this->assertNull($pid3->definition->table);
        $this->assertNull($pid->field(99));
        $this->assertNull($directory->segment('ZZZ'));

        $st = $directory->dataType('ST');
        $this->assertNotNull($st);
        $this->assertTrue($st->isPrimitive());

        $hd = $directory->dataType('HD');
        $this->assertNotNull($hd);
        $this->assertFalse($hd->isPrimitive());
        $hd1 = $hd->component(1);
        $this->assertNotNull($hd1);
        $this->assertSame('HD.1', $hd1->id);
        $this->assertSame('IS', $hd1->dataType);
        $this->assertSame('0300', $hd1->table);
        $this->assertSame(Usage::Conditional, $hd->component(2)?->usage);
    }

    public function testMessageStructureTree(): void
    {
        $profile = ProfileDirectory::open(self::PROFILES . '/json-2.5')->messageProfile('ORU', 'R01', 'ORU_R01');

        $this->assertSame('ORU_R01', $profile->structure);
        $this->assertSame(['MSH', 'SFT', 'PATIENT_RESULT', 'DSC'], self::names($profile->root->children));

        $patientResult = $profile->root->children[2];
        $this->assertInstanceOf(GroupNode::class, $patientResult);
        $this->assertSame('[1..*]', $patientResult->cardinality->toString());
        $this->assertSame(['PATIENT', 'ORDER_OBSERVATION'], self::names($patientResult->children));

        $msh = $profile->root->children[0];
        $this->assertInstanceOf(SegmentNode::class, $msh);
        $this->assertSame('Message Header', $msh->definition->longName);
    }

    public function testFieldOverridesOfTheStructureAreResolved(): void
    {
        $directory = ProfileDirectory::open(self::PROFILES . '/json-2.5-IHEPAMFR');
        $profile = $directory->messageProfile('ADT', 'A28', 'ADT_A05');

        $pid = self::segmentNode($profile->root, 'PID');
        $pid11 = $pid->definition->field(11);
        $this->assertNotNull($pid11);
        $this->assertSame(Usage::RequiredOrEmpty, $pid11->usage);
        $this->assertSame('[0..*]', $pid11->cardinality->toString());
        $this->assertSame('ITI 3.30.5.3', $pid11->source);
        // The override changes usage and cardinality only.
        $this->assertSame($directory->segment('PID')?->field(11)?->definition, $pid11->definition);

        // A segment without override shares the definition of segments.json.
        $this->assertSame($directory->segment('MSH'), self::segmentNode($profile->root, 'MSH')->definition);
        $this->assertNotSame($directory->segment('PID'), $pid->definition);
    }

    public function testStructureResolution(): void
    {
        $directory = ProfileDirectory::open(self::PROFILES . '/json-2.5');

        // From messageType.json when MSH-9.3 is empty.
        $this->assertSame('ADT-A04-ADT_A01', $directory->messageProfile('ADT', 'A04')->name);
        // A single ACK profile, whatever the trigger event.
        $this->assertSame('ACK-ACK-ACK', $directory->messageProfile('ACK', 'A01')->name);
        $this->assertSame('ACK-ACK-ACK', $directory->messageProfile('ACK', 'A01', 'ACK')->name);
    }

    public function testUnknownMessageIsRefused(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Message structure not found for ADT^A99.');

        ProfileDirectory::open(self::PROFILES . '/json-2.5')->messageProfile('ADT', 'A99');
    }

    public function testWrongStructureIsRefused(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Profile not found: ADT-A04-ADT_A04');

        ProfileDirectory::open(self::PROFILES . '/json-2.5')->messageProfile('ADT', 'A04', 'ADT_A04');
    }

    public function testMissingDirectoryIsRefused(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Profile directory not found');

        ProfileDirectory::open(self::PROFILES . '/json-9.9');
    }

    public function testForgedDirectoryLoads(): void
    {
        $profile = ProfileDirectory::open($this->forge())->messageProfile('ADT', 'A01', 'ADT_A01');

        $this->assertSame(['MSH', 'PATIENT'], self::names($profile->root->children));
        $patient = $profile->root->children[1];
        $this->assertInstanceOf(GroupNode::class, $patient);
        $this->assertSame('[0..*]', $patient->cardinality->toString());
        $this->assertSame(Usage::Optional, $patient->usage);
        $this->assertFalse($patient->choice);
        $this->assertFalse($profile->root->choice);
    }

    public function testChoiceGroupIsRead(): void
    {
        // No supported message has a choice group yet (ORM_O01 has one in the
        // schemas): the alternatives of the forged group are only placeholders.
        $path = $this->forge();
        $file = "$path/structures/ADT-A01-ADT_A01.json";
        $structure = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($structure);
        $structure['PATIENT']['choice'] = true;
        $structure['PATIENT']['elements'][] = ['segment' => 'MSH', 'usage' => 'R', 'min' => '1', 'max' => '1'];
        file_put_contents($file, json_encode($structure));

        $patient = ProfileDirectory::open($path)->messageProfile('ADT', 'A01', 'ADT_A01')->root->children[1];

        $this->assertInstanceOf(GroupNode::class, $patient);
        $this->assertTrue($patient->choice);
        $this->assertSame(['PID', 'MSH'], self::names($patient->children));
    }

    /**
     * @return iterable<string, array{string, callable(array<mixed, mixed>): array<mixed, mixed>, string}>
     */
    public static function formatErrors(): iterable
    {
        yield 'format version' => ['profile.json', static function (array $d): array {
            $d['formatVersion'] = '2';
            return $d;
        }, "format version '2' is not supported"];

        yield 'unknown key' => ['fields.json', static function (array $d): array {
            $d['PID.1']['length'] = '4';
            return $d;
        }, 'PID.1: unknown key(s) length.'];

        yield 'missing key' => ['fields.json', static function (array $d): array {
            unset($d['PID.1']['chapter']);
            return $d;
        }, 'PID.1: missing key(s) chapter.'];

        yield 'unknown data type of a field' => ['fields.json', static function (array $d): array {
            $d['PID.1']['dataType'] = 'XYZ';
            return $d;
        }, "PID.1: unknown data type 'XYZ'."];

        yield 'invalid length' => ['fields.json', static function (array $d): array {
            $d['PID.1']['maxLength'] = 'ten';
            return $d;
        }, "PID.1: 'maxLength' must be empty or a number."];

        yield 'unknown data type of a component' => ['dataTypes.json', static function (array $d): array {
            $d['HD']['components'][0]['dataType'] = 'XYZ';
            return $d;
        }, "HD.1: unknown data type 'XYZ'."];

        yield 'component out of order' => ['dataTypes.json', static function (array $d): array {
            $d['HD']['components'][0]['component'] = 'HD.2';
            return $d;
        }, "HD.1: 'component' must be 'HD.1'."];

        yield 'unknown usage' => ['segments.json', static function (array $d): array {
            $d['PID']['fields'][0]['usage'] = 'M';
            return $d;
        }, "PID.1: unknown usage 'M'."];

        yield 'invalid cardinality' => ['segments.json', static function (array $d): array {
            $d['PID']['fields'][0]['min'] = '2';
            return $d;
        }, 'PID.1: invalid cardinality [2..1].'];

        yield 'field out of order' => ['segments.json', static function (array $d): array {
            $d['PID']['fields'][0]['field'] = 'PID.2';
            return $d;
        }, "PID.1: 'field' must be 'PID.1'."];

        yield 'field not defined' => ['segments.json', static function (array $d): array {
            $d['PID']['fields'][] = ['field' => 'PID.3', 'usage' => 'O', 'min' => '0', 'max' => '1'];
            return $d;
        }, 'PID.3: not defined in fields.json.'];

        yield 'first group is not the structure' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            return array_reverse($d, true);
        }, "the first group must be the structure 'ADT_A01'."];

        yield 'group not defined' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            unset($d['PATIENT']);
            return $d;
        }, 'PATIENT: group not defined.'];

        yield 'group used twice' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['ADT_A01']['elements'][] = ['group' => 'PATIENT', 'usage' => 'O', 'min' => '0', 'max' => '1'];
            return $d;
        }, 'PATIENT: group used more than once.'];

        yield 'group never used' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['VISIT'] = ['elements' => [['segment' => 'PID', 'usage' => 'R', 'min' => '1', 'max' => '1']]];
            return $d;
        }, 'group(s) never used: VISIT.'];

        yield 'segment not defined' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['PATIENT']['elements'][] = ['segment' => 'ZZZ', 'usage' => 'O', 'min' => '0', 'max' => '1'];
            return $d;
        }, "segment 'ZZZ' not defined in segments.json."];

        yield 'override of an unknown field' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['PATIENT']['elements'][0]['fields'] = ['PID.9' => ['usage' => 'X', 'min' => '0', 'max' => '0', 'source' => 'TEST 1']];
            return $d;
        }, 'PID.9: not a field of segment PID.'];

        yield 'override without source' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['PATIENT']['elements'][0]['fields'] = ['PID.1' => ['usage' => 'X', 'min' => '0', 'max' => '0']];
            return $d;
        }, 'PID.1: missing key(s) source.'];

        yield 'choice set to false' => ['structures/ADT-A01-ADT_A01.json', static function (array $d): array {
            $d['PATIENT']['choice'] = false;
            return $d;
        }, "PATIENT: 'choice' must be true when present."];
    }

    /**
     * @param callable(array<mixed, mixed>): array<mixed, mixed> $change
     */
    #[DataProvider('formatErrors')]
    public function testFormatErrorIsRefused(string $file, callable $change, string $message): void
    {
        $path = $this->forge();
        $content = json_decode((string) file_get_contents("$path/$file"), true);
        $this->assertIsArray($content);
        file_put_contents("$path/$file", json_encode($change($content)));

        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage($message);

        ProfileDirectory::open($path)->messageProfile('ADT', 'A01', 'ADT_A01');
    }

    /**
     * Write a minimal valid profile directory in the temporary directory.
     */
    private function forge(): string
    {
        $path = sys_get_temp_dir() . '/hl7v2-profile-' . bin2hex(random_bytes(6));
        mkdir("$path/structures", 0777, true);
        $this->forged = $path;

        $files = [
            'profile.json' => [
                'formatVersion' => '1', 'hl7Version' => '2.5', 'name' => 'Test', 'base' => '',
                'generatedFrom' => 'test', 'documents' => ['TEST' => 'Test document'],
            ],
            'dataTypes.json' => [
                'ST' => ['primitive' => true],
                'HD' => ['components' => [[
                    'component' => 'HD.1', 'dataType' => 'ST', 'usage' => 'O', 'min' => '0', 'max' => '1',
                    'maxLength' => '20', 'table' => '', 'longName' => 'Namespace ID',
                ]]],
            ],
            'fields.json' => [
                'MSH.1' => ['item' => '00001', 'dataType' => 'ST', 'maxLength' => '1', 'table' => '', 'longName' => 'Field Separator', 'chapter' => '2.15.9.1'],
                'PID.1' => ['item' => '00104', 'dataType' => 'ST', 'maxLength' => '4', 'table' => '', 'longName' => 'Set ID - PID', 'chapter' => '3.4.2.1'],
                'PID.2' => ['item' => '00105', 'dataType' => 'HD', 'maxLength' => '', 'table' => '', 'longName' => 'Patient ID', 'chapter' => '3.4.2.2'],
            ],
            'segments.json' => [
                'MSH' => ['longName' => 'Message Header', 'chapter' => '2.15.9', 'fields' => [
                    ['field' => 'MSH.1', 'usage' => 'R', 'min' => '1', 'max' => '1'],
                ]],
                'PID' => ['longName' => 'Patient Identification', 'chapter' => '3.4.2', 'fields' => [
                    ['field' => 'PID.1', 'usage' => 'O', 'min' => '0', 'max' => '1'],
                    ['field' => 'PID.2', 'usage' => 'B', 'min' => '0', 'max' => '1'],
                ]],
            ],
            'messageType.json' => ['ADT' => ['A01' => 'ADT_A01']],
            'structures/ADT-A01-ADT_A01.json' => [
                'ADT_A01' => ['elements' => [
                    ['segment' => 'MSH', 'usage' => 'R', 'min' => '1', 'max' => '1'],
                    ['group' => 'PATIENT', 'usage' => 'O', 'min' => '0', 'max' => '*'],
                ]],
                'PATIENT' => ['elements' => [
                    ['segment' => 'PID', 'usage' => 'R', 'min' => '1', 'max' => '1'],
                ]],
            ],
        ];

        foreach ($files as $file => $content) {
            file_put_contents("$path/$file", json_encode($content));
        }
        copy(self::PROFILES . '/json-2.5/rules.json', "$path/rules.json");

        return $path;
    }

    /**
     * @param list<StructureNode> $nodes
     *
     * @return list<string>
     */
    private static function names(array $nodes): array
    {
        return array_map(static fn (StructureNode $node): string => $node->name, $nodes);
    }

    /**
     * First segment node of that name, in depth-first order.
     */
    private static function segmentNode(GroupNode $group, string $name): SegmentNode
    {
        foreach ($group->children as $child) {
            if ($child instanceof SegmentNode && $child->name === $name) {
                return $child;
            }
            if ($child instanceof GroupNode) {
                try {
                    return self::segmentNode($child, $name);
                } catch (\OutOfBoundsException) {
                    // Not in this group.
                }
            }
        }

        throw new \OutOfBoundsException("Segment $name not found.");
    }
}
