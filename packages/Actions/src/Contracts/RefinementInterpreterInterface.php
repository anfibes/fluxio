<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;

interface RefinementInterpreterInterface
{
    /**
     * Extract refinement operations from a refinement text.
     *
     * Phase 8D.2 (mutation arrow flip): the interpreter is a semantic extractor.
     * Migrated mutation families (date/time/temporal-shift and participant
     * add/remove/replace) are emitted as Semantic Refinement IR
     * (SemanticRefinementMutation); ActionProposalRefinementService lowers them
     * into structural NormalizedMutation at the refinement seam. Not-yet-migrated
     * families (priority replace/clear) are still emitted as NormalizedMutation
     * directly — hence the transitional union element type. Ambiguity is not part
     * of this contract; it remains in the service for now.
     *
     * @return array<SemanticRefinementMutation|NormalizedMutation>
     */
    public function interpret(string $text): array;
}
