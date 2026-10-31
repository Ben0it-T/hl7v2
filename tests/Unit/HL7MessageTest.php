<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit;

use HL7v2\Exception\HL7Exception;
use HL7v2\HL7Message;
use HL7v2\Validation\Finding;
use PHPUnit\Framework\TestCase;

final class HL7MessageTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../profiles';

    private const ADT_A01 = "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r"
        . "EVN|A01|20260101120000\r"
        . "PID|1||1^^^AUTH^PI||DOE^JOHN\r"
        . "PV1|1|I";

    private const ADT_A03 = "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A03^ADT_A03|2|P|2.5\r"
        . "EVN|A03|20260101120000\r"
        . "PID|1||1^^^AUTH^PI||DOE^JOHN\r"
        . "PV1|1|I";

    public function testParseHasNoValidationResult(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);

        $this->assertNull($hl7->getValidationResult());
        $this->assertSame([], $hl7->getProfiledMessage());
    }

    public function testParseResetsValidationState(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);
        $hl7->validate(self::PROFILES);
        $this->assertNotNull($hl7->getValidationResult());

        $hl7->parse(self::ADT_A03);

        $this->assertNull($hl7->getValidationResult());
        $this->assertSame([], $hl7->getProfiledMessage());
    }

    public function testDatatypeXmlRequiresValidationAfterNewParse(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);
        $hl7->validate(self::PROFILES);
        $hl7->parse(self::ADT_A03);

        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('No profiled message available');

        $hl7->toDatatypeXml();
    }

    public function testFindingsRequireValidation(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);

        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('No validation result available');

        $hl7->getFindings();
    }

    public function testFindingsRequireValidationAfterNewParse(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01);
        $hl7->validate(self::PROFILES);
        $hl7->parse(self::ADT_A03);

        $this->expectException(HL7Exception::class);
        $this->expectExceptionMessage('No validation result available');

        $hl7->getFindings();
    }

    public function testFindingsAreTheOnesOfTheValidationResult(): void
    {
        $hl7 = new HL7Message();
        $hl7->parse(self::ADT_A01 . "\rZZA|1");
        $result = $hl7->validate(self::PROFILES);

        $this->assertSame($result->getFindings(), $hl7->getFindings());
        $this->assertContains(
            ['Warning', 'zSegmentNotDefined', 'ZZA'],
            array_map(
                static fn (Finding $finding): array => [
                    $finding->severity->value,
                    $finding->type->value,
                    $finding->location?->toShortString(),
                ],
                $hl7->getFindings()
            )
        );
    }
}
