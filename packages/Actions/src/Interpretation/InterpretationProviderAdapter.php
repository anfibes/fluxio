<?php

namespace Fluxio\Actions\Interpretation;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;

/**
 * Bridges InterpretationProviderInterface to CommandInterpreterInterface so that
 * ActionInterpreterService needs no changes while the provider layer is swappable.
 * A default InterpretationContext is constructed here; future callers that have
 * user/locale context can supply it by calling the provider directly.
 */
class InterpretationProviderAdapter implements CommandInterpreterInterface
{
    public function __construct(
        private readonly InterpretationProviderInterface $provider,
    ) {}

    public function interpret(string $text): NormalizedCommand
    {
        return $this->provider->interpret($text, new InterpretationContext());
    }
}
