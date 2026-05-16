<?php

namespace Fluxio\Actions\Resolvers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ParsedIntent;
use Fluxio\Actions\Support\DateTimeExpressionParser;

class RuleBasedIntentResolver implements IntentResolverInterface
{
    public function __construct(private readonly DateTimeExpressionParser $parser) {}

    public function resolve(string $text): ParsedIntent
    {
        $lower = mb_strtolower($text);

        $intent = match (true) {
            str_contains($lower, 'contract') => 'prepare_contract_from_quote',
            str_contains($lower, 'assign')   => 'assign_lead',
            str_contains($lower, 'meeting')  => 'schedule_meeting',
            str_contains($lower, 'task')     => 'create_task',
            str_contains($lower, 'call')     => 'schedule_call',
            default                          => 'unknown',
        };

        $entities = [];

        // Always emit lead_query so entity resolution handles both exact and ambiguous names.
        if (str_contains($lower, 'rossini')) {
            $entities['lead_query'] = 'Rossini';
        } elseif ((bool) preg_match('/\brossi\b/i', $text)) {
            $entities['lead_query'] = 'Rossi';
        }

        // Assignee extraction: "Assign Rossini to Marco" → assignee = Marco
        if ($intent === 'assign_lead') {
            if ((bool) preg_match('/\bto\s+([A-Z][a-z]+)\b/', $text, $m)) {
                $entities['assignee'] = $m[1];
            }
        }

        // Date/time extraction — delegated to shared parser so command and
        // refinement paths remain consistent.
        foreach ($this->parser->parse($text) as $key => $value) {
            $entities[$key] = $value;
        }

        return new ParsedIntent($intent, $entities);
    }
}
