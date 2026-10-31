<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Runs every forged message of the corpus through the current validator.
 * The expected result comes from the specification.
 */
final class MessageCorpusTest extends CorpusTestCase
{
    private const MESSAGES = __DIR__ . '/messages';
    private const SNAPSHOTS = __DIR__ . '/snapshots/messages';

    /**
     * @return iterable<string, array{CorpusCase}>
     */
    public static function cases(): iterable
    {
        foreach (CorpusCase::loadAll(self::MESSAGES) as $id => $case) {
            yield $id => [$case];
        }
    }

    #[DataProvider('cases')]
    public function testValidity(CorpusCase $case): void
    {
        $this->assertNotNull($case->expectedValid, 'A forged case must state expected.valid.');
        $this->assertExpectedValidity($case, $this->validate($case));
    }

    #[DataProvider('cases')]
    public function testFindings(CorpusCase $case): void
    {
        $this->assertExpectedFindings($case, $this->validate($case));
    }

    #[DataProvider('cases')]
    public function testSnapshot(CorpusCase $case): void
    {
        $this->assertSnapshots($this->validateMessage($case), self::SNAPSHOTS . '/' . $case->id);
    }
}
