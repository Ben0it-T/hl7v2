<?php
declare(strict_types=1);

namespace HL7v2\Validation;

use HL7v2\Validation\ProfiledMessage;
use HL7v2\Validation\ValidationResult;

/**
 * Outcome of the validation of a message: its result and its Profiled
 * message, built by the same validation.
 */
final readonly class ValidationOutcome
{
    /**
     * @param ValidationResult $result TestReport (every check done) and findings.
     * @param ProfiledMessage $profiledMessage The message in the group tree of
     *                                         its profile, with the findings
     *                                         of each element.
     */
    public function __construct(
        public ValidationResult $result,
        public ProfiledMessage $profiledMessage
    ) {
        //
    }
}
