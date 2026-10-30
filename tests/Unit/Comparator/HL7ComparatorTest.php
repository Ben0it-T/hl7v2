<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Comparator;

use HL7v2\Comparator\ComparisonResult;
use HL7v2\Comparator\Difference;
use HL7v2\Comparator\HL7Comparator;
use HL7v2\Comparator\IgnoreRuleParser;
use HL7v2\Parser\HL7Parser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HL7ComparatorTest extends TestCase
{
    private const MSH = "MSH|^~\\&|APP|FAC|APP|FAC|20260101120000||ADT^A01^ADT_A01|1|P|2.5\r";

    public function testEmptyElementsRemovedAreFormDifferences(): void
    {
        $result = $this->compare('PID|1||1^^^AUTH||DOE^JANE^^||', 'PID|1||1^^^AUTH||DOE^JANE');

        $this->assertSame(
            [
                ['PID-5.3', 'EMPTY_REMOVED'],
                ['PID-5.4', 'EMPTY_REMOVED'],
                ['PID-6', 'EMPTY_REMOVED'],
                ['PID-7', 'EMPTY_REMOVED'],
            ],
            $this->summary($result)
        );
        $this->assertFalse($result->isIdentical());
        $this->assertSame([], $result->getDataDifferences());
    }

    public function testEmptyRepetitionAdded(): void
    {
        $result = $this->compare('PID|1||1^^^AUTH', 'PID|1||1^^^AUTH~');

        $this->assertSame([['PID-3[2]', 'EMPTY_ADDED']], $this->summary($result));
    }

    public function testEmptySegmentAdded(): void
    {
        $result = $this->compare('PID|1', "PID|1\rZZA");

        $this->assertSame([['ZZA', 'EMPTY_ADDED']], $this->summary($result));
    }

    public function testDataDifferencesExcludeFormDifferences(): void
    {
        $result = $this->compare('PID|1||1||DOE^JANE', 'PID|1||1||DOE^JOAN||');

        $this->assertCount(3, $result->getDifferences());
        $this->assertSame(
            [['PID-5.2.1', 'CHANGED']],
            $this->summary($result, $result->getDataDifferences())
        );
    }

    public function testSecondSegmentOfTheSameNameIsRanked(): void
    {
        $result = $this->compare("NK1|1|DOE^JOHN\rNK1|2|DOE^ANN", "NK1|1|DOE^JOHN\rNK1|2|DOE^ANNA");

        $this->assertSame([['NK1[2]-2.2.1', 'CHANGED']], $this->summary($result));
    }

    public function testDifferencesFollowMessageOrder(): void
    {
        $result = $this->compare(
            "PID|1||100001^^^HOSP^PI||DOE^JANE^^^^^L||19800214|F\rNK1|1|DOE^JOHN|SPO\rPV1|1|I|WARD1^101^1",
            "PID|1||100001^^^HOSP^PI~200001^^^HOSP^AN||DOE^JOAN^^^^^L||19800214|F||\r"
            . "NK1|1|DOE^JOHN|SPO\rNK1|2|DOE^ANN|CHD\rPV1|1|I|WARD1^102^1"
        );

        $this->assertSame(
            [
                ['PID-3[2].1.1', 'ADDED'],
                ['PID-3[2].4.1', 'ADDED'],
                ['PID-3[2].5.1', 'ADDED'],
                ['PID-5.2.1', 'CHANGED'],
                ['PID-9', 'EMPTY_ADDED'],
                ['PID-10', 'EMPTY_ADDED'],
                ['NK1[2]-1.1.1', 'ADDED'],
                ['NK1[2]-2.1.1', 'ADDED'],
                ['NK1[2]-2.2.1', 'ADDED'],
                ['NK1[2]-3.1.1', 'ADDED'],
                ['PV1-3.2.1', 'CHANGED'],
            ],
            $this->summary($result)
        );
    }

    public function testElementsOnBothSidesKeepTheLeftOrder(): void
    {
        // A segment removed on the right and one added on the right, at different places.
        $result = $this->compare("ZZA|X\rPID|1\rPV1|1", "PID|1\rZZB|Y\rPV1|1");

        $this->assertSame(
            [['ZZA-1.1.1', 'REMOVED'], ['ZZB-1.1.1', 'ADDED']],
            $this->summary($result)
        );
    }

    public function testElementAddedRightAfterMsh(): void
    {
        $result = $this->compare('PID|1', "ZZA|X\rPID|1");

        $this->assertSame([['ZZA-1.1.1', 'ADDED']], $this->summary($result));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function ignoreRules(): iterable
    {
        // rule, number of differences left (2 without rule: NK1[2]-2.2.1 and ZZA-1.1.1); -1: refused
        yield 'whole segment'            => ['ZZA', 1];
        yield 'segment with rank'        => ['NK1[2]', 1];
        yield 'other rank'               => ['NK1[1]', 2];
        yield 'field, every rank'        => ['NK1-2', 1];
        yield 'wildcards'                => ['NK1[*]-2[*].2', 1];
        yield 'one sub-component'        => ['NK1[2]-2[1].2.1', 1];
        yield 'invalid rule'             => ['NK1-2.2.1.', -1];
    }

    #[DataProvider('ignoreRules')]
    public function testIgnoreRules(string $rule, int $remaining): void
    {
        if ($remaining < 0) {
            $this->expectException(InvalidArgumentException::class);
        }

        $result = $this->compare(
            "NK1|1|DOE^JOHN\rNK1|2|DOE^ANN\rZZA|X",
            "NK1|1|DOE^JOHN\rNK1|2|DOE^ANNA\rZZA|Y",
            [$rule]
        );

        $this->assertCount($remaining, $result->getDifferences());
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function wildcardRules(): iterable
    {
        // rules, short locations of the differences left
        yield 'no rule' => [[], [
            'NK1-5.12.1', 'NK1-5[2].12.1',
            'NK1[2]-2.2.1', 'NK1[2]-5.12.1', 'NK1[2]-5[2].12.1',
        ]];
        yield 'every phone number of every NK1' => [['NK1[*]-5[*].12'], ['NK1[2]-2.2.1']];
        yield 'same rule without *' => [['NK1-5.12'], ['NK1[2]-2.2.1']];
        yield 'second repetition of every NK1' => [['NK1[*]-5[2]'], [
            'NK1-5.12.1', 'NK1[2]-2.2.1', 'NK1[2]-5.12.1',
        ]];
        yield 'every repetition of the second NK1' => [['NK1[2]-5[*]'], [
            'NK1-5.12.1', 'NK1-5[2].12.1', 'NK1[2]-2.2.1',
        ]];
        yield 'first NK1, first repetition only' => [['NK1[1]-5[1]'], [
            'NK1-5[2].12.1', 'NK1[2]-2.2.1', 'NK1[2]-5.12.1', 'NK1[2]-5[2].12.1',
        ]];
        yield 'second NK1, everything' => [['NK1[2]'], ['NK1-5.12.1', 'NK1-5[2].12.1']];
    }

    /**
     * @param list<string> $rules
     * @param list<string> $remaining
     */
    #[DataProvider('wildcardRules')]
    public function testWildcardRules(array $rules, array $remaining): void
    {
        $result = $this->compare(
            "NK1|1|DOE^JOHN|SPO||^PRN^PH^^^^^^^^^0102030405~^WPN^PH^^^^^^^^^0304050607\r"
            . "NK1|2|DOE^ANN|CHD||^PRN^PH^^^^^^^^^0102030405~^PRN^CP^^^^^^^^^0602030405",
            "NK1|1|DOE^JOHN|SPO||^PRN^PH^^^^^^^^^0111111111~^WPN^PH^^^^^^^^^0322222222\r"
            . "NK1|2|DOE^ANNA|CHD||^PRN^PH^^^^^^^^^0133333333~^PRN^CP^^^^^^^^^0644444444",
            $rules
        );

        $this->assertSame(
            $remaining,
            array_map(static fn (array $difference): string => $difference[0], $this->summary($result))
        );
    }

    public function testRuleDeeperThanAFormDifferenceDoesNotIgnoreIt(): void
    {
        // PID-9.1 is a component rule; the difference is on field PID-9.
        $result = $this->compare('PID|1', 'PID|1||||||||', ['PID-9.1']);

        $this->assertContains(['PID-9', 'EMPTY_ADDED'], $this->summary($result));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'segment rank 0' => ['PID[0]-3'];
        yield 'field 0'        => ['PID-0'];
        yield 'repetition 0'   => ['PID-3[0]'];
        yield 'component 0'    => ['PID-3.0'];
        yield 'old notation'   => ['PID[2]-3[0].4.1'];
    }

    #[DataProvider('invalidRules')]
    public function testIndexZeroIsRefused(string $rule): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('indexes start at 1; a segment is counted among the segments of the same name');

        (new IgnoreRuleParser())->parse($rule);
    }

    public function testInvalidRuleIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ignore rule "pid-3".');

        (new IgnoreRuleParser())->parse('pid-3');
    }

    /**
     * @param list<string> $ignoreRules
     */
    private function compare(string $left, string $right, array $ignoreRules = []): ComparisonResult
    {
        $parser = new HL7Parser();

        return (new HL7Comparator())->compare(
            $parser->parse(self::MSH . $left),
            $parser->parse(self::MSH . $right),
            $ignoreRules
        );
    }

    /**
     * @param list<Difference>|null $differences
     *
     * @return list<array{string, string}> Short location and type of each difference.
     */
    private function summary(ComparisonResult $result, ?array $differences = null): array
    {
        $summary = [];
        foreach ($differences ?? $result->getDifferences() as $difference) {
            $summary[] = [$difference->getLocation()->toShortString(), $difference->getType()->name];
        }

        return $summary;
    }
}
