<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Profile\Rules;
use HL7v2\Validation\Check;
use HL7v2\Validation\Finding;
use HL7v2\Validation\ValidationResult;

/**
 * Records every check of the validation through a single method:
 * a TestReport entry for each check, and a Finding, with the severity of
 * rules.json, for each check that gives one.
 */
final class CheckRecorder
{
    /**
     * @param string $structure Message structure (ADT_A01): TestReport location
     *                          of a check that has no other position.
     */
    public function __construct(
        private readonly Rules $rules,
        private readonly ValidationResult $result,
        private readonly string $structure
    ) {
        //
    }

    /**
     * Record a check.
     *
     * @return Finding|null The finding added, for the Profiled message.
     */
    public function record(Check $check): ?Finding
    {
        $entry = [
            'Location'    => $check->location?->toShortString() ?? $check->profilePath ?? $this->structure,
            'Description' => $check->description,
            'Type'        => $check->type,
            'Result'      => $check->result,
        ];

        $finding = null;
        if ($check->finding !== null) {
            $severity = $this->rules->severity($check->finding);
            $entry['Severity'] = $severity->value;
            $finding = new Finding(
                $severity, $check->finding, $check->description,
                $check->location, $check->profilePath, $check->segment
            );
            $this->result->addFinding($finding);
        }

        $this->result->addTestReport($entry);

        return $finding;
    }
}
