<?php
declare(strict_types=1);

namespace HL7v2\Tests\Unit\Model;

use HL7v2\Model\Location;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function forms(): iterable
    {
        // input, long form, short form
        yield 'segment'                  => ['PV1', 'PV1[1]', 'PV1'];
        yield 'segment with rank'        => ['NK1[2]', 'NK1[2]', 'NK1[2]'];
        yield 'field'                    => ['PID-3', 'PID[1]-3[1]', 'PID-3'];
        yield 'repetition'               => ['PID-3[2]', 'PID[1]-3[2]', 'PID-3[2]'];
        yield 'component'                => ['PID-3.4', 'PID[1]-3[1].4', 'PID-3.4'];
        yield 'sub-component'            => ['PID-3.4.1', 'PID[1]-3[1].4.1', 'PID-3.4.1'];
        yield 'long form'                => ['PID[1]-3[1].4.1', 'PID[1]-3[1].4.1', 'PID-3.4.1'];
        yield 'ranks other than 1'       => ['OBX[3]-5[2].1.2', 'OBX[3]-5[2].1.2', 'OBX[3]-5[2].1.2'];
        yield 'segment rank only'        => ['NK1[2]-2.1', 'NK1[2]-2[1].1', 'NK1[2]-2.1'];
        yield 'MSH-1, field separator'   => ['MSH-1', 'MSH[1]-1[1]', 'MSH-1'];
        yield 'Z segment'                => ['ZBE-9.1', 'ZBE[1]-9[1].1', 'ZBE-9.1'];
        yield 'segment name with digit'  => ['IN1-17', 'IN1[1]-17[1]', 'IN1-17'];
    }

    #[DataProvider('forms')]
    public function testParseAndFormat(string $input, string $long, string $short): void
    {
        $location = Location::parse($input);

        $this->assertSame($long, $location->toLongString());
        $this->assertSame($short, $location->toShortString());
        $this->assertSame($long, (string) $location);
    }

    #[DataProvider('forms')]
    public function testRoundTrip(string $input, string $long, string $short): void
    {
        $this->assertSame($long, Location::parse($long)->toLongString());
        $this->assertSame($long, Location::parse($short)->toLongString());
    }

    public function testParsedProperties(): void
    {
        $location = Location::parse('OBX[3]-5[2].1.2');

        $this->assertSame('OBX', $location->segment);
        $this->assertSame(3, $location->segmentRank);
        $this->assertSame(5, $location->field);
        $this->assertSame(2, $location->repetition);
        $this->assertSame(1, $location->component);
        $this->assertSame(2, $location->subComponent);
    }

    public function testSegmentLocationHasNoField(): void
    {
        $location = Location::parse('PV1[2]');

        $this->assertNull($location->field);
        $this->assertNull($location->repetition);
        $this->assertNull($location->component);
        $this->assertNull($location->subComponent);
    }

    public function testConstructorDefaults(): void
    {
        $this->assertSame('PID[1]-3[1].4', (new Location('PID', field: 3, component: 4))->toLongString());
    }

    public function testEquals(): void
    {
        $this->assertTrue(Location::parse('PID-3')->equals(Location::parse('PID[1]-3[1]')));
        $this->assertTrue(Location::parse('PID-3')->equals(new Location('PID', field: 3)));
        $this->assertFalse(Location::parse('PID-3')->equals(Location::parse('PID-3[2]')));
        $this->assertFalse(Location::parse('PID-3')->equals(Location::parse('PID-3.1')));
        $this->assertFalse(Location::parse('NK1-2')->equals(Location::parse('NK1[2]-2')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function zeroIndexes(): iterable
    {
        yield 'segment rank' => ['PID[0]-3'];
        yield 'field'        => ['PID-0'];
        yield 'repetition'   => ['PID-3[0]'];
        yield 'component'    => ['PID-3.0'];
        yield 'sub-component' => ['PID-3.4.0'];
        yield 'old notation' => ['PID[2]-3[0].4.1'];
    }

    #[DataProvider('zeroIndexes')]
    public function testZeroIndexIsRefused(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('indexes start at 1; a segment is counted among the segments of the same name');

        Location::parse($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidForms(): iterable
    {
        yield 'empty'                 => [''];
        yield 'lower case'            => ['pid-3'];
        yield 'segment name too long' => ['PIDX-3'];
        yield 'segment name too short' => ['PI-3'];
        yield 'missing field'         => ['PID-'];
        yield 'wildcard'              => ['PID[*]-3'];
        yield 'too deep'              => ['PID-3.4.1.2'];
        yield 'negative'              => ['PID-[-1]'];
        yield 'spaces'                => [' PID-3'];
    }

    #[DataProvider('invalidForms')]
    public function testInvalidFormIsRefused(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid location');

        Location::parse($input);
    }

    public function testInvalidSegmentNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid segment name "pid"');

        new Location('pid');
    }

    public function testComponentNeedsAField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A repetition or a component needs a field.');

        new Location('PID', component: 4);
    }

    public function testRepetitionNeedsAField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A repetition or a component needs a field.');

        new Location('PID', repetition: 2);
    }

    public function testSubComponentNeedsAComponent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A sub-component needs a component.');

        new Location('PID', field: 3, subComponent: 1);
    }

    public function testNegativeIndexIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid field -1');

        new Location('PID', field: -1);
    }
}
