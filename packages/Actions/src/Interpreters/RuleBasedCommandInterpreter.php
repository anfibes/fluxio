<?php

namespace Fluxio\Actions\Interpreters;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Registry\IntentRegistry;

class RuleBasedCommandInterpreter implements CommandInterpreterInterface
{
    public function __construct(
        private readonly IntentResolverInterface $resolver,
        private readonly IntentRegistry $registry,
    ) {}

    public function interpret(string $text): NormalizedCommand
    {
        $parsed = $this->resolver->resolve($text);

        $definition = $this->registry->find($parsed->intent);
        $confidence = $definition?->confidence ?? 0.3;

        return new NormalizedCommand(
            intent:     $parsed->intent,
            confidence: $confidence,
            sourceText: $text,
            locale:     'en',
            entities:   $parsed->entities,
            warnings:   $parsed->warnings,
        );
    }
}
