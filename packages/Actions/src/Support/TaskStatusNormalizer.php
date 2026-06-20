<?php

namespace Fluxio\Actions\Support;

/**
 * Deterministic, interpretation-side normalization of a user's status wording onto a
 * canonical Task lifecycle status string.
 *
 * This is the SAME kind of seam as DateTimeExpressionParser: it turns free-text
 * wording ("done", "in progress") into a normalized primitive value, exactly as the
 * temporal parser turns "tomorrow" into an ISO date. It is intentionally small and
 * self-contained — it does NOT import the Task model or query the database, so the
 * interpreter stays domain-agnostic and provider-blind.
 *
 * The canonical strings it produces deliberately mirror Fluxio\Tasks\Models\Task::STATUSES
 * (pending, in_progress, completed, cancelled), but the Task model remains the single
 * VALIDATION authority — UpdateTaskStatusActionExecutor re-validates the value against
 * Task::STATUSES before persisting. New wording is mapped here; new statuses are never
 * invented.
 *
 * IMPORTANT — it does NOT scan the whole command for any status keyword. A status word
 * that merely appears inside a task title (e.g. "Update the Cancel subscription task",
 * "Update the Pending invoice task") must NOT be read as the desired target state. A
 * state is recognized ONLY in one of two unambiguous positions:
 *
 *   1. an explicit status slot after "as"/"to"   — "mark … as completed", "set … to pending"
 *   2. a leading status-changing action verb      — "complete …", "cancel …", "reopen …"
 *
 * Anything else returns null.
 */
class TaskStatusNormalizer
{
    /**
     * Status wording allowed in an explicit "as <status>" / "to <status>" slot. Kept as
     * an alternation so only these tokens — never an arbitrary trailing word — are read
     * as a target state.
     */
    private const STATUS_SLOT = 'in[\s-]progress|completed|complete|done|finished|finish|closed|close|cancelled|canceled|cancel|pending';

    /**
     * Leading status-changing action verbs: the verb itself IS the target state
     * ("Complete the … task" → completed). "mark"/"set"/"move"/"update" are deliberately
     * excluded — they only carry a state via an explicit "as"/"to" slot (or none at all).
     */
    private const LEADING_VERB = 'completed|complete|finished|finish|closed|close|cancelled|canceled|cancel|reopened|reopen';

    /**
     * Normalize the target status of an update command to a canonical Task status, or
     * null when no explicit status slot or leading status verb is present.
     */
    public function normalize(string $text): ?string
    {
        // 1. Explicit status slot: "… as <status>" / "… to <status>".
        if (preg_match('/\b(?:as|to)\s+('.self::STATUS_SLOT.')\b/i', $text, $m) === 1) {
            $status = $this->canonical($m[1]);
            if ($status !== null) {
                return $status;
            }
        }

        // 2. Leading status-changing verb: "complete/finish/close/cancel/reopen …".
        if (preg_match('/^\s*(?:please\s+)?('.self::LEADING_VERB.')\b/i', $text, $m) === 1) {
            return $this->canonical($m[1]);
        }

        return null;
    }

    /**
     * Map a single recognized status word/verb onto its canonical Task status.
     * Returns null for anything outside the supported vocabulary.
     */
    private function canonical(string $word): ?string
    {
        $normal = preg_replace('/[\s-]+/', ' ', mb_strtolower(trim($word)));

        return match ($normal) {
            'in progress' => 'in_progress',
            'completed', 'complete', 'done', 'finished', 'finish', 'closed', 'close' => 'completed',
            'cancelled', 'canceled', 'cancel' => 'cancelled',
            'pending', 'reopen', 'reopened' => 'pending',
            default => null,
        };
    }
}
