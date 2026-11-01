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
 * The new validator on the normalized profile format: problems met while
 * parsing first, then the structure pass.
 */
final class MessageValidatorTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles-next';

    public function testValidMessage(): void
    {
        $outcome = $this->validate("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\rEVN|A01\rPID|1\rPV1|1");

        $this->assertTrue($outcome->result->isValid());
        $this->assertSame([], $outcome->result->getFindings());
        $this->assertNotSame([], $outcome->result->getTestReport());
        $this->assertSame('', $outcome->profiledMessage->root->path);
        $this->assertCount(4, $outcome->profiledMessage->root->segments());
    }

    public function testParseIssuesComeFirst(): void
    {
        // Unknown character set (MSH-18), then PV1 absent.
        $outcome = $this->validate("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5||||||FOO-CHARSET\rEVN|A01\rPID|1");

        $this->assertSame([
            'Error parseIssue MSH-18 segment=1',
            'Error requiredAbsent - segment=-',
        ], self::summary($outcome));

        $entry = $outcome->result->getTestReport()[0];
        $this->assertSame('MSH-18', $entry['Location']);
        $this->assertSame('Parse', $entry['Type']);
        $this->assertFalse($entry['Result']);
        $this->assertSame('Error', $entry['Severity']);
    }

    public function testParseIssueWithoutLocation(): void
    {
        // Invalid UTF-8 bytes, no MSH-18: the whole message.
        $outcome = $this->validate("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\rEVN|A01\rPID|1||||DUPR\xC9\rPV1|1");

        $this->assertSame(['Error parseIssue - segment=-'], self::summary($outcome));
        $this->assertSame('ADT_A01', $outcome->result->getTestReport()[0]['Location']);
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
