<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

/**
 * THE single place that maps a concrete model name to a capability class.
 *
 * No other file in the codebase compares model strings to decide how to drive a model — adding a
 * model is one line here. Unmapped models fall back to the default class (instruction-following),
 * which preserves the prior behavior, so an unknown model is driven exactly as before.
 *
 * Diagnostics-only: this registry is consumed by ObservationProfileResolver and never by the
 * runtime interpretation path.
 */
class ModelCapabilityRegistry
{
    /**
     * Model → capability class. The ONLY model-string mapping in the system.
     *
     * @var array<string, ModelCapabilityClass>
     */
    private const MAP = [
        // qwen3:0.6b complies with forced JSON directly (A7.1 baseline: 96% contract-valid).
        'qwen3:0.6b' => ModelCapabilityClass::InstructionFollowing,
        // qwen3:1.7b is a thinking model whose reasoning collides with forced JSON (A7.1: 0% → 98%
        // once driven reasoning-aware).
        'qwen3:1.7b' => ModelCapabilityClass::Reasoning,
    ];

    public function __construct(
        private readonly ModelCapabilityClass $default = ModelCapabilityClass::InstructionFollowing,
    ) {}

    /**
     * Resolve the capability class for a model. Null (no override → configured default model) and
     * any unmapped model resolve to the default class.
     */
    public function classFor(?string $model): ModelCapabilityClass
    {
        if ($model === null) {
            return $this->default;
        }

        return self::MAP[$model] ?? $this->default;
    }

    /**
     * @return list<string>
     */
    public function mappedModels(): array
    {
        return array_keys(self::MAP);
    }
}
