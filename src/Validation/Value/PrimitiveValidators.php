<?php
declare(strict_types=1);

namespace HL7v2\Validation\Value;

use HL7v2\Validation\Value\Primitive\DTMValidator;
use HL7v2\Validation\Value\Primitive\DTValidator;
use HL7v2\Validation\Value\Primitive\NMValidator;
use HL7v2\Validation\Value\Primitive\SIValidator;
use HL7v2\Validation\Value\Primitive\TMValidator;
use HL7v2\Validation\Value\PrimitiveValidator;

/**
 * The validators of the primitive data types, by data type.
 *
 * The same validators serve every HL7 version; they follow the definitions
 * of HL7 v2.5 (DTM exists from 2.5 on).
 *
 * ID and IS have no validator: their value is checked against their table.
 * ST, TX, FT and GTS are judged by their written characters (DisplayableText).
 * A primitive data type without validator is not checked (TN...).
 */
final readonly class PrimitiveValidators
{
    /**
     * @param array<string, PrimitiveValidator> $validators Validator by data type.
     */
    private function __construct(
        private array $validators
    ) {
        //
    }

    /**
     * The validators of the HL7 primitive data types.
     *
     * @return PrimitiveValidators
     */
    public static function standard(): self
    {
        return new self([
            'DT'  => new DTValidator(),
            'DTM' => new DTMValidator(),
            'NM'  => new NMValidator(),
            'SI'  => new SIValidator(),
            'TM'  => new TMValidator(),
        ]);
    }

    /**
     * Get the validator of a primitive data type.
     *
     * @param string $dataType Primitive data type (NM, DTM...).
     *
     * @return PrimitiveValidator|null Null when the data type has no validator.
     */
    public function get(string $dataType): ?PrimitiveValidator
    {
        return $this->validators[$dataType] ?? null;
    }
}
