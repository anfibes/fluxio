<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;

/**
 * Diagnostics-only "how to drive this class of model" profile.
 *
 * A profile bundles the transport strategy for a capability class: whether to disable thinking,
 * whether to force JSON (vs free-text + extraction), and a reserved prompt-variant id for a later
 * slice. It is provider-agnostic and carries no model strings — it is selected per model via the
 * resolver, never branched on directly.
 *
 * It maps to the generic ObservationOptions the observation pipeline already consumes, so applying
 * a profile changes nothing about validation, the corpus, or the runtime — only the transport knobs.
 */
final readonly class ObservationProfile
{
    public function __construct(
        public string $id,
        public ModelCapabilityClass $capabilityClass,
        public ?bool $think,
        public bool $forceJson,
        public ?string $promptVariantId = null,
    ) {}

    /**
     * Convert to transport knobs. $maxTokens is a separate (CLI) concern that profiles do not own,
     * so it is passed through unchanged.
     */
    public function toObservationOptions(?int $maxTokens = null): ObservationOptions
    {
        return new ObservationOptions(
            think: $this->think,
            forceJson: $this->forceJson,
            maxTokens: $maxTokens,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'capability_class' => $this->capabilityClass->value,
            'think' => $this->think,
            'force_json' => $this->forceJson,
            'prompt_variant' => $this->promptVariantId,
        ];
    }
}
