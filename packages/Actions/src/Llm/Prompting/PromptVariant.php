<?php

namespace Fluxio\Actions\Llm\Prompting;

/**
 * Diagnostics-only, append-only prompt-guidance variant for the interpretation sandbox (Slice A6.2).
 *
 * A variant is an OPT-IN block of extra guidance appended AFTER the strict base prompt by
 * InterpretationPromptBuilder. It never rewrites the base prompt and is never used by the runtime:
 * the runtime/sandbox provider always calls buildSystemPrompt() with no variant, so its output stays
 * byte-identical. Variants exist so diagnostics can probe whether targeted guidance reduces specific
 * failure classes without touching the shared runtime prompt.
 *
 * The id is the single source of truth (no scattered magic strings); the guidance text lives here too
 * so a variant is one small, explicit, reviewable unit.
 */
enum PromptVariant: string
{
    /**
     * Italian intent-selection guidance (A6.2). Targets the A6.1 intent-confusion clusters
     * (create_task under-recognition; transfer-phrasing assign_lead; contract-from-quote; unknown
     * for reporting/navigation). Intent-selection ONLY — it deliberately adds no entity-formatting
     * rules and lists no corpus phrases verbatim.
     */
    case ItIntentGuidance = 'it_intent_guidance';

    public static function fromString(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }

    /**
     * The append-only guidance block (leading blank line + a delimited section). Returned as lines so
     * the builder can merge it exactly like the worked-examples block.
     *
     * @return list<string>
     */
    public function guidanceLines(): array
    {
        return match ($this) {
            self::ItIntentGuidance => [
                '',
                'Intent selection hints for Italian input (use these to choose the intent only; the entity rules above are unchanged):',
                '- "promemoria", "compito", "attività", "ricordami di…", "annota", "segna", "raccogli", "inserisci" usually mean create_task — unless the user is clearly arranging a call or a meeting.',
                '- "chiamata", "telefonata", "call", "videochiamata", "sentire al telefono" mean schedule_call when the user wants to arrange contact.',
                '- "riunione", "incontro", "meeting", "appuntamento", "vedersi di persona" mean schedule_meeting.',
                '- "assegna", "affida", "passa", "attribuisci", "gira", "sposta la gestione" directed at a lead/person mean assign_lead.',
                '- "contratto" together with a "preventivo"/"offerta"/quote code (e.g. "Q-1234") means prepare_contract_from_quote; a plain document, report, or relazione request with no quote is create_task.',
                '- Reports, dashboards, exports, settings, invoices, inventory, and generic email/newsletter sending are "unknown" unless they clearly match a supported intent.',
            ],
        };
    }
}
