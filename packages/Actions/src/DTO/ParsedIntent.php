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
 *
 * The `entities` map mixes two categories: raw entity reference SPANS (the `*_query`
 * keys, which must still pass through EntityResolverRegistry) and already-parsed
 * scalar VALUES (`date`, `time`, `priority`, `assignee`). `entityReferences()` /
 * `entityReference()` expose the reference category as typed EntityReference objects
 * (Phase 9E.2) — a derived, read-side view. Storage and wire shape are unchanged, so
 * NormalizedCommand stays byte-identical; `lead_query` remains the bridge key.
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

    /**
     * The entity reference spans in this interpretation, derived from the `*_query`
     * keys of the entities map. Parsed scalar values (date/time/priority/assignee)
     * are NOT references. This is a typed read-side view; it does not change storage.
     *
     * @return list<EntityReference>
     */
    public function entityReferences(): array
    {
        $references = [];

        foreach ($this->entities as $key => $value) {
            if (is_string($key) && str_ends_with($key, '_query') && is_string($value) && $value !== '') {
                $references[] = new EntityReference($key, $value);
            }
        }

        return $references;
    }

    /**
     * The entity reference for a given transit key (e.g. `lead_query`), or null if
     * that key is absent or is not a reference span.
     */
    public function entityReference(string $key): ?EntityReference
    {
        foreach ($this->entityReferences() as $reference) {
            if ($reference->key === $key) {
                return $reference;
            }
        }

        return null;
    }
}
