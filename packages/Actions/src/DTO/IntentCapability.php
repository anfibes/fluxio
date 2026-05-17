<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\RefinementCapabilityType;

/**
 * Declares what refinement operations are permitted for a given intent.
 *
 * This is a static, in-memory description — it is never persisted.
 * Registered in IntentCapabilityRegistry and consulted by
 * ActionProposalRefinementService before applying any mutation.
 */
readonly class IntentCapability
{
    /**
     * @param list<MutationCapability>       $mutations   Allowed mutation operations with their target fields.
     * @param list<RefinementCapabilityType> $refinements High-level refinement capability types supported.
     */
    public function __construct(
        public string $intent,
        public array  $mutations,
        public array  $refinements                = [],
        public bool   $supportsContextualReferences  = false,
        public bool   $supportsAmbiguityResolution   = false,
        public bool   $supportsCollectionMutations   = false,
    ) {}

    public function toArray(): array
    {
        return [
            'supports_contextual_references' => $this->supportsContextualReferences,
            'supports_ambiguity_resolution'  => $this->supportsAmbiguityResolution,
            'supports_collection_mutations'  => $this->supportsCollectionMutations,
            'mutations'                      => array_map(fn ($m) => $m->toArray(), $this->mutations),
            'refinements'                    => array_map(fn ($r) => $r->value, $this->refinements),
        ];
    }
}
