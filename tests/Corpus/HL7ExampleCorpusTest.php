<?php
declare(strict_types=1);

namespace HL7v2\Tests\Corpus;

use HL7v2\Exception\HL7Exception;
use HL7v2\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Runs the example messages of the HL7 standard through the current validator.
 *
 * The suite is skipped when the `hl7-examples/` directory is absent or empty.
 *
 * An example whose expected validity has not been reviewed yet (expected.valid = null)
 * only checks that the validator runs.
 */
final class HL7ExampleCorpusTest extends CorpusTestCase
{
    private const EXAMPLES = __DIR__ . '/hl7-examples';

    /**
     * @return iterable<string, array{CorpusCase|null}>
     */
    public static function cases(): iterable
    {
        $cases = is_dir(self::EXAMPLES) ? CorpusCase::loadAll(self::EXAMPLES) : [];
        if ($cases === []) {
            yield 'no local HL7 examples' => [null];
        }
        foreach ($cases as $id => $case) {
            yield $id => [$case];
        }
    }

    #[DataProvider('cases')]
    public function testValidity(?CorpusCase $case): void
    {
        if ($case === null) {
            $this->markTestSkipped('No local HL7 examples.');
        }

        try {
            $result = $this->validate($case);
        } catch (HL7Exception $e) {
            if (preg_match('/^(Profile not found|Message structure not found)/', $e->getMessage()) === 1) {
                $this->markTestSkipped($e->getMessage() . ' (no profile in profiles/json-' . $case->profileId . ').');
            }
            throw $e;
        }

        if ($case->expectedValid === null) {
            $this->assertInstanceOf(ValidationResult::class, $result);

            return;
        }
        $this->assertExpectedValidity($case, $result);
    }
}
