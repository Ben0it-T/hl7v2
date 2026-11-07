<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Content;

use HL7v2\HL7Message;
use HL7v2\Model\Message;
use HL7v2\Profile\Definition\Cardinality;
use HL7v2\Profile\Definition\FieldDefinition;
use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\Definition\SegmentDefinition;
use HL7v2\Profile\Definition\SegmentField;
use HL7v2\Profile\Definition\SegmentNode;
use HL7v2\Profile\Definition\Usage;
use HL7v2\Profile\ProfileDirectory;
use HL7v2\Validation\CheckRecorder;
use HL7v2\Validation\Content\ContentChecker;
use HL7v2\Validation\Finding;
use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\Structure\SegmentOccurrence;
use HL7v2\Validation\Structure\StructureMatcher;
use HL7v2\Validation\Structure\UnplacedSegment;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Content of the segments: usage, count of repetitions as received, HL7
 * length and Explicit null of fields, components and sub-components; escape
 * sequences of the primitive values. One finding per cause; each finding
 * attached to its segment element.
 */
final class ContentCheckerTest extends TestCase
{
    private const PROFILES = __DIR__ . '/../../../../profiles-next';

    /**
     * Fields of the PID segment used by the tests, ST unless stated:
     * 1 R [1..1] length 1; 2 O [0..2] length 5; 3 X [0..0]; 4 W; 5 B; 6 C; 7 CE;
     * 8 R [2..3], no length; then optional fields of the data types of the
     * profile directory: 9 CX, 10 XPN, 11 TS, 12 FT, 13 NM, 14 varies;
     * 15 ID of table 0136 (HL7), 16 IS of table 0001 (User, closed in IHE PAM
     * FR), 17 CE of table 0001, 18 ID of table 0003 (local codes accepted),
     * 19 ID of table ZZZZ (absent), 20 IS of table 0300 (without codes),
     * 21 DT, 22 SI, 23 TM, 24 GTS (no validator), 25 NM of table 0136;
     * 26 CNE of table 0136 (HL7), 27 CWE of table 0136, 28 CNE of table 0001
     * (User), 29 CWE of table 0001 (closed in IHE PAM FR), 30 CNE of table
     * 0003 and 31 CWE of table 0003 (local codes accepted).
     */
    private static function pid(): SegmentDefinition
    {
        $field = static fn (int $position, Usage $usage, int $min, ?int $max, ?int $length = null, string $type = 'ST', ?string $table = null): SegmentField =>
            new SegmentField($position, $usage, new Cardinality($min, $max), new FieldDefinition(
                "PID.$position", '00000', $type, $length, $table, "Field $position", '3'
            ));

        return new SegmentDefinition('PID', 'Patient Identification', '3', [
            $field(1, Usage::Required, 1, 1, 1),
            $field(2, Usage::Optional, 0, 2, 5),
            $field(3, Usage::NotSupported, 0, 0),
            $field(4, Usage::Withdrawn, 0, 1),
            $field(5, Usage::Backward, 0, 1),
            $field(6, Usage::Conditional, 0, 1),
            $field(7, Usage::ConditionalOrEmpty, 0, 1),
            $field(8, Usage::Required, 2, 3),
            $field(9, Usage::Optional, 0, null, null, 'CX'),
            $field(10, Usage::Optional, 0, null, null, 'XPN'),
            $field(11, Usage::Optional, 0, 1, null, 'TS'),
            $field(12, Usage::Optional, 0, 1, null, 'FT'),
            $field(13, Usage::Optional, 0, 1, null, 'NM'),
            $field(14, Usage::Optional, 0, 1, null, 'varies'),
            $field(15, Usage::Optional, 0, 1, null, 'ID', '0136'),
            $field(16, Usage::Optional, 0, 1, null, 'IS', '0001'),
            $field(17, Usage::Optional, 0, 1, null, 'CE', '0001'),
            $field(18, Usage::Optional, 0, 1, null, 'ID', '0003'),
            $field(19, Usage::Optional, 0, 1, null, 'ID', 'ZZZZ'),
            $field(20, Usage::Optional, 0, 1, null, 'IS', '0300'),
            $field(21, Usage::Optional, 0, 1, null, 'DT'),
            $field(22, Usage::Optional, 0, 1, null, 'SI'),
            $field(23, Usage::Optional, 0, 1, null, 'TM'),
            $field(24, Usage::Optional, 0, 1, null, 'GTS'),
            $field(25, Usage::Optional, 0, 1, null, 'NM', '0136'),
            $field(26, Usage::Optional, 0, 1, null, 'CNE', '0136'),
            $field(27, Usage::Optional, 0, 1, null, 'CWE', '0136'),
            $field(28, Usage::Optional, 0, 1, null, 'CNE', '0001'),
            $field(29, Usage::Optional, 0, 1, null, 'CWE', '0001'),
            $field(30, Usage::Optional, 0, 1, null, 'CNE', '0003'),
            $field(31, Usage::Optional, 0, 1, null, 'CWE', '0003'),
        ]);
    }

    public function testValidSegment(): void
    {
        [$outcome] = $this->check('PID|A|ABCDE~AB||||||X~Y');

        $this->assertSame([], self::summary($outcome));
    }

    public function testRequiredFieldAbsentGivesOneFinding(): void
    {
        [$outcome, $profiled] = $this->check('PID||||||||X~Y');

        $this->assertSame(['Error requiredAbsent PID-1'], self::summary($outcome));
        $this->assertSame($outcome->getFindings(), $profiled->findingsOf($this->pidElement($profiled)));
    }

    public function testRequiredFieldWithEmptyComponentsIsAbsent(): void
    {
        [$outcome] = $this->check('PID|^&^|||||||~');

        $this->assertSame(['Error requiredAbsent PID-1', 'Error requiredAbsent PID-8'], self::summary($outcome));
    }

    public function testExplicitNullSatisfiesRequiredAndHasNoLength(): void
    {
        // "" has 2 characters, beyond the length of PID-1 (1): not checked.
        [$outcome] = $this->check('PID|""|""||||||X~""');

        $this->assertSame([], self::summary($outcome));
    }

    public function testNotAllowedFieldPresentGivesOneFinding(): void
    {
        // Beyond its maximum (0) and with a value: notAllowedPresent only.
        [$outcome] = $this->check('PID|A||X~Y|||||X~Y');

        $this->assertSame(['Error notAllowedPresent PID-3'], self::summary($outcome));
    }

    public function testNotAllowedFieldWithExplicitNull(): void
    {
        [$outcome] = $this->check('PID|A||""|||||X~Y');

        $this->assertSame(['Error notAllowedPresent PID-3'], self::summary($outcome));
    }

    public function testWithdrawnBackwardAndConditionalPresent(): void
    {
        [$outcome] = $this->check('PID|A|||W|B|C|CE|X~Y');

        $this->assertSame([
            'Warning withdrawnPresent PID-4',
            'Info backwardPresent PID-5',
            'Info conditionalPresent PID-6',
            'Info conditionalPresent PID-7',
        ], self::summary($outcome));
    }

    public function testRepetitionsBeyondTheMaximum(): void
    {
        // The empty repetition in the middle counts: 3 repetitions received.
        [$outcome] = $this->check('PID|A|AB~~CD||||||X~Y');

        $this->assertSame(['Error cardinality PID-2'], self::summary($outcome));
        $this->assertStringContainsString('(found 3)', $outcome->getFindings()[0]->message);
    }

    public function testRequiredFieldUnderItsMinimum(): void
    {
        [$outcome] = $this->check('PID|A|||||||X');

        $this->assertSame(['Error cardinality PID-8'], self::summary($outcome));
        $this->assertStringContainsString('at least 2 repetition(s) (found 1)', $outcome->getFindings()[0]->message);
    }

    public function testLengthOfEachRepetition(): void
    {
        [$outcome] = $this->check('PID|AB|ABCDEF~ABCDEFG||||||X~Y');

        $this->assertSame([
            'Error length PID-1',
            'Error length PID-2',
            'Error length PID-2[2]',
        ], self::summary($outcome));
    }

    public function testLengthIsTheHl7Length(): void
    {
        // A\F\BCD: 5 characters of data (\F\ counts 1); ABCD\F\: 5 too;
        // ABCDE\F\: 6. The separators of a repetition count 1 each (A^B&C: 5).
        [$outcome] = $this->check('PID|A|A\F\BCD~ABCD\F\||||||X~Y');
        $this->assertSame([], self::summary($outcome));

        [$outcome] = $this->check('PID|A|ABCDE\F\~A^B&C||||||X~Y');
        $this->assertSame(['Error length PID-2'], self::summary($outcome));
        $this->assertStringContainsString('length (6) exceeds', $outcome->getFindings()[0]->message);
    }

    public function testPassingChecksAreInTheTestReport(): void
    {
        [$outcome] = $this->check('PID|A|AB||||||X~Y');

        $entries = array_map(
            static fn (array $entry): string => "{$entry['Type']} {$entry['Location']} " . ($entry['Result'] ? 'ok' : 'ko'),
            $outcome->getTestReport()
        );
        $this->assertContains('Usage PID-1 ok', $entries);
        $this->assertContains('Cardinality PID-1 ok', $entries);
        $this->assertContains('Length PID-1 ok', $entries);
        $this->assertContains('Usage PID-3 ok', $entries); // X, absent.
        $this->assertContains('Cardinality PID-8 ok', $entries);
        $this->assertNotContains('Length PID-8 ok', $entries); // No length in the profile.
    }

    public function testFindingsHaveThePositionsOfTheSegment(): void
    {
        [$outcome] = $this->check('PID||||||||X~Y');

        $finding = $outcome->getFindings()[0];
        $this->assertSame('PID[1]', $finding->profilePath);
        $this->assertSame(2, $finding->segment);
        $this->assertSame('PID[1]-1[1]', $finding->location?->toLongString());
    }

    public function testNotExpectedSegmentIsCheckedAgainstItsNearestDefinition(): void
    {
        // The second PID, after PV1, has no place: it is judged as a PID.
        [$outcome, $profiled] = $this->check("PID|A|||||||X~Y\rPV1|1\rPID||||||||X~Y");

        $unplaced = $profiled->root->segments()[3];
        $this->assertInstanceOf(UnplacedSegment::class, $unplaced);
        $this->assertSame(['Error requiredAbsent PID[2]-1'], self::summary($outcome, $profiled->findingsOf($unplaced)));
        $this->assertSame(4, $profiled->findingsOf($unplaced)[0]->segment);
        $this->assertNull($profiled->findingsOf($unplaced)[0]->profilePath);
    }

    public function testNotDefinedSegmentIsNotChecked(): void
    {
        [$outcome] = $this->check("PID|A|||||||X~Y\rZZZ|1|2|3");

        $this->assertSame([], self::summary($outcome));
    }

    public function testComponentRequiredAbsent(): void
    {
        // CX.1 (R) absent; CX.4 (HD) present, HD.1 of table 0300, without codes.
        [$outcome] = $this->check('PID|A|||||||X~Y|^^^HOSP');

        $this->assertSame(['Error requiredAbsent PID-9.1', 'Info tableUnavailable PID-9.4.1'], self::summary($outcome));
    }

    public function testNothingIsJudgedBelowAnExplicitNull(): void
    {
        // The repetition is an Explicit null: CX.1 (R) is not required.
        [$outcome] = $this->check('PID|A|||||||X~Y|""');

        $this->assertSame([], self::summary($outcome));
    }

    public function testComponentExplicitNullSatisfiesRequired(): void
    {
        [$outcome] = $this->check('PID|A|||||||X~Y|""^^^HOSP');

        $this->assertSame(['Info tableUnavailable PID-9.4.1'], self::summary($outcome));
    }

    public function testComponentNotAllowedPresent(): void
    {
        // IHE PAM FR: XPN.4 (suffix) is X, even as an Explicit null; XPN.2 is C,
        // XPN.7 (name type) is R.
        [$outcome] = $this->check('PID|A|||||||X~Y||DOE^JOHN^^""^^^L', 'json-2.5-IHEPAMFR');

        $this->assertSame(['Info conditionalPresent PID-10.2', 'Error notAllowedPresent PID-10.4'], self::summary($outcome));
    }

    public function testSubComponentRequiredAbsent(): void
    {
        // XPN.1 (FN) without FN.1 (R): FN.2 only.
        [$outcome] = $this->check('PID|A|||||||X~Y||&VAN^JOHN');

        $this->assertSame(['Error requiredAbsent PID-10.1.1'], self::summary($outcome));
    }

    public function testExplicitNullInASubComponentIsALiteralValue(): void
    {
        // FN.1 = "" beside FN.2: a value of 2 characters, FN.1 is present.
        [$outcome] = $this->check('PID|A|||||||X~Y||""&VAN^JOHN');

        $this->assertSame([], self::summary($outcome));
    }

    public function testComponentAndSubComponentLength(): void
    {
        // CX.1: 16 characters (15 at most); FN.1: 51 characters (50 at most).
        [$outcome] = $this->check('PID|A|||||||X~Y|1234567890123456|' . str_repeat('D', 51));

        $this->assertSame(['Error length PID-9.1', 'Error length PID-10.1.1'], self::summary($outcome));
    }

    public function testCompositeAtTheSubComponentLevelIsJudgedAsItsType(): void
    {
        // XPN.10 (DR, B): DR.1 and DR.2 are TS; the value of each is its TS.1 (DTM, R, length 24).
        [$outcome] = $this->check('PID|A|||||||X~Y||DOE^^^^^^^^^20200101&2020010112000000000000000');

        $this->assertSame(
            ['Info backwardPresent PID-10.10', 'Error length PID-10.10.2', 'Error datatype PID-10.10.2'],
            self::summary($outcome)
        );
        $this->assertSame(
            "Component 'Time' (TS.1) of SubComponent 'Range End Date/Time' (DR.2) length (25) exceeds the length defined in the message profile (24).",
            $outcome->getFindings()[1]->message
        );

        $entries = self::entries($outcome);
        $this->assertContains("Usage PID-10.10.1 ok Component 'Time' (TS.1) of SubComponent 'Range Start Date/Time' (DR.1) usage is R.", $entries);
        // TS.2 cannot be sent at this level: not judged.
        $this->assertSame([], array_values(array_filter($entries, static fn (string $entry): bool => str_contains($entry, 'TS.2'))));
    }

    public function testSecondComponentOfATimeStampAsAField(): void
    {
        [$outcome] = $this->check('PID|A|||||||X~Y|||20260101^Y');
        $this->assertSame(['Info backwardPresent PID-11.2'], self::summary($outcome));

        [$outcome] = $this->check('PID|A|||||||X~Y|||20260101^Y', 'json-2.5-IHEPAMFR');
        $this->assertSame(['Error notAllowedPresent PID-11.2'], self::summary($outcome));
    }

    public function testEscapeSequencesOfThePrimitiveValues(): void
    {
        [$outcome] = $this->check(
            'PID|A|\.br\||||||X~Y||O\T\BRIEN\H\^JOHN\X41\||A\.br\B\H\C\N\|1\Q\\\H\\'
        );

        $this->assertSame([
            'Error escapeNotAllowed PID-2',
            'Error escapeNotAllowed PID-10.1.1',
            'Error escapeNotAllowed PID-10.2',
            'Error escapeMalformed PID-13',
            'Error escapeNotAllowed PID-13',
            'Error datatype PID-13',
        ], self::summary($outcome));
        $this->assertSame(
            "SubComponent 'Surname' (FN.1) has escape sequence(s) not allowed in data type ST: \\H\\.",
            $outcome->getFindings()[1]->message
        );
    }

    public function testEscapeSequenceInATimeStampOfARange(): void
    {
        // A TS.2 attempted with an escaped component separator: a DTM allows no
        // sequence, and its Decoded value (20200101^Y) is not a DTM.
        [$outcome] = $this->check('PID|A|||||||X~Y||DOE^^^^^^^^^20200101\S\Y');

        $this->assertSame(
            ['Info backwardPresent PID-10.10', 'Error escapeNotAllowed PID-10.10.1', 'Error datatype PID-10.10.1'],
            self::summary($outcome)
        );
    }

    public function testEscapeChecksOnlyWhenTheValueHoldsTheEscapeCharacter(): void
    {
        [$outcome] = $this->check('PID|A|AB|||||X~Y||DOE^JOHN|||12');
        $this->assertSame([], array_values(array_filter(self::entries($outcome), static fn (string $entry): bool => str_starts_with($entry, 'Escape'))));

        [$outcome] = $this->check('PID|A|A\F\B||||||X~Y');
        $this->assertSame([
            "Escape PID-2 ok Field 'Field 2' (PID.2) escape sequences are well formed.",
            "Escape PID-2 ok Field 'Field 2' (PID.2) escape sequences are allowed in data type ST.",
        ], array_values(array_filter(self::entries($outcome), static fn (string $entry): bool => str_starts_with($entry, 'Escape'))));
    }

    public function testEncodingCharactersAreNotEscapedValues(): void
    {
        // MSH-2 "^~\&" would read as a malformed "\&".
        [$outcome] = $this->check('PID|A|||||||X~Y');

        $this->assertSame([], self::summary($outcome));
        $this->assertContains("Length MSH-2 ok Field 'Field 2' (MSH.2) length (4) does not exceed the length defined in the message profile (4).", self::entries($outcome));
    }

    public function testVariesValueIsNotCheckedForEscapeSequences(): void
    {
        [$outcome] = $this->check('PID|A|||||||X~Y||||||A\H\B\.br\\');

        $this->assertSame([], self::summary($outcome));
    }

    public function testPrimitiveValueIsItsFirstSubComponent(): void
    {
        // The second component of an ST field is in excess: not judged here.
        [$outcome] = $this->check('PID|A|AB^\H\||||||X~Y');

        $this->assertSame([], self::summary($outcome));
    }

    public function testPrimitiveDataTypes(): void
    {
        [$outcome] = $this->check(self::pidWith([11 => '20260101120000+0100', 13 => '-1.5', 21 => '20260228', 22 => '3', 23 => '2359']));
        $this->assertSame([], self::summary($outcome));
        $this->assertContains("Datatype PID-21 ok Field 'Field 21' (PID.21) value is a valid DT.", self::entries($outcome));

        [$outcome] = $this->check(self::pidWith([11 => '20260230', 13 => '1,5', 21 => '2026022', 22 => '-3', 23 => '2460']));
        $this->assertSame([
            'Error datatype PID-11.1',
            'Error datatype PID-13',
            'Error datatype PID-21',
            'Error datatype PID-22',
            'Error datatype PID-23',
        ], self::summary($outcome));
        $this->assertSame(
            "Component 'Time' (TS.1) value is not a valid DTM: '20260230' is not a date of the calendar.",
            $outcome->getFindings()[0]->message
        );
    }

    public function testDataTypeWithoutValidator(): void
    {
        // GTS: no validator, an Info. ST, ID and IS: no Datatype entry.
        [$outcome] = $this->check(self::pidWith([2 => 'AB', 15 => 'Y', 16 => 'F', 24 => 'R1']));

        $this->assertSame(['Info noDatatypeValidator PID-24'], self::summary($outcome));
        $this->assertSame(['PID-24'], array_values(array_map(
            static fn (string $entry): string => explode(' ', $entry)[1],
            array_filter(self::entries($outcome), static fn (string $entry): bool => str_starts_with($entry, 'Datatype'))
        )));
    }

    public function testValueOutsideItsTable(): void
    {
        // 0136 is an HL7 table, 0001 a User table.
        [$outcome] = $this->check(self::pidWith([15 => 'y', 16 => 'X']));

        $this->assertSame(['Error tableHL7 PID-15', 'Warning tableUser PID-16'], self::summary($outcome));
        $this->assertSame("Field 'Field 15' (PID.15) value (y) not in table 0136 (HL7 table).", $outcome->getFindings()[0]->message);
    }

    public function testValueOutsideATableClosedByTheProfile(): void
    {
        // IHE PAM FR closes table 0001.
        [$outcome] = $this->check(self::pidWith([15 => 'Y', 16 => 'A']), 'json-2.5-IHEPAMFR');

        $this->assertSame(['Error tableClosed PID-16'], self::summary($outcome));
        $this->assertSame("Field 'Field 16' (PID.16) value (A) not in table 0001 (User table, closed by the profile).", $outcome->getFindings()[0]->message);
    }

    public function testTableIsCheckedOnTheDecodedValue(): void
    {
        // \X59\ is "Y".
        [$outcome] = $this->check(self::pidWith([15 => '\X59\\']));

        $this->assertSame(['Error escapeNotAllowed PID-15'], self::summary($outcome));
        $this->assertContains("Table PID-15 ok Field 'Field 15' (PID.15) value (Y) exists in table 0136 (HL7 table).", self::entries($outcome));
    }

    public function testLocalCodeOfATableThatAcceptsThem(): void
    {
        [$outcome] = $this->check(self::pidWith([18 => 'Z01']));
        $this->assertSame([], self::summary($outcome));
        $this->assertContains("Table PID-18 ok Field 'Field 18' (PID.18) value (Z01) accepted as a local code of table 0003 (HL7 table).", self::entries($outcome));

        // Not in a table that does not accept them.
        [$outcome] = $this->check(self::pidWith([15 => 'Z01']));
        $this->assertSame(['Error tableHL7 PID-15'], self::summary($outcome));
    }

    public function testTableUnavailable(): void
    {
        [$outcome] = $this->check(self::pidWith([19 => 'X', 20 => 'X']));

        $this->assertSame(['Info tableUnavailable PID-19', 'Info tableUnavailable PID-20'], self::summary($outcome));
        $this->assertSame("Field 'Field 19' (PID.19) value (X) not checked: table ZZZZ is not in the profile.", $outcome->getFindings()[0]->message);
        $this->assertSame("Field 'Field 20' (PID.20) value (X) not checked: table 0300 has no codes in the profile.", $outcome->getFindings()[1]->message);
    }

    public function testDataTypeAndTableGiveOneFindingEach(): void
    {
        // An NM field with a table: not a number, and not in table 0136.
        [$outcome] = $this->check(self::pidWith([25 => 'X']));

        $this->assertSame(['Error datatype PID-25', 'Error tableHL7 PID-25'], self::summary($outcome));
    }

    public function testCodedElementWithoutCodingSystemUsesTheTableOfTheField(): void
    {
        [$outcome] = $this->check(self::pidWith([17 => 'M^Male']));
        $this->assertSame([], self::summary($outcome));

        [$outcome] = $this->check(self::pidWith([17 => 'X^Unknown']));
        $this->assertSame(['Warning tableUser PID-17.1'], self::summary($outcome));
    }

    public function testCodedElementAgainstTheTableItsCodingSystemDesignates(): void
    {
        // CE.3 = HL70136: CE.1 against table 0136, not table 0001 of the field.
        [$outcome] = $this->check(self::pidWith([17 => 'Y^Yes^HL70136']));
        $this->assertSame([], self::summary($outcome));

        [$outcome] = $this->check(self::pidWith([17 => 'M^Male^HL70136']));
        $this->assertSame(['Error tableHL7 PID-17.1'], self::summary($outcome));
        $this->assertSame(
            "Component 'Identifier' (CE.1) value (M) not in table 0136 (coding system 'HL70136') (HL7 table).",
            $outcome->getFindings()[0]->message
        );

        // HL79998: a table absent from the profile.
        [$outcome] = $this->check(self::pidWith([17 => 'M^Male^HL79998']));
        $this->assertSame(['Info tableUnavailable PID-17.1'], self::summary($outcome));
    }

    public function testCodedElementOfAnotherCodingSystemIsNotChecked(): void
    {
        // ISO3166 is in table 0396 (pattern ISOnnnn); CE.1 is not checked.
        [$outcome] = $this->check(self::pidWith([17 => 'FR^France^ISO3166']));

        $this->assertSame([], self::summary($outcome));
        $this->assertContains(
            "Table PID-17.1 ok Component 'Identifier' (CE.1) value (FR) not checked: coding system 'ISO3166' is not a table of the profile.",
            self::entries($outcome)
        );
    }

    public function testCodingSystemsOfThePamFrExample(): void
    {
        // PAM FR 2.11.2 section 6.6.13: not in table 0396 of HL7 2.5, added to
        // table 0396 of IHE PAM FR.
        [$outcome] = $this->check(self::pidWith([17 => 'FR^^ISO 3166 alpha-2']));
        $this->assertSame(['Error tableHL7 PID-17.3'], self::summary($outcome));

        [$outcome] = $this->check(self::pidWith([17 => 'FR^^ISO 3166 alpha-2']), 'json-2.5-IHEPAMFR');
        $this->assertSame([], self::summary($outcome));
    }

    public function testAlternateIdentifierAgainstTheTableOfItsCodingSystem(): void
    {
        // CE.4 against the table CE.6 designates.
        [$outcome] = $this->check(self::pidWith([17 => 'M^Male^^Q^^HL70136']));
        $this->assertSame(['Error tableHL7 PID-17.4'], self::summary($outcome));

        // CE.6 empty: CE.4 is not checked.
        [$outcome] = $this->check(self::pidWith([17 => 'M^Male^^Q']));
        $this->assertSame([], self::summary($outcome));
        $this->assertSame([], array_values(array_filter(
            self::entries($outcome),
            static fn (string $entry): bool => str_starts_with($entry, 'Table PID-17.4')
        )));
    }

    public function testCodedElementAsAComponent(): void
    {
        // XPN.9 (CE, table 0448 without codes): its CE.3, a sub-component,
        // designates the table of its CE.1.
        [$outcome] = $this->check(self::pidWith([10 => 'DOE^^^^^^^^Q']));
        $this->assertSame(['Info tableUnavailable PID-10.9.1'], self::summary($outcome));

        [$outcome] = $this->check(self::pidWith([10 => 'DOE^^^^^^^^Q&&HL70136']));
        $this->assertSame(['Error tableHL7 PID-10.9.1'], self::summary($outcome));
    }

    public function testPatternsOfTheCodingSystemTable(): void
    {
        foreach (['HL70136', 'ISO639', 'IBT0001', '99ABC', '99Z', 'L', 'LN'] as $codingSystem) {
            [$outcome] = $this->check(self::pidWith([17 => "X^^$codingSystem"]));
            $this->assertNotContains('Error tableHL7 PID-17.3', self::summary($outcome), $codingSystem);
        }

        foreach (['HL7136', 'HL7nnnn', 'ISO', '99', 'l'] as $codingSystem) {
            [$outcome] = $this->check(self::pidWith([17 => "X^^$codingSystem"]));
            $this->assertContains('Error tableHL7 PID-17.3', self::summary($outcome), $codingSystem);
        }
    }

    public function testCodedWithNoExceptionsAllowsNoValueOutsideItsTable(): void
    {
        [$outcome] = $this->check(self::pidWith([26 => 'Y^Yes']));
        $this->assertSame([], self::summary($outcome));

        // Outside an HL7 table, a User table, or a local code: an Error each time.
        [$outcome] = $this->check(self::pidWith([26 => 'X', 28 => 'X', 30 => 'Z01']));
        $this->assertSame(['Error tableClosed PID-26.1', 'Error tableClosed PID-28.1', 'Error tableClosed PID-30.1'], self::summary($outcome));
        $this->assertSame(
            "Component 'Identifier' (CNE.1) value (X) not in table 0136 (HL7 table, closed by the CNE data type).",
            $outcome->getFindings()[0]->message
        );
    }

    public function testCodedWithNoExceptionsRequiresItsIdentifier(): void
    {
        // Text may not replace the code: CNE.1 is R.
        [$outcome] = $this->check(self::pidWith([26 => '^Yes']));

        $this->assertSame(['Error requiredAbsent PID-26.1'], self::summary($outcome));
    }

    public function testCodedWithNoExceptionsAllowsNoLocalCodingSystem(): void
    {
        foreach (['L', '99LOC'] as $codingSystem) {
            [$outcome] = $this->check(self::pidWith([26 => "Y^Yes^$codingSystem"]));
            $this->assertSame(['Error tableClosed PID-26.1'], self::summary($outcome), $codingSystem);
        }
        $this->assertSame(
            "Component 'Identifier' (CNE.1) value (Y) has the local coding system '99LOC': a CNE allows no local value.",
            $outcome->getFindings()[0]->message
        );

        // An external coding system: CNE.1 is not checked.
        [$outcome] = $this->check(self::pidWith([26 => 'Y^Yes^LN']));
        $this->assertSame([], self::summary($outcome));
    }

    public function testAlternateIdentifierOfACodedWithNoExceptions(): void
    {
        // CNE.4 against the table CNE.6 designates, with the rules of the CNE.
        [$outcome] = $this->check(self::pidWith([26 => 'Y^Yes^^Q^^HL70136']));
        $this->assertSame(['Error tableClosed PID-26.4'], self::summary($outcome));

        // The alternate components may hold a local code.
        [$outcome] = $this->check(self::pidWith([26 => 'Y^Yes^^Q^Local^L']));
        $this->assertSame([], self::summary($outcome));
    }

    public function testCodedWithExceptionsAllowsLocalValues(): void
    {
        // Outside an HL7 table: a Warning.
        [$outcome] = $this->check(self::pidWith([27 => 'X']));
        $this->assertSame(['Warning tableUser PID-27.1'], self::summary($outcome));
        $this->assertSame(
            "Component 'Identifier' (CWE.1) value (X) not in table 0136 (HL7 table; the CWE data type allows local values).",
            $outcome->getFindings()[0]->message
        );

        // A local code (Z...) of a table that accepts them; a local coding system.
        [$outcome] = $this->check(self::pidWith([31 => 'Z01', 27 => 'LOC^Local^L']));
        $this->assertSame([], self::summary($outcome));
    }

    public function testCodedWithExceptionsInATableClosedByTheProfile(): void
    {
        // IHE PAM FR closes table 0001 (as ZBE-9, a CWE of a closed table).
        [$outcome] = $this->check(self::pidWith([29 => 'A']), 'json-2.5-IHEPAMFR');

        $this->assertSame(['Error tableClosed PID-29.1'], self::summary($outcome));
    }

    public function testCodedWithExceptionsTextOnlyAndDataMissing(): void
    {
        // Uncoded: text only. Data missing: a CWE status of table 0353.
        [$outcome] = $this->check(self::pidWith([27 => '^Wesnerian', 29 => 'NAV^Not available^HL70353']));
        $this->assertSame([], self::summary($outcome));

        [$outcome] = $this->check(self::pidWith([29 => 'NONE^Not available^HL70353']));
        $this->assertSame(['Warning tableUser PID-29.1'], self::summary($outcome));
    }

    /**
     * A PID segment with PID-1 and PID-8 valued (A, X~Y), and the given fields.
     *
     * @param array<int, string> $values Raw value by field position.
     *
     * @return string
     */
    private static function pidWith(array $values): string
    {
        $fields = [1 => 'A', 8 => 'X~Y'];
        foreach ($values as $position => $value) {
            $fields[$position] = $value;
        }

        $segment = 'PID';
        for ($position = 1; $position <= max(array_keys($fields)); $position++) {
            $segment .= '|' . ($fields[$position] ?? '');
        }

        return $segment;
    }

    /**
     * Run the content checks on a message MSH, then the given segments, on     * the structure MSH, PID, [PV1], with the data types of a profile directory.
     * MSH-1 and MSH-2 are defined as ST of length 1 and 4.
     *
     * @param string $directory Profile directory in profiles-next.
     *
     * @return array{0: ValidationResult, 1: ProfiledMessage}
     */
    private function check(string $segments, string $directory = 'json-2.5'): array
    {
        $msh = static fn (int $position, int $length): SegmentField => new SegmentField(
            $position, Usage::Required, new Cardinality(1, 1),
            new FieldDefinition("MSH.$position", '00000', 'ST', $length, null, "Field $position", '2')
        );
        $structure = new GroupNode('ROOT', Usage::Required, new Cardinality(1, 1), [
            new SegmentNode(Usage::Required, new Cardinality(1, 1), new SegmentDefinition('MSH', 'MSH', '2', [$msh(1, 1), $msh(2, 4)])),
            new SegmentNode(Usage::Required, new Cardinality(1, 1), self::pid()),
            new SegmentNode(Usage::Optional, new Cardinality(0, 1), new SegmentDefinition('PV1', 'PV1', '3', [])),
        ]);

        $message = $this->parse("MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01|1|P|2.5\r" . $segments);
        $result = new ValidationResult();
        $profileDirectory = ProfileDirectory::open(self::PROFILES . '/' . $directory);
        $recorder = new CheckRecorder($profileDirectory->rules, $result, 'ADT_A01');
        $profiled = new ProfiledMessage((new StructureMatcher())->match($structure, $message));

        (new ContentChecker($profileDirectory))->check($profiled, $message, $recorder);

        return [$result, $profiled];
    }

    private function parse(string $hl7): Message
    {
        $parser = new HL7Message();
        $parser->parse($hl7);
        $message = $parser->getMessage();
        $this->assertNotNull($message);

        return $message;
    }

    private function pidElement(ProfiledMessage $profiled): SegmentOccurrence
    {
        $pid = $profiled->root->segments()[1];
        $this->assertInstanceOf(SegmentOccurrence::class, $pid);

        return $pid;
    }

    /**
     * @param list<Finding>|null $findings Findings to summarize; all the findings of the result by default.
     *
     * @return list<string>
     */
    private static function summary(ValidationResult $result, ?array $findings = null): array
    {
        return array_map(
            static fn (Finding $finding): string => sprintf(
                '%s %s %s',
                $finding->severity->value,
                $finding->type->value,
                $finding->location?->toShortString() ?? '-'
            ),
            $findings ?? $result->getFindings()
        );
    }
    /**
     * TestReport entries as "Type Location ok|ko Description".
     *
     * @return list<string>
     */
    private static function entries(ValidationResult $result): array
    {
        return array_values(array_map(
            static fn (array $entry): string => "{$entry['Type']} {$entry['Location']} " . ($entry['Result'] ? 'ok' : 'ko') . " {$entry['Description']}",
            $result->getTestReport()
        ));
    }
}
