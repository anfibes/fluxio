<?php

namespace Fluxio\Actions\Support;

/**
 * Deterministic extraction of the user-facing TASK reference SPAN from an
 * update_task_status command.
 *
 * Sibling of LeadReferenceExtractor: it identifies WHAT task the user referred to
 * (e.g. "Follow-up", "Rossi follow-up"), never WHICH Task it is. It returns the span
 * as the user wrote it (casing preserved); matching, scoring, candidate identity and
 * ambiguity remain owned by EntityResolverRegistry / TaskEntityResolver. It never
 * resolves identity and never emits a task id.
 *
 * It supports only the small set of obvious phrasings the slice targets:
 *   - "complete the follow-up task"            → "follow-up"
 *   - "mark follow-up as completed"            → "follow-up"
 *   - "mark the Rossi follow-up task as done"  → "Rossi follow-up"
 *   - "set the follow-up task to completed"    → "follow-up"
 *   - "Complete the Rossi follow-up task"      → "Rossi follow-up"
 *
 * It is intentionally NOT a general NLP extractor.
 */
class TaskReferenceExtractor
{
    /**
     * Extract the task reference span from an update_task_status command, or null when
     * no task reference remains after stripping the command/status scaffolding.
     */
    public function extract(string $text): ?string
    {
        $span = trim($text);

        // Drop a leading command verb ("mark", "set", "complete", …).
        $span = (string) preg_replace(
            '/^\s*(please\s+)?(mark|set|update|complete|completed|close|cancel|reopen|finish)\b\s*/i',
            '',
            $span,
            1,
        );

        // Drop a trailing status clause introduced by "as"/"to" ("… as completed").
        $span = (string) preg_replace('/\s+(as|to)\s+.+$/i', '', $span);

        // Drop the literal noun "task".
        $span = (string) preg_replace('/\btask\b/i', ' ', $span);

        // Drop a leading determiner ("the follow-up" → "follow-up").
        $span = (string) preg_replace('/^\s*(the|a|an|this|that)\s+/i', '', $span);

        // Drop a trailing bare status word with no connector ("mark follow-up completed").
        $span = (string) preg_replace(
            '/\s+(completed|complete|done|finished|closed|pending|cancelled|canceled)\s*$/i',
            '',
            $span,
        );

        $span = trim((string) preg_replace('/\s+/', ' ', $span));

        return $span === '' ? null : $span;
    }
}
