<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

/**
 * Resolves an observation profile for a model: model → capability class → profile.
 *
 * This is the single entry point all diagnostics consumers use to discover how to drive a model.
 * The mapping lives in ModelCapabilityRegistry and the per-class strategy in
 * DefaultObservationProfiles; this resolver only composes them, so there is exactly one path from a
 * model name to its driving strategy. Diagnostics-only — never used by the runtime.
 */
class ObservationProfileResolver
{
    public function __construct(
        private readonly ModelCapabilityRegistry $registry,
    ) {}

    public function resolve(?string $model): ObservationProfile
    {
        return DefaultObservationProfiles::forClass($this->registry->classFor($model));
    }
}
