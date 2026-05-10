<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\NormalizedMutation;

interface RefinementInterpreterInterface
{
    /**
     * Extract normalized field mutations from a refinement text.
     *
     * @return NormalizedMutation[]
     */
    public function interpret(string $text): array;
}
