<?php

namespace Fluxio\Actions\Resolvers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\ParsedIntent;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Fluxio\Actions\Support\LeadReferenceExtractor;

class RuleBasedIntentResolver implements IntentResolverInterface
{
    public function __construct(
        private readonly DateTimeExpressionParser $parser,
        private readonly LeadReferenceExtractor $leadReferenceExtractor,
    ) {}

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

        // Preserve the user-facing lead reference SPAN (e.g. "Mario Rossi", "Rossi
        // SRL"), not a reduced token. The interpreter only extracts what the user
        // referred to; matching, scoring, ambiguity generation and identity remain
        // owned by EntityResolverRegistry / LeadEntityResolver. A richer span lets
        // the resolver exact-match where it otherwise would have surfaced a spurious
        // ambiguity (e.g. "Mario Rossi" no longer degrading to "Rossi"). Span
        // extraction is owned by LeadReferenceExtractor (Phase 9E.1).
        $leadQuery = $this->leadReferenceExtractor->extract($text, $intent);
        if ($leadQuery !== null) {
            $entities['lead_query'] = $leadQuery;
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

        // Phase 6D: for scheduling intents that need both date and time, flag
        // temporal incompleteness when exactly one of the two was extracted.
        // Informational only — it does not change status, readiness, confidence,
        // entities, ambiguities, refinement, or execution. No warning is emitted
        // when both are present or both are missing.
        if (in_array($intent, ['schedule_call', 'schedule_meeting'], true)) {
            $hasDate = $temporal->date !== null;
            $hasTime = $temporal->time !== null;

            if ($hasDate && ! $hasTime) {
                $warnings[] = $temporal->dateExpression !== null
                    ? "Date resolved from '{$temporal->dateExpression}', but time is still missing."
                    : 'Date resolved, but time is still missing.';
            } elseif ($hasTime && ! $hasDate) {
                $warnings[] = $temporal->timeExpression !== null
                    ? "Time resolved from '{$temporal->timeExpression}', but date is still missing."
                    : 'Time resolved, but date is still missing.';
            }
        }

        return new ParsedIntent($intent, $entities, $warnings);
    }
}
