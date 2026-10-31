<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\HL7Message;
use HL7v2\Profile\HL7TableLoader;
use HL7v2\Profile\Profile;
use HL7v2\Profile\ProfileLoader;
use HL7v2\Profile\Rules;
use HL7v2\Validation\FindingType;
use HL7v2\Validation\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles';

    private const ADT_A01 = "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
        . "EVN|Z01|20260101120000\r"
        . "PID|1||1^^^AUTH^PI||DOE^JANE\r"
        . "PV1|1|I";

    /**
     * @return iterable<string, array{string}>
     */
    public static function profiles(): iterable
    {
        foreach (['2.3', '2.3.1', '2.4', '2.5', '2.5-IHEPAMFR'] as $profileId) {
            yield $profileId => [$profileId];
        }
    }

    #[DataProvider('profiles')]
    public function testEveryProfileHasValidRules(string $profileId): void
    {
        $rules = $this->rules($profileId);

        $this->assertSame(Severity::Error, $rules->severity(FindingType::RequiredAbsent));
        $this->assertSame(Severity::Warning, $rules->severity(FindingType::TableUser));
        $this->assertTrue($rules->acceptsLocalCodes('0003'));
        $this->assertNotSame('', $rules->getSource());
    }

    public function testClosedTablesOfIhePamFr(): void
    {
        $rules = $this->rules('2.5-IHEPAMFR');

        $this->assertTrue($rules->isClosedTable('0001'));
        $this->assertTrue($rules->isClosedTable('IHE-FRANCE-ZBE-4'));
        // Declared non-limitative by PAM FR.
        $this->assertFalse($rules->isClosedTable('0063'));
        $this->assertFalse($rules->isClosedTable('IHE-FRANCE-ZFS-6'));
        // HL7 profiles close no table.
        $this->assertFalse($this->rules('2.5')->isClosedTable('0001'));
    }

    public function testLocalCodeAcceptedByTheRules(): void
    {
        $report = $this->validate($this->rules('2.5'));

        $this->assertContains(
            "Field 'Event Type Code' (EVN.1) value (Z01) accepted as local extension of table 0003 (HL7 standard tables).",
            $report
        );
    }

    public function testLocalCodeRefusedWithoutTheRule(): void
    {
        $data = $this->rulesData('2.5');
        $data['localCodeTables'] = [];

        $report = $this->validate(Rules::fromArray($data, 'rules.json'));

        $this->assertContains(
            "Field 'Event Type Code' (EVN.1) value (Z01) not in table 0003 (HL7 standard tables).",
            $report
        );
    }

    public function testProfileWithoutRulesIsRefused(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);
        $loaded = (new ProfileLoader(self::PROFILES))->load('2.5', 'ADT', 'A01', 'ADT_A01');

        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('No validation rules');

        $hl7->validateWith(
            new Profile($loaded->getDefinition(), 'ADT_A01'),
            (new HL7TableLoader(self::PROFILES))->load('2.5')
        );
    }

    public function testMissingRulesFileIsRefused(): void
    {
        $dir = sys_get_temp_dir() . '/hl7v2-rules-' . uniqid();
        mkdir($dir . '/json-2.5', 0777, true);
        copy(self::PROFILES . '/json-2.5/ADT-A01-ADT_A01.json', $dir . '/json-2.5/ADT-A01-ADT_A01.json');

        try {
            $this->expectException(HL7Exception::class);
            $this->expectExceptionMessage('Validation rules not found');

            (new ProfileLoader($dir))->load('2.5', 'ADT', 'A01', 'ADT_A01');
        } finally {
            unlink($dir . '/json-2.5/ADT-A01-ADT_A01.json');
            rmdir($dir . '/json-2.5');
            rmdir($dir);
        }
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'unknown key' => [
            static fn (array $d): array => $d + ['severity' => []],
            "unknown key(s) severity",
        ];
        yield 'severities missing' => [
            static function (array $d): array { unset($d['severities']); return $d; },
            "'severities' is required",
        ];
        yield 'severity missing' => [
            static function (array $d): array { unset($d['severities']['parseIssue']); return $d; },
            "severity missing for 'parseIssue'",
        ];
        yield 'unknown severity' => [
            static function (array $d): array { $d['severities']['length'] = 'Fatal'; return $d; },
            "unknown severity for 'length'",
        ];
        yield 'unknown finding type' => [
            static function (array $d): array { $d['severities']['spelling'] = 'Error'; return $d; },
            "unknown finding type(s) spelling",
        ];
        yield 'table list not a list' => [
            static function (array $d): array { $d['closedTables'] = ['a' => '0001']; return $d; },
            "'closedTables' must be a list of table ids",
        ];
        yield 'table list missing' => [
            static function (array $d): array { unset($d['localCodeTables']); return $d; },
            "'localCodeTables' must be a list of table ids",
        ];
        yield 'empty table id' => [
            static function (array $d): array { $d['localCodeTables'] = ['']; return $d; },
            "'localCodeTables' must be a list of table ids",
        ];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    #[DataProvider('invalidRules')]
    public function testInvalidRulesAreRefused(callable $change, string $message): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage("rules.json: $message");

        Rules::fromArray($change($this->rulesData('2.5')), 'rules.json');
    }

    private function rules(string $profileId): Rules
    {
        $rules = (new ProfileLoader(self::PROFILES))->load($profileId, 'ADT', 'A01', 'ADT_A01')->getRules();
        $this->assertNotNull($rules);

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesData(string $profileId): array
    {
        $data = json_decode((string) file_get_contents(self::PROFILES . "/json-$profileId/rules.json"), true);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * Validate the ADT^A01 (EVN-1 = Z01) with the given rules.
     *
     * @return list<string> Descriptions of the TestReport.
     */
    private function validate(Rules $rules): array
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);
        $loaded = (new ProfileLoader(self::PROFILES))->load('2.5', 'ADT', 'A01', 'ADT_A01');
        $result = $hl7->validateWith(
            new Profile($loaded->getDefinition(), 'ADT_A01', $rules),
            (new HL7TableLoader(self::PROFILES))->load('2.5')
        );

        return array_values(array_map(
            static fn (array $entry): string => (string) $entry['Description'],
            $result->getTestReport()
        ));
    }
}
