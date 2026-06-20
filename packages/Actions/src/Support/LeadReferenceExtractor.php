<?php

namespace Fluxio\Actions\Support;

/**
 * Deterministic extraction of the user-facing lead reference SPAN from a command.
 *
 * This is the interpretation-side seam that identifies WHAT the user referred to —
 * never WHICH entity it is. It returns a span (e.g. "Mario Rossi", "Rossi SRL")
 * exactly as the user wrote it; matching, scoring, candidate identity, ambiguity
 * generation and auto-resolution remain owned by EntityResolverRegistry /
 * LeadEntityResolver. It never resolves identity and never emits a candidate id.
 *
 * It was extracted from RuleBasedIntentResolver (Phase 9E.1) so the span-extraction
 * contract is a small, single-responsibility unit: independently testable, reusable
 * by a future interpretation provider, and a deterministic baseline that a future
 * provider's span extraction can be compared against. Behaviour is byte-identical to
 * the Phase 9D.1 in-resolver implementation.
 */
class LeadReferenceExtractor
{
    /**
     * Tokens that terminate a lead reference span. These are temporal/connective
     * words, never entity names; matching is case-insensitive on bare tokens
     * (trailing punctuation stripped). They trim trailing phrases like
     * "tomorrow at 10" or "next Friday" while keeping the full name span intact.
     *
     * @var list<string>
     */
    private const STOP_WORDS = [
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
     * with trailing temporal/connective phrases trimmed. Returns null when no
     * reference is present.
     */
    public function extract(string $text, string $intent): ?string
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

            return $this->trim($after);
        }

        if ($intent === 'update_lead_status') {
            // "mark <lead> as contacted" / "set <lead> to won" / "qualify <lead>" —
            // the lead sits between a leading lifecycle/command verb and an optional
            // "as|to <status>" tail. Mirrors TaskReferenceExtractor: strip the verb and
            // the status clause, drop the "lead" noun and a leading determiner.
            return $this->extractLeadStatusReference($text);
        }

        foreach ($this->anchors($intent) as $anchor) {
            $pattern = '/\b'.preg_quote($anchor, '/').'\b\s+(.+)$/i';

            if (preg_match($pattern, $text, $m)) {
                $span = $this->trim($m[1]);

                if ($span !== null) {
                    return $span;
                }
            }
        }

        return null;
    }

    /**
     * Extract the lead reference span from an update_lead_status command.
     *
     * Narrow and deterministic — it removes only the command scaffolding (leading
     * verb, "as|to <status>" tail, the "lead" noun, a leading determiner, a trailing
     * bare status word) and returns whatever lead reference remains, casing preserved.
     * A status word inside the lead name ("Lost account") is kept; identity/ambiguity
     * stay owned by LeadEntityResolver.
     */
    private function extractLeadStatusReference(string $text): ?string
    {
        $span = trim($text);

        // Drop a leading lifecycle/command verb ("mark", "set", "qualify", …).
        $span = (string) preg_replace(
            '/^\s*(please\s+)?(mark|set|move|update|change|reopen|qualified|qualify|contacted)\b\s*/i',
            '',
            $span,
            1,
        );

        // Drop a trailing status clause introduced by "as"/"to" ("… as contacted").
        $span = (string) preg_replace('/\s+(as|to)\s+.+$/i', '', $span);

        // Drop the literal noun "lead".
        $span = (string) preg_replace('/\blead\b/i', ' ', $span);

        // Drop a leading determiner ("the Rossi" → "Rossi").
        $span = (string) preg_replace('/^\s*(the|a|an|this|that)\s+/i', '', $span);

        // Drop a trailing bare status word with no connector ("mark Rossi contacted").
        $span = (string) preg_replace('/\s+(new|contacted|qualified|lost|won)\s*$/i', '', $span);

        $span = trim((string) preg_replace('/\s+/', ' ', $span));

        return $span === '' ? null : $span;
    }

    /**
     * Lead-reference anchors per intent, in priority order. The lead span is the
     * text following the first anchor present in the command. Keeping these per
     * intent avoids capturing the wrong clause (e.g. preferring "with <lead>" over
     * the verb "call" in "Schedule a call with …").
     *
     * @return list<string>
     */
    private function anchors(string $intent): array
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
    private function trim(string $raw): ?string
    {
        $tokens = preg_split('/\s+/', trim($raw)) ?: [];
        $span = [];

        foreach ($tokens as $token) {
            $bare = mb_strtolower(rtrim($token, ".,;:!?'\""));

            if ($bare === '') {
                continue;
            }

            if (in_array($bare, self::STOP_WORDS, true) || preg_match('/^\d/', $bare)) {
                break;
            }

            $span[] = $token;
        }

        $result = trim(implode(' ', $span));

        return $result === '' ? null : $result;
    }
}
