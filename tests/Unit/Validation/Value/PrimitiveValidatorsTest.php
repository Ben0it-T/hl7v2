<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Value;

use HL7v2\Validation\Value\PrimitiveValidators;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validators of the primitive data types, on the Decoded value (HL7 v2.5
 * sections 2.A.21 DT, 2.A.22 DTM, 2.A.47 NM, 2.A.69 SI, 2.A.75 TM).
 */
final class PrimitiveValidatorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validValues(): iterable
    {
        yield 'DT year' => ['DT', '2026'];
        yield 'DT month' => ['DT', '202602'];
        yield 'DT day' => ['DT', '20240229'];
        yield 'DTM year' => ['DTM', '1999'];
        yield 'DTM month' => ['DTM', '199904'];
        yield 'DTM day' => ['DTM', '19880705'];
        yield 'DTM hour' => ['DTM', '1988070523'];
        yield 'DTM midnight' => ['DTM', '198807050000'];
        yield 'DTM second and zone' => ['DTM', '19760704010159-0500'];
        yield 'DTM fraction' => ['DTM', '20260101120000.1234+0100'];
        yield 'DTM UTC' => ['DTM', '20260101120000-0000'];
        yield 'NM integer' => ['NM', '999'];
        yield 'NM signed decimal' => ['NM', '-123.792'];
        yield 'NM leading zeros' => ['NM', '01.20'];
        yield 'NM plus sign' => ['NM', '+1'];
        yield 'NM leading decimal point' => ['NM', '.5'];
        yield 'NM trailing decimal point' => ['NM', '1.'];
        yield 'SI zero' => ['SI', '0'];
        yield 'SI' => ['SI', '9999'];
        yield 'TM midnight' => ['TM', '0000'];
        yield 'TM hour' => ['TM', '08'];
        yield 'TM fraction' => ['TM', '093544.2312'];
        yield 'TM zone' => ['TM', '235959+1100'];
    }

    #[DataProvider('validValues')]
    public function testValidValue(string $dataType, string $value): void
    {
        $validator = PrimitiveValidators::standard()->get($dataType);
        $this->assertNotNull($validator);

        $this->assertNull($validator->check($value));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'DT format' => ['DT', '2026022', "'2026022' is not a date in the format YYYY[MM[DD]]."];
        yield 'DT month' => ['DT', '202613', "'202613' is not a date of the calendar."];
        yield 'DT day' => ['DT', '20230229', "'20230229' is not a date of the calendar."];
        yield 'DT with time' => ['DT', '202601011200', "'202601011200' is not a date in the format YYYY[MM[DD]]."];
        yield 'DTM format' => ['DTM', '2026-01-01', "'2026-01-01' is not a date/time in the format YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ]."];
        yield 'DTM fraction without seconds' => ['DTM', '202601011200.1', "'202601011200.1' is not a date/time in the format YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ]."];
        yield 'DTM fraction of 5 digits' => ['DTM', '20260101120000.12345', "'20260101120000.12345' is not a date/time in the format YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ]."];
        yield 'DTM zone of 3 digits' => ['DTM', '19981004010159+010', "'19981004010159+010' is not a date/time in the format YYYY[MM[DD[HH[MM[SS[.S[S[S[S]]]]]]]]][+/-ZZZZ]."];
        yield 'DTM date' => ['DTM', '20260230', "'20260230' is not a date of the calendar."];
        yield 'DTM hour' => ['DTM', '2026010124', "'2026010124' is not a time of the 24-hour clock."];
        yield 'DTM minute' => ['DTM', '202601011260', "'202601011260' is not a time of the 24-hour clock."];
        yield 'DTM zone' => ['DTM', '20260101120000+2400', "'20260101120000+2400' has an invalid time zone offset."];
        yield 'NM comma' => ['NM', '1,5', "'1,5' is not a number (optional sign, digits, optional decimal point)."];
        yield 'NM sign only' => ['NM', '-', "'-' is not a number (optional sign, digits, optional decimal point)."];
        yield 'NM decimal point only' => ['NM', '.', "'.' is not a number (optional sign, digits, optional decimal point)."];
        yield 'NM comparison' => ['NM', '<12', "'<12' is not a number (optional sign, digits, optional decimal point)."];
        yield 'NM exponent' => ['NM', '1E3', "'1E3' is not a number (optional sign, digits, optional decimal point)."];
        yield 'SI negative' => ['SI', '-1', "'-1' is not a non-negative integer."];
        yield 'SI decimal' => ['SI', '1.0', "'1.0' is not a non-negative integer."];
        yield 'TM format' => ['TM', '8', "'8' is not a time in the format HH[MM[SS[.S[S[S[S]]]]]][+/-ZZZZ]."];
        yield 'TM fraction without seconds' => ['TM', '0930.5', "'0930.5' is not a time in the format HH[MM[SS[.S[S[S[S]]]]]][+/-ZZZZ]."];
        yield 'TM hour' => ['TM', '2400', "'2400' is not a time of the 24-hour clock."];
        yield 'TM second' => ['TM', '120060', "'120060' is not a time of the 24-hour clock."];
        yield 'TM zone' => ['TM', '1200+0160', "'1200+0160' has an invalid time zone offset."];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValue(string $dataType, string $value, string $reason): void
    {
        $validator = PrimitiveValidators::standard()->get($dataType);
        $this->assertNotNull($validator);

        $this->assertSame($reason, $validator->check($value));
    }

    public function testDataTypesWithoutValidator(): void
    {
        $validators = PrimitiveValidators::standard();

        foreach (['ID', 'IS', 'ST', 'TX', 'FT', 'GTS', 'TN', 'varies', 'CE'] as $dataType) {
            $this->assertNull($validators->get($dataType), $dataType);
        }
    }

    public function testValidatorKeepsNoState(): void
    {
        $validator = PrimitiveValidators::standard()->get('NM');
        $this->assertNotNull($validator);

        $this->assertNotNull($validator->check('X'));
        $this->assertNull($validator->check('1'));
    }
}
