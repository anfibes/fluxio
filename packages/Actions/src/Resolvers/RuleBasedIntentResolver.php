<?php

namespace Fluxio\Actions\Resolvers;

use Carbon\Carbon;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ParsedIntent;

class RuleBasedIntentResolver implements IntentResolverInterface
{
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

        if (str_contains($lower, 'rossini')) {
            $entities['lead'] = 'Rossini';
        } elseif ((bool) preg_match('/\brossi\b/i', $text)) {
            $entities['lead_query'] = 'Rossi';
        }

        // Assignee extraction: "Assign Rossini to Marco" → assignee = Marco
        if ($intent === 'assign_lead') {
            if ((bool) preg_match('/\bto\s+([A-Z][a-z]+)\b/', $text, $m)) {
                $entities['assignee'] = $m[1];
            }
        }

        // Date extraction — mirrors RuleBasedRefinementInterpreter, applied at command time
        // so that full commands like "Schedule a meeting with Rossini tomorrow at 4pm"
        // produce a ready proposal without requiring a separate refinement step.
        if (str_contains($lower, 'tomorrow')) {
            $entities['date'] = now()->addDay()->toDateString();
        } else {
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                if ((bool) preg_match('/\b' . $day . '\b/i', $lower)) {
                    $entities['date'] = Carbon::parse('next ' . $day)->toDateString();
                    break;
                }
            }
        }

        // Time extraction — same pattern as RuleBasedRefinementInterpreter
        if ((bool) preg_match('/\bat\s+(\d{1,2})(?:[.:](\d{2}))?\s*(am|pm)?\b/i', $text, $m)) {
            $hour = (int) $m[1];
            $min  = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $ampm = mb_strtolower($m[3] ?? '');
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }
            $entities['time'] = sprintf('%02d:%02d', $hour, $min);
        } elseif ((bool) preg_match('/\bmorning\b/i', $text)) {
            $entities['time'] = '09:00';
        }

        return new ParsedIntent($intent, $entities);
    }
}
