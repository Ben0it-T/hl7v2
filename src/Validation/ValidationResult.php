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
     */
    public function addValidationReport(array $entry): void
    {
        $this->validationReport[] = $entry;
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
