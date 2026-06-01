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

        // Preserve the user-facing lead reference SPAN (e.g. "Mario Rossi", "Rossi
        // SRL"), not a reduced token. The interpreter only extracts what the user
        // referred to; matching, scoring, ambiguity generation and identity remain
        // owned by EntityResolverRegistry / LeadEntityResolver. A richer span lets
        // the resolver exact-match where it otherwise would have surfaced a spurious
        // ambiguity (e.g. "Mario Rossi" no longer degrading to "Rossi").
        $leadQuery = $this->extractLeadReference($text, $intent);
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

    /**
     * Tokens that terminate a lead reference span. These are temporal/connective
     * words, never entity names; matching is case-insensitive on bare tokens
     * (trailing punctuation stripped). They trim trailing phrases like
     * "tomorrow at 10" or "next Friday" while keeping the full name span intact.
     *
     * @var list<string>
     */
    private const LEAD_SPAN_STOP_WORDS = [
        'tomorrow', 'today', 'tonight', 'yesterday',
        'morning', 'afternoon', 'evening', 'noon', 'midnight', 'end',
        'next', 'this', 'later', 'early',
        'on', 'at', 'by', 'in', 'about', 'regarding',
        'day', 'days', 'week', 'weeks', 'month',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Extract the user-facing lead reference span from a command.
     *
     * The span is the text following the first intent-appropriate anchor present in
     * the command (e.g. "for"/"with"/"call", or "assign <lead> to <assignee>"),
     * with trailing temporal/connective phrases trimmed. This is deterministic span
     * preservation, not natural-language understanding: it never resolves identity
     * and never emits a candidate id — that stays with the resolver.
     */
    private function extractLeadReference(string $text, string $intent): ?string
    {
        if ($intent === 'assign_lead') {
            // "assign <lead> [to <assignee>]" — the lead sits between "assign" and
            // the optional "to <assignee>" tail (assignee is extracted separately).
            if (! preg_match('/\bassign\b\s+(.+)$/i', $text, $m)) {
                return null;
            }

            $after = $m[1];
            if (preg_match('/^(.*?)\s+\bto\b\s+/i', $after, $mm)) {
                $after = $mm[1];
            }

            return $this->trimLeadSpan($after);
        }

        foreach ($this->leadAnchors($intent) as $anchor) {
            $pattern = '/\b'.preg_quote($anchor, '/').'\b\s+(.+)$/i';

            if (preg_match($pattern, $text, $m)) {
                $span = $this->trimLeadSpan($m[1]);

                if ($span !== null) {
                    return $span;
                }
            }
        }

        return null;
    }

    /**
     * Lead-reference anchors per intent, in priority order. The lead span is the
     * text following the first anchor present in the command. Keeping these per
     * intent avoids capturing the wrong clause (e.g. preferring "with <lead>" over
     * the verb "call" in "Schedule a call with …").
     *
     * @return list<string>
     */
    private function leadAnchors(string $intent): array
    {
        return match ($intent) {
            'schedule_call' => ['with', 'call'],
            'schedule_meeting' => ['with'],
            'create_task', 'prepare_contract_from_quote' => ['for'],
            default => ['for', 'with'],
        };
    }

    /**
     * Keep the leading run of reference tokens, stopping at the first temporal /
     * connective stop word or numeric token. Original casing is preserved so the
     * resolver receives the span exactly as the user wrote it.
     */
    private function trimLeadSpan(string $raw): ?string
    {
        $tokens = preg_split('/\s+/', trim($raw)) ?: [];
        $span = [];

        foreach ($tokens as $token) {
            $bare = mb_strtolower(rtrim($token, ".,;:!?'\""));

            if ($bare === '') {
                continue;
            }

            if (in_array($bare, self::LEAD_SPAN_STOP_WORDS, true) || preg_match('/^\d/', $bare)) {
                break;
            }

            $span[] = $token;
        }

        $result = trim(implode(' ', $span));

        return $result === '' ? null : $result;
    }
}
