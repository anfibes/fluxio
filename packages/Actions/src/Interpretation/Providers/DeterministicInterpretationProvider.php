<?php

namespace Fluxio\Actions\Interpretation\Providers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Registry\IntentRegistry;

class DeterministicInterpretationProvider implements InterpretationProviderInterface
{
    public function __construct(
        private readonly IntentResolverInterface $resolver,
        private readonly IntentRegistry          $registry,
    ) {}

    public function interpret(string $text, InterpretationContext $context): NormalizedCommand
    {
        $parsed     = $this->resolver->resolve($text);
        $definition = $this->registry->find($parsed->intent);
        $confidence = $definition?->confidence ?? 0.3;

        return new NormalizedCommand(
            intent:     $parsed->intent,
            confidence: $confidence,
            sourceText: $text,
            locale:     $context->locale,
            entities:   $parsed->entities,
        );
    }
}
