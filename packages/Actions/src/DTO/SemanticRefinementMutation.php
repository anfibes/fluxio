<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\SemanticMutationType;

/**
 * Semantic Refinement IR — the *interpretation* boundary (Phase 8B foundation).
 *
 * This models WHAT THE USER MEANT, not what the runtime applies. It is the
 * structured output that refinement providers should emit: the rule-based
 * interpreter today, and future LLM / voice / external structured providers. A
 * provider classifies and extracts; it never describes a structural effect.
 *
 *   e.g. "Push it by 30 minutes" → SemanticRefinementMutation(
 *            type: ShiftTime,
 *            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later'],
 *        )
 *
 * It is NOT the application IR. The deterministic SemanticRefinementLowerer
 * compiles a SemanticRefinementMutation into a structural NormalizedMutation,
 * which the existing capability/application/runtime flow consumes unchanged.
 * The arrow is one-directional:
 *
 *   semantic mutation → lower → NormalizedMutation → applyAll()
 *
 * It is deliberately NOT execution IR and carries no proposal state, no resolved
 * effect (a ShiftTime has no concrete time yet — that is computed later from
 * ProposalRuntimeContext), and no authority. Per-type payload contract:
 *
 *   - ReplaceTime / ReplaceDate : payload['value'] (string) — the new value
 *   - ShiftTime                 : payload['amount'] (int > 0),
 *                                 payload['unit'] ('minutes' | 'hours'),
 *                                 payload['direction'] ('later' | 'earlier', default 'later')
 *   - AddParticipant            : payload['value'] (string) — the participant to add
 *   - RemoveParticipant         : target (string) — the participant to remove
 *   - ReplaceParticipant        : target (string, the existing item) + payload['value'] (string, the new item)
 *   - ReplacePriority           : payload['value'] (string) — the new priority (Phase 8D.4)
 *   - ClearPriority             : no payload — lowers to a structural priority clear (Phase 8D.4)
 *
 * Provider attribution and confidence are intentionally NOT modeled yet; they
 * are added when a probabilistic provider actually needs them. The optional
 * `metadata`/`source` below are provider-side only: they live left of the lowering
 * boundary and are never copied into the lowered NormalizedMutation.
 */
final class SemanticRefinementMutation implements SemanticOperation
{
    /**
     * @param  array<string, mixed>  $payload  Type-specific semantic parameters (see class doc)
     * @param  string|null  $field  Optional explicit field hint; the lowerer derives the field per type
     * @param  string|null  $target  Existing collection item targeted by remove/replace participant ops
     * @param  string  $source  Provenance label carried into the lowered mutation (detected, inferred, …)
     * @param  array<string, mixed>  $metadata  Opaque provider-side metadata. It lives LEFT of the lowering
     *                                          boundary and is NOT passed into the lowered NormalizedMutation —
     *                                          the lowerer builds only deterministic runtime metadata. This keeps
     *                                          provider/provenance/confidence out of the authority input.
     */
    public function __construct(
        public readonly SemanticMutationType $type,
        public readonly array $payload = [],
        public readonly ?string $field = null,
        public readonly ?string $target = null,
        public readonly string $source = 'detected',
        public readonly array $metadata = [],
    ) {}
}
