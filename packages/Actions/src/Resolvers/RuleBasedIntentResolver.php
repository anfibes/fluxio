<?php

namespace Fluxio\Actions\Resolvers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\DTO\EntityReference;
use Fluxio\Actions\DTO\ParsedIntent;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Fluxio\Actions\Support\LeadReferenceExtractor;
use Fluxio\Actions\Support\LeadStatusNormalizer;
use Fluxio\Actions\Support\TaskReferenceExtractor;
use Fluxio\Actions\Support\TaskStatusNormalizer;

class RuleBasedIntentResolver implements IntentResolverInterface
{
    public function __construct(
        private readonly DateTimeExpressionParser $parser,
        private readonly LeadReferenceExtractor $leadReferenceExtractor,
        private readonly TaskReferenceExtractor $taskReferenceExtractor,
        private readonly TaskStatusNormalizer $taskStatusNormalizer,
        private readonly LeadStatusNormalizer $leadStatusNormalizer,
    ) {}

    public function resolve(string $text): ParsedIntent
    {
        $lower = mb_strtolower($text);

        // update_task_status is checked before create_task: phrases like "complete the
        // follow-up task" contain "task" but mutate an EXISTING task's lifecycle rather
        // than creating one.
        $intent = match (true) {
            $this->isUpdateTaskStatus($lower) => 'update_task_status',
            $this->isUpdateLeadStatus($lower) => 'update_lead_status',
            str_contains($lower, 'contract') => 'prepare_contract_from_quote',
            str_contains($lower, 'assign') => 'assign_lead',
            str_contains($lower, 'meeting') => 'schedule_meeting',
            str_contains($lower, 'task') => 'create_task',
            str_contains($lower, 'call') => 'schedule_call',
            default => 'unknown',
        };

        // Parsed scalar VALUES go into the entities map; entity reference SPANS are
        // produced as typed EntityReference objects and folded back into the map by
        // ParsedIntent::fromParts (Phase 9E.3). The wire shape is unchanged.
        $entities = [];
        $references = [];

        // Preserve the user-facing lead reference SPAN (e.g. "Mario Rossi", "Rossi
        // SRL"), not a reduced token. The interpreter only extracts what the user
        // referred to; matching, scoring, ambiguity generation and identity remain
        // owned by EntityResolverRegistry / LeadEntityResolver. A richer span lets
        // the resolver exact-match where it otherwise would have surfaced a spurious
        // ambiguity (e.g. "Mario Rossi" no longer degrading to "Rossi"). Span
        // extraction is owned by LeadReferenceExtractor (Phase 9E.1).
        $leadQuery = $this->leadReferenceExtractor->extract($text, $intent);
        if ($leadQuery !== null) {
            $references[] = new EntityReference('lead_query', $leadQuery);
        }

        // Assignee extraction: "Assign Rossini to Marco" → assignee = Marco.
        // This is a parsed value (no resolver registered for user_query today), not a
        // reference span, so it stays a plain entities entry.
        if ($intent === 'assign_lead') {
            if ((bool) preg_match('/\bto\s+([A-Z][a-z]+)\b/', $text, $m)) {
                $entities['assignee'] = $m[1];
            }
        }

        // update_task_status: emit the task reference SPAN (resolved downstream by
        // TaskEntityResolver) and the normalized target status. The status key is named
        // `state` because the provider sandbox forbids entity keys containing "status";
        // it carries the canonical Task status string, validated at execution time.
        if ($intent === 'update_task_status') {
            $taskQuery = $this->taskReferenceExtractor->extract($text);
            if ($taskQuery !== null) {
                $entities['task'] = $taskQuery;
            }

            $status = $this->taskStatusNormalizer->normalize($lower);
            if ($status !== null) {
                $entities['state'] = $status;
            }
        }

        // update_lead_status: the lead reference SPAN is emitted as a lead_query
        // reference above (resolved downstream by the existing LeadEntityResolver); here
        // we add only the normalized target lifecycle state, keyed `state` (the sandbox
        // forbids entity keys containing "status"). Validated against Lead::STATUSES at
        // execution time.
        if ($intent === 'update_lead_status') {
            $state = $this->leadStatusNormalizer->normalize($lower);
            if ($state !== null) {
                $entities['state'] = $state;
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

        return ParsedIntent::fromParts(
            intent: $intent,
            entities: $entities,
            warnings: $warnings,
            entityReferences: $references,
        );
    }

    /**
     * Whether the command expresses a change to an existing task's lifecycle status
     * (update_task_status), as opposed to creating a task. Deliberately narrow — it
     * recognizes only the obvious "mark … as <status>", "set … to <status>", and
     * "<transition-verb> … task" phrasings, never a general NLP surface. A "create"
     * command is always excluded.
     */
    private function isUpdateTaskStatus(string $lower): bool
    {
        if (str_contains($lower, 'create')) {
            return false;
        }

        $hasStatus = $this->taskStatusNormalizer->normalize($lower) !== null;
        $hasMark = preg_match('/\bmark\b/', $lower) === 1;
        $hasSetTo = preg_match('/\bset\b.+\bto\b/', $lower) === 1;
        $hasTransitionVerb = preg_match(
            '/\b(complete|completed|close|closed|cancel|canceled|cancelled|reopen|finish|finished|done)\b/',
            $lower,
        ) === 1;
        $hasExplicitUpdateVerb = preg_match('/\b(update|change)\b/', $lower) === 1;

        // "mark … as <status>" / "set … to <status>" — only when a status is recognized.
        if (($hasMark || $hasSetTo) && $hasStatus) {
            return true;
        }

        // "Complete the … task" / "Update the … task": a transition or explicit-update
        // verb that names the task noun directly. The explicit-update form is allowed
        // without a status so the proposal can stay draft on a missing target status.
        return ($hasTransitionVerb || $hasExplicitUpdateVerb) && str_contains($lower, 'task');
    }

    /**
     * Whether the command expresses a change to an existing lead's lifecycle status
     * (update_lead_status). Disjoint from the task domain: any command naming a "task"
     * is excluded. A recognized lead state (explicit "as/to <status>" slot or a leading
     * lifecycle verb like "qualify") is itself the signal; an explicit "update/change …
     * lead" with no recognized state yet stays an update command (draft on missing
     * state). "create" and "assign" commands are never lead-status updates.
     */
    private function isUpdateLeadStatus(string $lower): bool
    {
        if (str_contains($lower, 'create') || str_contains($lower, 'task')) {
            return false;
        }

        if ($this->leadStatusNormalizer->normalize($lower) !== null) {
            return true;
        }

        $hasExplicitUpdateVerb = preg_match('/\b(update|change)\b/', $lower) === 1;

        return $hasExplicitUpdateVerb && str_contains($lower, 'lead');
    }
}
