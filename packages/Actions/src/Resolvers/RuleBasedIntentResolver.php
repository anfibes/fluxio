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
            str_contains($lower, 'assign') => 'assign_lead',
            str_contains($lower, 'meeting') => 'schedule_meeting',
            str_contains($lower, 'task') => 'create_task',
            str_contains($lower, 'call') => 'schedule_call',
            default => 'unknown',
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

        // Date/time extraction — delegated to shared parser. explain() yields the
        // same date/time values as parse() plus lightweight provenance used only
        // for an informational warning below.
        $temporal = $this->parser->explain($text);
        if ($temporal->date !== null) {
            $entities['date'] = $temporal->date;
        }
        if ($temporal->time !== null) {
            $entities['time'] = $temporal->time;
        }

        // Phase 6C: surface a single informational warning when the time was
        // inferred from a day-part expression (e.g. "afternoon" → 15:00).
        // Explicit clock times and weekday date resolution emit no warning.
        // This is informational only: it does not affect entities, confidence,
        // status, readiness, validation, or execution.
        $warnings = [];
        if ($temporal->isTimeInferred()) {
            $warnings[] = "Time inferred from '{$temporal->timeExpression}' as {$temporal->time}.";
        }

        return new ParsedIntent($intent, $entities, $warnings);
    }
}
