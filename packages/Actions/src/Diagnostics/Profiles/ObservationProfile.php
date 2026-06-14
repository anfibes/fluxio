<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

use Fluxio\Actions\Diagnostics\Examples\ExemplarStrategy;
use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;

/**
 * Diagnostics-only "how to drive this class of model" profile.
 *
 * A profile bundles the transport strategy for a capability class: whether to disable thinking,
 * whether to force JSON (vs free-text + extraction), and a reserved prompt-variant id for a later
 * slice. It is provider-agnostic and carries no model strings — it is selected per model via the
 * resolver, never branched on directly.
 *
 * It also owns the diagnostics **few-shot policy** for the class (Path 1): whether few-shot is
 * enabled by default and, if so, which exemplar strategy to use. This lets the diagnostic command
 * pick the right few-shot behavior per model automatically (instruction-following → off; reasoning →
 * selected) without the user remembering flags. The few-shot policy is a DIAGNOSTICS default only —
 * CLI flags always override it, and it never touches the runtime, validator, grammar, or prompt
 * content (few-shot only conditions the diagnostic prompt with held-out IntentExamples exemplars).
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
        public bool $fewShotDefault = false,
        public ?ExemplarStrategy $defaultExemplarStrategy = null,
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
            'few_shot_default' => $this->fewShotDefault,
            'default_exemplar_strategy' => $this->defaultExemplarStrategy?->value,
            'prompt_variant' => $this->promptVariantId,
        ];
    }
}
