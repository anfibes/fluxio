<?php

namespace Fluxio\Actions\Interpretation;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Exceptions\InvalidNormalizedCommandException;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Validation\NormalizedCommandValidator;

/**
 * Bridges InterpretationProviderInterface to CommandInterpreterInterface so that
 * ActionInterpreterService needs no changes while the provider layer is swappable.
 * A default InterpretationContext is constructed here; future callers that have
 * user/locale context can supply it by calling the provider directly.
 *
 * Every NormalizedCommand returned by the provider is validated before it enters
 * the proposal pipeline. Invalid provider output throws InvalidNormalizedCommandException
 * so malformed responses cannot silently corrupt proposal state.
 */
class InterpretationProviderAdapter implements CommandInterpreterInterface
{
    public function __construct(
        private readonly InterpretationProviderInterface $provider,
        private readonly NormalizedCommandValidator      $validator,
    ) {}

    public function interpret(string $text): NormalizedCommand
    {
        $command = $this->provider->interpret($text, new InterpretationContext());
        $result  = $this->validator->validate($command);

        if (! $result->valid) {
            throw new InvalidNormalizedCommandException($command, $result->errors);
        }

        return $command;
    }
}
