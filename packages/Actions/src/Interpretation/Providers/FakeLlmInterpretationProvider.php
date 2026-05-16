<?php

namespace Fluxio\Actions\Interpretation\Providers;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;

/**
 * Deterministic fake provider for sandbox testing and provider-swap validation.
 * No external API calls are made. Not registered as the default provider.
 */
class FakeLlmInterpretationProvider implements InterpretationProviderInterface
{
    /** @var array<string, NormalizedCommand> */
    private array $responses = [];

    public function addResponse(string $text, NormalizedCommand $command): void
    {
        $this->responses[mb_strtolower($text)] = $command;
    }

    public function interpret(string $text, InterpretationContext $context): NormalizedCommand
    {
        return $this->responses[mb_strtolower($text)] ?? new NormalizedCommand(
            intent:     'unknown',
            confidence: 0.0,
            sourceText: $text,
            locale:     $context->locale,
        );
    }
}
