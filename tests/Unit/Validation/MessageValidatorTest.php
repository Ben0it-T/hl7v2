<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation;

use HL7v2\HL7Message;
use HL7v2\Model\Message;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Validation\Finding;
use HL7v2\Validation\MessageValidator;
use HL7v2\Validation\ValidationOutcome;
use PHPUnit\Framework\TestCase;

/**
 * The new validator on the normalized profile format: the structure pass,
 * the problems met while parsing, then the content pass.
 */
final class MessageValidatorTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles-next';

    public function testValidMessage(): void
    {
        // MSH-3 to MSH-6 absent: their HD.1 has a table without codes (0300, 0362), an Info.
        $outcome = $this->validate("MSH|^~\\&|||||20260101120000||ADT^A01^ADT_A01|1|P|2.5\rEVN||20260101120000\rPID|1||123||DOE^JOHN\rPV1|1|I");

        $this->assertTrue($outcome->result->isValid());
        $this->assertSame([], $outcome->result->getFindings());
        $this->assertNotSame([], $outcome->result->getTestReport());
        $this->assertSame('', $outcome->profiledMessage->root->path);
        $this->assertCount(4, $outcome->profiledMessage->root->segments());
    }

    public function testPassesInOrder(): void
    {
        // PV1 absent (structure), unknown character set in MSH-18 (parsing,
        // then content: not in table 0211), PID-5 absent (content).
        $outcome = $this->validate("MSH|^~\\&|||||20260101120000||ADT^A01^ADT_A01|1|P|2.5||||||FOO-CHARSET\rEVN||20260101120000\rPID|1||123");

        $this->assertSame([
            'Error requiredAbsent - segment=-',
            'Error parseIssue MSH-18 segment=1',
            'Error tableHL7 MSH-18 segment=1',
            'Error requiredAbsent PID-5 segment=3',
        ], self::summary($outcome));
    }

    public function testParseIssueIsAttachedToItsSegment(): void
    {
        $outcome = $this->validate("MSH|^~\\&|||||20260101120000||ADT^A01^ADT_A01|1|P|2.5||||||FOO-CHARSET\rEVN||20260101120000\rPID|1||123||DOE^JOHN\rPV1|1|I");

        $this->assertSame(['Error parseIssue MSH-18 segment=1', 'Error tableHL7 MSH-18 segment=1'], self::summary($outcome));

        $msh = $outcome->profiledMessage->root->segments()[0];
        $this->assertSame($outcome->result->getFindings(), $outcome->profiledMessage->findingsOf($msh));

        $entries = array_values(array_filter(
            $outcome->result->getTestReport(),
            static fn (array $entry): bool => $entry['Type'] === 'Parse'
        ));
        $this->assertCount(1, $entries);
        $this->assertSame('MSH-18', $entries[0]['Location']);
        $this->assertFalse($entries[0]['Result']);
        $this->assertSame('Error', $entries[0]['Severity']);
    }

    public function testParseIssueWithoutLocation(): void
    {
        // Invalid UTF-8 bytes, no MSH-18: the whole message, attached to no element.
        $outcome = $this->validate("MSH|^~\\&|||||20260101120000||ADT^A01^ADT_A01|1|P|2.5\rEVN||20260101120000\rPID|1||123||DUPR\xC9\rPV1|1|I");

        $this->assertSame(['Error parseIssue - segment=-'], self::summary($outcome));

        $entries = array_values(array_filter(
            $outcome->result->getTestReport(),
            static fn (array $entry): bool => $entry['Type'] === 'Parse'
        ));
        $this->assertSame('ADT_A01', $entries[0]['Location']);
        foreach ($outcome->profiledMessage->root->segments() as $segment) {
            $this->assertSame([], $outcome->profiledMessage->findingsOf($segment));
        }
    }

    private function validate(string $hl7): ValidationOutcome
    {
        $message = $this->parse($hl7);
        $profile = ProfileDirectory::open(self::PROFILES . '/json-2.5')
            ->messageProfile($message->getMessageCode(), $message->getTriggerEvent(), $message->getStructure());

        return (new MessageValidator())->validate($message, $profile);
    }

    private function parse(string $hl7): Message
    {
        $parser = new HL7Message();
        $parser->parse($hl7);
        $message = $parser->getMessage();
        $this->assertNotNull($message);

        return $message;
    }

    /**
     * @return list<string>
     */
    private static function summary(ValidationOutcome $outcome): array
    {
        return array_map(
            static fn (Finding $finding): string => sprintf(
                '%s %s %s segment=%s',
                $finding->severity->value,
                $finding->type->value,
                $finding->location?->toShortString() ?? '-',
                $finding->segment ?? '-'
            ),
            $outcome->result->getFindings()
        );
    }
}
