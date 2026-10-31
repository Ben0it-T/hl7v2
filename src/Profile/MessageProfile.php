<?php
declare(strict_types=1);

namespace HL7v2\Profile;

use HL7v2\Profile\Definition\GroupNode;
use HL7v2\Profile\ProfileDirectory;

/**
 * A message profile (ADT-A01-ADT_A01): its message structure, in the profile
 * directory it belongs to (data types, rules).
 */
final readonly class MessageProfile
{
    public function __construct(
        public string $name,
        public string $structure,
        public GroupNode $root,
        public ProfileDirectory $directory
    ) {
        //
    }
}
