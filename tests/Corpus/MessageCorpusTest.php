<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use HL7v2\HL7Message;
use HL7v2\Profile\HL7TableLoader;
use HL7v2\Profile\ProfileLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs every forged message of the corpus through the current validator,
 * against the profiles in profiles/ (old format).
 *
 * The expected result comes from the specification. When the validator
 * disagrees and the case documents a known bug, the test is marked
 * incomplete instead of failing, so the bug is never frozen as expected.
 */
final class MessageCorpusTest extends TestCase
{
    private const MESSAGES = __DIR__ . '/messages';
    private const PROFILES = __DIR__ . '/../../profiles';

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
        $hl7 = new HL7Message();
        $hl7->parse($case->message);
        $message = $hl7->getMessage();
        $this->assertNotNull($message);

        $profile = (new ProfileLoader(self::PROFILES))->load(
            $case->profileId,
            $message->getMessageCode(),
            $message->getTriggerEvent(),
            $message->getStructure()
        );
        $tables = (new HL7TableLoader(self::PROFILES))->load($case->profileId);
        $result = $hl7->validateWith($profile, $tables);

        if ($result->isValid() !== $case->expectedValid && $case->knownBug !== null) {
            $this->markTestIncomplete('Known bug: ' . $case->knownBug);
        }

        $this->assertSame(
            $case->expectedValid,
            $result->isValid(),
            $case->title . "\n" . $this->describe($result->getTestReport())
        );
    }

    /**
     * @param array<int, array<string, mixed>> $report
     */
    private function describe(array $report): string
    {
        $lines = [];
        foreach ($report as $entry) {
            if (($entry['Result'] ?? true) === false) {
                $lines[] = sprintf('- %s: %s', (string) ($entry['Location'] ?? ''), (string) ($entry['Description'] ?? ''));
            }
        }

        return implode("\n", $lines);
    }
}
