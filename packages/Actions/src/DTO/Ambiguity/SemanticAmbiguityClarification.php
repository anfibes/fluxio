<?php

namespace Fluxio\Actions\DTO\Ambiguity;

/**
 * Ambiguity Clarification IR — the interpretation boundary for resolving a
 * blocking ambiguity (Phase 8C). It is the ambiguity-side parallel of
 * SemanticRefinementMutation and is a DISTINCT semantic operation category: it
 * does NOT lower into NormalizedMutation.
 *
 *   "the company"   → SemanticAmbiguityClarification(key, AttributeSelector('type','company'))
 *   "the first one" → SemanticAmbiguityClarification(key, OrdinalSelector(1))
 *   "Rossi SRL"     → SemanticAmbiguityClarification(key, LabelSelector('Rossi SRL'))
 *
 * It carries only the ambiguity key and a selector — never a candidate id, never
 * a resolution outcome. A provider (rule-based today, LLM/voice later) emits this;
 * the deterministic AmbiguityDirectiveLowerer validates it and the
 * AmbiguityResolver computes the outcome. The provider does not decide
 * resolve-vs-narrow; the runtime does, from the current candidate set.
 */
final class SemanticAmbiguityClarification
{
    /**
     * @param  array<string, mixed>  $metadata  Opaque provider metadata, passed through to the directive
     */
    public function __construct(
        public readonly string $ambiguityKey,
        public readonly AmbiguitySelector $selector,
        public readonly string $source = 'detected',
        public readonly array $metadata = [],
    ) {}
}
