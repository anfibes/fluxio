<?php

namespace Fluxio\Actions\DTO;

/**
 * The deterministic parser's interpretation-side output contract — the structure a
 * command interpreter produces from free text, before confidence/locale/source are
 * attached to form a NormalizedCommand.
 *
 * It is interpretation-side ONLY: it describes WHAT the user said, never the runtime
 * consequences of it. `entities` carries raw, user-facing references and parsed
 * values — notably `lead_query` is a reference SPAN to be resolved later, not a
 * resolved entity. Identity, matching and ambiguity are decided downstream by
 * EntityResolverRegistry; readiness/status/lifecycle by ActionInterpreterService.
 *
 * It MUST NOT carry resolved entity ids / selected_candidate_id, proposal status,
 * missing fields, ambiguities, readiness, or any execution/model reference. (A
 * guardrail test asserts the interpretation-side DTOs expose no such surface.)
 * Interpretation-side metadata such as warnings is allowed.
 */
class ParsedIntent
{
    /**
     * @param  array<string, mixed>  $entities  Raw references (e.g. lead_query span) and parsed values (e.g. date, time, assignee)
     * @param  string[]  $warnings  Non-fatal interpretation notes (e.g. inferred day-part times)
     */
    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
        public readonly array $warnings = [],
    ) {}
}
