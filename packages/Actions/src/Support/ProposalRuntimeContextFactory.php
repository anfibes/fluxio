<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\ProposalRuntimeContext;
use Fluxio\Actions\Models\ActionProposal;

/**
 * Builds a deterministic, proposal-scoped ProposalRuntimeContext from an
 * ActionProposal (Phase 7A foundation).
 *
 * Sole responsibility: ActionProposal -> ProposalRuntimeContext. It only
 * normalizes data already stored on the proposal — it performs no database
 * queries, no natural-language parsing, no ambiguity resolution, and no
 * mutation. The context it returns is not persisted separately.
 */
class ProposalRuntimeContextFactory
{
    /** Editable-field keys treated as temporal for the derived `temporal` map. */
    private const TEMPORAL_KEYS = ['date', 'time'];

    public function fromProposal(ActionProposal $proposal): ProposalRuntimeContext
    {
        $entities = $proposal->entities ?? [];
        $editableFields = $proposal->editable_fields ?? [];
        $missing = $proposal->missing ?? [];
        $ambiguities = $proposal->ambiguities ?? [];
        $warnings = $proposal->warnings ?? [];

        return new ProposalRuntimeContext(
            proposalId: (string) $proposal->id,
            intent: (string) $proposal->intent,
            status: (string) $proposal->status,
            entities: $entities,
            editableFields: $editableFields,
            missingFields: $missing,
            ambiguities: $ambiguities,
            warnings: array_values($warnings),
            lastRefinement: $proposal->last_refinement,
            collections: $this->deriveCollections($editableFields),
            temporal: $this->deriveTemporal($editableFields, $entities),
            blockingAmbiguities: $this->deriveBlockingAmbiguities($ambiguities),
        );
    }

    /**
     * Collection-like editable fields: any field whose current value is an array
     * (e.g. participants). Keyed by field key. No business interpretation.
     *
     * @param  array<int, array<string, mixed>>  $editableFields
     * @return array<string, array<int, mixed>>
     */
    private function deriveCollections(array $editableFields): array
    {
        $collections = [];

        foreach ($editableFields as $field) {
            $key = $field['key'] ?? null;
            $value = $field['value'] ?? null;

            if (is_string($key) && is_array($value)) {
                $collections[$key] = array_values($value);
            }
        }

        return $collections;
    }

    /**
     * Temporal values (date / time) taken from the current editable fields, with
     * a fallback to stored entities. Only non-null values are included.
     *
     * @param  array<int, array<string, mixed>>  $editableFields
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function deriveTemporal(array $editableFields, array $entities): array
    {
        $byKey = [];
        foreach ($editableFields as $field) {
            if (isset($field['key'])) {
                $byKey[$field['key']] = $field['value'] ?? null;
            }
        }

        $temporal = [];
        foreach (self::TEMPORAL_KEYS as $key) {
            $value = $byKey[$key] ?? ($entities[$key] ?? null);
            if ($value !== null) {
                $temporal[$key] = $value;
            }
        }

        return $temporal;
    }

    /**
     * Ambiguities flagged as blocking. Resolution state is intentionally NOT
     * considered here — callers that need "unresolved" semantics filter further.
     *
     * @param  array<int, array<string, mixed>>  $ambiguities
     * @return array<int, array<string, mixed>>
     */
    private function deriveBlockingAmbiguities(array $ambiguities): array
    {
        return array_values(array_filter(
            $ambiguities,
            fn (array $a) => ($a['blocking'] ?? false) === true,
        ));
    }
}
