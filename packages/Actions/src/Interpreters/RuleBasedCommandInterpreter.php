<?php

namespace Fluxio\Actions\Interpreters;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\NormalizedCommand;

class RuleBasedCommandInterpreter implements CommandInterpreterInterface
{
    public function __construct(private readonly IntentResolverInterface $resolver) {}

    public function interpret(string $text): NormalizedCommand
    {
        $parsed = $this->resolver->resolve($text);

        $confidence = match ($parsed->intent) {
            'create_task'   => 0.9,
            'schedule_call' => 0.7,
            default         => 0.3,
        };

        return new NormalizedCommand(
            intent:     $parsed->intent,
            confidence: $confidence,
            sourceText: $text,
            locale:     'en',
            entities:   $parsed->entities,
        );
    }
}
