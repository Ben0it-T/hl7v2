<?php
declare(strict_types=1);

namespace HL7v2\Validation;

class ValidationResult
{
    /**
     * @var string[]
     */
    private array $messages = [];

    /**
     * Add new message.
     *
     * @param string $message
     */
    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Get messages.
     *
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }




    /**
     * @var array<int, array<string, mixed>>
     */
    private array $testReport = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $validationReport = [];

    /**
     * Validation error count.
     */
    private int $errorCount = 0;

    /**
     * Add test report entry.
     *
     * @param array<string, mixed> $entry
     *
     * Expected structure:
     * array{
     *     Location: string,
     *     Description: string,
     *     Type: string,
     *     Result: bool
     * }
     */
    public function addTestReport(array $entry): void
    {
        $this->testReport[] = $entry;

        if (!$entry['Result']) {
            $this->errorCount++;
        }
    }

    /**
     * Add validation report entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return int Index of the inserted entry.
     */
    public function addValidationReport(array $entry): int
    {
        $index = count($this->validationReport);
        $this->validationReport[$index] = $entry;

        return $index;
    }

    /**
     * Update validation report entry.
     *
     * @param int $reportIndex
     * @param array<string,mixed> $elements
     */
    public function updateValidationReport(int $reportIndex, array $elements): void
    {
        if (!isset($this->validationReport[$reportIndex])) {
            throw new \OutOfBoundsException("Validation report entry {$reportIndex} does not exist.");
        }

        $this->validationReport[$reportIndex] = array_merge(
            $this->validationReport[$reportIndex],
            $elements
        );
    }

    /**
     * Get test report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTestReport(): array
    {
        return $this->testReport;
    }

    /**
     * Get validation report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getValidationReport(): array
    {
        return $this->validationReport;
    }

    /**
     * Get validation error count.
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Check if message has errors according to profile.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /**
     * Check if message is valid according to profile.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->hasErrors();
    }

}
