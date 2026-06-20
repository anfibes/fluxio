<?php

namespace Fluxio\Actions\Support;

/**
 * Deterministic, interpretation-side normalization of a user's lead-lifecycle wording
 * onto a canonical Lead status string.
 *
 * Sibling of TaskStatusNormalizer (same design): it turns free-text wording
 * ("qualify", "as won") into a normalized primitive value. It is intentionally small
 * and self-contained — it does NOT import the Lead model or query the database, so the
 * interpreter stays domain-agnostic and provider-blind.
 *
 * The canonical strings it produces deliberately mirror Fluxio\Leads\Models\Lead::STATUSES
 * (new, contacted, qualified, lost, won), but the Lead model remains the single
 * VALIDATION authority — UpdateLeadStatusActionExecutor re-validates against
 * Lead::STATUSES before persisting. New wording is mapped here; new statuses are never
 * invented and no transition rules are introduced.
 *
 * IMPORTANT — it does NOT scan the whole command for any status keyword. A status word
 * that merely appears inside a lead name/title (e.g. "Update the Lost account lead",
 * "Review the Qualified pipeline lead", "Find the Contacted leads") must NOT be read as
 * the desired target state. A state is recognized ONLY in one of two unambiguous
 * positions:
 *
 *   1. an explicit status slot after "as"/"to"   — "mark … as contacted", "set … to qualified"
 *   2. a leading lifecycle-changing verb           — "qualify …", "contacted …"
 *
 * Anything else returns null.
 */
class LeadStatusNormalizer
{
    /**
     * Status wording allowed in an explicit "as <status>" / "to <status>" slot. Kept as
     * an alternation so only these canonical tokens — never an arbitrary trailing word —
     * are read as a target state.
     */
    private const STATUS_SLOT = 'new|contacted|qualified|lost|won';

    /**
     * Leading lifecycle-changing verbs: the verb itself IS the target state
     * ("Qualify the … lead" → qualified). Deliberately conservative — "mark"/"set"/
     * "move"/"update" carry a state only via an explicit "as"/"to" slot, and adjective-
     * like states ("lost"/"won") are intentionally NOT leading verbs so they are not
     * read out of a lead name.
     */
    private const LEADING_VERB = 'qualified|qualify|contacted';

    /**
     * Normalize the target lead status of an update command to a canonical Lead status,
     * or null when no explicit status slot or leading lifecycle verb is present.
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

        // 2. Leading lifecycle-changing verb: "qualify …" / "contacted …".
        if (preg_match('/^\s*(?:please\s+)?('.self::LEADING_VERB.')\b/i', $text, $m) === 1) {
            return $this->canonical($m[1]);
        }

        return null;
    }

    /**
     * Map a single recognized status word/verb onto its canonical Lead status.
     * Returns null for anything outside the supported vocabulary.
     */
    private function canonical(string $word): ?string
    {
        $normal = preg_replace('/[\s-]+/', ' ', mb_strtolower(trim($word)));

        return match ($normal) {
            'new' => 'new',
            'contacted', 'contact' => 'contacted',
            'qualified', 'qualify' => 'qualified',
            'lost' => 'lost',
            'won' => 'won',
            default => null,
        };
    }
}
