<?php

namespace Fluxio\Actions\Exceptions;

use Fluxio\Actions\DTO\NormalizedCommand;
use RuntimeException;

class InvalidNormalizedCommandException extends RuntimeException
{
    /** @param list<string> $validationErrors */
    public function __construct(
        public readonly NormalizedCommand $command,
        public readonly array             $validationErrors,
    ) {
        parent::__construct(
            'Interpretation provider returned an invalid NormalizedCommand: ' . implode('; ', $validationErrors),
        );
    }
}
