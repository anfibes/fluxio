<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ActionProposal;
use Illuminate\Support\Str;

class ActionInterpreterService
{
    public function __construct(private readonly IntentResolverInterface $resolver) {}

    public function interpret(string $text): ActionProposal
    {
        $parsed = $this->resolver->resolve($text);

        $confidence = match ($parsed->intent) {
            'create_task' => 0.9,
            'schedule_call' => 0.7,
            default => 0.3,
        };

        [$status, $missing] = match ($parsed->intent) {
            'create_task' => [
                isset($parsed->entities['lead']) ? 'ready' : 'draft',
                [],
            ],
            'schedule_call' => ['draft', ['date', 'time']],
            default => ['draft', []],
        };

        return new ActionProposal(
            id: (string) Str::uuid(),
            intent: $parsed->intent,
            status: $status,
            confidence: $confidence,
            source_text: $text,
            entities: $parsed->entities,
            missing: $missing,
            needs_confirmation: true,
        );
    }
}
