<?php

namespace Fluxio\Actions\DTO;

/**
 * Marker for the Semantic Refinement IR — the single interpretation-boundary
 * vocabulary a refinement provider emits (Phase 8D.4).
 *
 * Implemented by:
 *  - SemanticRefinementMutation        (field mutations → SemanticRefinementLowerer → NormalizedMutation)
 *  - Ambiguity\SemanticAmbiguityClarification (→ AmbiguityDirectiveLowerer → AmbiguityResolver)
 *
 * RuleBasedRefinementInterpreter::interpret() returns array<SemanticOperation>:
 * after the arrow flip the interpreter emits ONLY semantic IR, never a structural
 * NormalizedMutation. Structural mutations exist solely as lowering output.
 *
 * This is a pure type tag: no methods, no behaviour, no provider/provenance/
 * confidence surface. It must never gain a discriminator method, visitor, or any
 * provider metadata — routing happens by instanceof in the single service partition.
 */
interface SemanticOperation {}
