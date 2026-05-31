<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\SemanticOperation;

interface RefinementInterpreterInterface
{
    /**
     * Extract refinement operations from a refinement text.
     *
     * Phase 8D.4: the interpreter is a pure semantic extractor and returns ONLY
     * Semantic Refinement IR — `SemanticRefinementMutation` (field mutations,
     * lowered to NormalizedMutation by the service) and
     * `Ambiguity\SemanticAmbiguityClarification` (routed through the
     * AmbiguityResolver authority). It never emits a structural NormalizedMutation;
     * structural mutations exist only as lowering output.
     *
     * @return array<SemanticOperation>
     */
    public function interpret(string $text): array;
}
