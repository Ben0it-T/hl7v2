<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Validation\Value;

use HL7v2\Profile\Definition\TableDefinition;
use HL7v2\Profile\Definition\TableType;
use HL7v2\Validation\Value\TableMembership;
use PHPUnit\Framework\TestCase;

/**
 * Membership of a value in a table: codes compared as written; the patterns
 * of table 0396 (HL7 v2.5 section 2.17.4).
 */
final class TableMembershipTest extends TestCase
{
    public function testCodesAreCaseSensitive(): void
    {
        $table = new TableDefinition('0001', 'Administrative Sex', TableType::User, ['F', 'M', 'U']);
        $membership = new TableMembership();

        $this->assertTrue($membership->contains($table, 'F'));
        $this->assertFalse($membership->contains($table, 'f'));
        $this->assertFalse($membership->contains($table, 'X'));
    }

    public function testPatternsOfTheCodingSystemTable(): void
    {
        $table = new TableDefinition('0396', 'Coding system', TableType::HL7, [
            '99zzz or L', '99zzz', 'HL7nnnn', 'IBTnnnn', 'ISOnnnn', 'ISO+', 'LN',
        ]);
        $membership = new TableMembership();

        foreach (['HL70001', 'HL70396', 'ISO639', 'ISO3166', 'IBT0001', '99ABC', '99Z', '99zzz', 'L', 'ISO+', 'LN'] as $value) {
            $this->assertTrue($membership->contains($table, $value), $value);
        }
        foreach (['HL7001', 'HL700001', 'HL7nnnn', 'ISOnnnn', 'ISO', 'ISO 3166', 'IBT', '99', '99-A', '99zzz or L', 'l', 'SNM'] as $value) {
            $this->assertFalse($membership->contains($table, $value), $value);
        }
    }

    public function testLocalCodingSystems(): void
    {
        $membership = new TableMembership();

        foreach (['L', '99LOC', '99Z', '99zzz'] as $codingSystem) {
            $this->assertTrue($membership->isLocalCodingSystem($codingSystem), $codingSystem);
        }
        foreach (['l', '99', 'LN', 'HL70001', '99-A', 'L1'] as $codingSystem) {
            $this->assertFalse($membership->isLocalCodingSystem($codingSystem), $codingSystem);
        }
    }

    public function testPatternsAreReadInTableCodingSystemOnly(): void
    {
        // Table 0203 holds "NNxxx", compared as written.
        $table = new TableDefinition('0203', 'Identifier type', TableType::User, ['NNxxx', 'HL7nnnn']);
        $membership = new TableMembership();

        $this->assertTrue($membership->contains($table, 'NNxxx'));
        $this->assertTrue($membership->contains($table, 'HL7nnnn'));
        $this->assertFalse($membership->contains($table, 'HL70001'));
    }

    public function testCodeLWithoutTheLocalPatternEntry(): void
    {
        // HL7 2.4: "99zzz or L" without a separate L.
        $table = new TableDefinition('0396', 'Coding system', TableType::User, ['99zzz or L', 'HL7nnnn']);
        $membership = new TableMembership();

        $this->assertTrue($membership->contains($table, 'L'));
        $this->assertTrue($membership->contains($table, '99LOC'));
    }
}
