<?php

namespace Fluxio\Actions\Interpretation\Contracts;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;

interface InterpretationProviderInterface
{
    public function interpret(string $text, InterpretationContext $context): NormalizedCommand;
}
