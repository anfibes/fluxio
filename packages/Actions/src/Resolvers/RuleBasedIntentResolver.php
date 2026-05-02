<?php

namespace Fluxio\Actions\Resolvers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ParsedIntent;

class RuleBasedIntentResolver implements IntentResolverInterface
{
    public function resolve(string $text): ParsedIntent
    {
        $lower = strtolower($text);

        $intent = match (true) {
            str_contains($lower, 'task') => 'create_task',
            str_contains($lower, 'call') => 'schedule_call',
            default => 'unknown',
        };

        $entities = [];

        if (str_contains($text, 'Rossini')) {
            $entities['lead'] = 'Rossini';
        }

        return new ParsedIntent($intent, $entities);
    }
}
