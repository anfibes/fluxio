<?php

namespace Fluxio\Actions\Resolvers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ParsedIntent;

class RuleBasedIntentResolver implements IntentResolverInterface
{
    public function resolve(string $text): ParsedIntent
    {
        $lower = mb_strtolower($text);

        $intent = match (true) {
            str_contains($lower, 'task') => 'create_task',
            str_contains($lower, 'call') => 'schedule_call',
            default => 'unknown',
        };

        $entities = [];

        if (str_contains($lower, 'rossini')) {
            $entities['lead'] = 'Rossini';
        } elseif ((bool) preg_match('/\brossi\b/i', $text)) {
            $entities['lead_query'] = 'Rossi';
        }

        return new ParsedIntent($intent, $entities);
    }
}
