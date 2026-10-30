<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Profile;

use HL7v2\Exception\HL7Exception;
use HL7v2\HL7Message;
use HL7v2\Profile\ProfileLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Message metadata kept by the parser, and structure resolved by the
 * profile loader: MSH-9.3, else ACK for an acknowledgement, else
 * messageType.json.
 */
final class ProfileLoaderTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../profiles';

    /**
     * @return iterable<string, array{string, string, array{string, string, string}, string}>
     */
    public static function messages(): iterable
    {
        // version, MSH-9, [type, event, structure] kept by the parser, resolved structure
        yield '2.3 ADT^A04'               => ['2.3', 'ADT^A04', ['ADT', 'A04', ''], 'ADT_A01'];
        yield '2.3 ACK^A01'               => ['2.3', 'ACK^A01', ['ACK', 'A01', ''], 'ACK'];
        yield '2.3 ACK without event'     => ['2.3', 'ACK', ['ACK', '', ''], 'ACK'];
        yield '2.3.1 ACK^A04'             => ['2.3.1', 'ACK^A04', ['ACK', 'A04', ''], 'ACK'];
        yield '2.4 ADT^A04^ADT_A01'       => ['2.4', 'ADT^A04^ADT_A01', ['ADT', 'A04', 'ADT_A01'], 'ADT_A01'];
        yield '2.4 ACK^A01^ACK'           => ['2.4', 'ACK^A01^ACK', ['ACK', 'A01', 'ACK'], 'ACK'];
        yield '2.5 ACK^R01^ACK'           => ['2.5', 'ACK^R01^ACK', ['ACK', 'R01', 'ACK'], 'ACK'];
        yield '2.5 ADT^A47 (no structure)' => ['2.5', 'ADT^A47', ['ADT', 'A47', ''], 'ADT_A30'];
        yield 'IHE PAM FR ADT^A01^ADT_A01' => ['2.5-IHEPAMFR', 'ADT^A01^ADT_A01', ['ADT', 'A01', 'ADT_A01'], 'ADT_A01'];
    }

    /**
     * @param array{string, string, string} $kept
     */
    #[DataProvider('messages')]
    public function testMetadataAndResolvedStructure(string $profileId, string $msh9, array $kept, string $resolved): void
    {
        $hl7 = new HL7Message();
        $hl7->parse($this->message($profileId, $msh9));
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        $this->assertSame(
            $kept,
            [$message->getMessageCode(), $message->getTriggerEvent(), $message->getStructure()]
        );

        $profile = (new ProfileLoader(self::PROFILES))->load(
            $profileId,
            $message->getMessageCode(),
            $message->getTriggerEvent(),
            $message->getStructure()
        );
        $this->assertSame($resolved, $profile->getStructure());
    }

    public function testWrongStructureInMsh9IsNotCorrected(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Profile not found: ADT-A44-ADT_A30');

        (new ProfileLoader(self::PROFILES))->load('2.5', 'ADT', 'A44', 'ADT_A30');
    }

    public function testAcknowledgementWithAnotherStructureHasNoProfile(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Profile not found: ACK-ACK-ACK_A01');

        (new ProfileLoader(self::PROFILES))->load('2.5', 'ACK', 'A01', 'ACK_A01');
    }

    public function testUnknownEventWithoutStructure(): void
    {
        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('Message structure not found for ADT^Z42.');

        (new ProfileLoader(self::PROFILES))->load('2.3', 'ADT', 'Z42', '');
    }

    public function testStructuralXmlRootBeforeAndAfterValidation(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse($this->message('2.3', 'ADT^A04'));

        // Without profile: <type>_<event>.
        $this->assertStringContainsString('<ADT_A04 xmlns="urn:hl7-org:v2xml">', $hl7->toStructuralXml());

        $hl7->validate(self::PROFILES);

        // After validation: the structure resolved by the profile.
        $this->assertStringContainsString('<ADT_A01 xmlns="urn:hl7-org:v2xml">', $hl7->toStructuralXml());
        $this->assertStringContainsString('<ADT_A01 xmlns="urn:hl7-org:v2xml">', $hl7->toDatatypeXml());

        // A new parse forgets the resolved structure.
        $hl7->parse($this->message('2.3', 'ADT^A04'));
        $this->assertStringContainsString('<ADT_A04 xmlns="urn:hl7-org:v2xml">', $hl7->toStructuralXml());
    }

    public function testStructuralXmlRootOfAnAcknowledgementWithoutEvent(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse($this->message('2.3', 'ACK'));

        $this->assertStringContainsString('<ACK xmlns="urn:hl7-org:v2xml">', $hl7->toStructuralXml());
    }

    private function message(string $profileId, string $msh9): string
    {
        $version = explode('-', $profileId)[0];
        $segments = ["MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||$msh9|1|P|$version"];
        if (str_starts_with($msh9, 'ACK')) {
            $segments[] = 'MSA|AA|1';
        } else {
            $segments[] = 'EVN||20260101120000';
            $segments[] = 'PID|1||1^^^AUTH^PI||DOE^JANE';
            $segments[] = 'PV1|1|O';
        }

        return implode("\r", $segments);
    }
}
