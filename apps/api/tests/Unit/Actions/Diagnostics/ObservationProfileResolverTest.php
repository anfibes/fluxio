<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\Profiles\DefaultObservationProfiles;
use Fluxio\Actions\Diagnostics\Profiles\ModelCapabilityClass;
use Fluxio\Actions\Diagnostics\Profiles\ModelCapabilityRegistry;
use Fluxio\Actions\Diagnostics\Profiles\ObservationProfileResolver;
use Tests\TestCase;

/**
 * A7.2 capability-class profile abstraction: model → capability class → profile. Pure, no model,
 * no I/O. Proves the single mapping location, the per-class strategy, and that the chosen reasoning
 * default is strategy A (think:false + format=json).
 */
class ObservationProfileResolverTest extends TestCase
{
    private function registry(): ModelCapabilityRegistry
    {
        return new ModelCapabilityRegistry;
    }

    private function resolver(): ObservationProfileResolver
    {
        return new ObservationProfileResolver($this->registry());
    }

    // ── 1. model → capability class mapping ───────────────────────────────────

    public function test_maps_known_models_to_capability_classes(): void
    {
        $registry = $this->registry();

        $this->assertSame(ModelCapabilityClass::InstructionFollowing, $registry->classFor('qwen3:0.6b'));
        $this->assertSame(ModelCapabilityClass::Reasoning, $registry->classFor('qwen3:1.7b'));
    }

    public function test_unmapped_or_null_model_falls_back_to_the_default_class(): void
    {
        $registry = $this->registry();

        $this->assertSame(ModelCapabilityClass::InstructionFollowing, $registry->classFor('some-unknown-model'));
        $this->assertSame(ModelCapabilityClass::InstructionFollowing, $registry->classFor(null));
    }

    // ── 2. capability class → profile resolution ──────────────────────────────

    public function test_instruction_following_profile_is_byte_identical_to_prior_behavior(): void
    {
        $profile = DefaultObservationProfiles::instructionFollowing();

        $this->assertSame(ModelCapabilityClass::InstructionFollowing, $profile->capabilityClass);
        $this->assertNull($profile->think, 'Instruction-following must not override thinking.');
        $this->assertTrue($profile->forceJson, 'Instruction-following must force JSON.');
    }

    public function test_reasoning_profile_default_is_strategy_a_think_false_plus_format_json(): void
    {
        $profile = DefaultObservationProfiles::reasoning();

        $this->assertSame(ModelCapabilityClass::Reasoning, $profile->capabilityClass);
        // A7.2 decision: default reasoning strategy is A (think:false + format=json), not B
        // (free-text+extract), for contract fidelity + robustness. B stays available via --free-text.
        $this->assertFalse($profile->think, 'Reasoning default must disable thinking.');
        $this->assertTrue($profile->forceJson, 'Reasoning default must keep forced JSON (strategy A).');
    }

    // ── 3 & 4. concrete model resolution ──────────────────────────────────────

    public function test_qwen3_06b_resolves_instruction_following(): void
    {
        $profile = $this->resolver()->resolve('qwen3:0.6b');

        $this->assertSame('instruction-following', $profile->id);
        $this->assertNull($profile->think);
        $this->assertTrue($profile->forceJson);
    }

    public function test_qwen3_17b_resolves_reasoning(): void
    {
        $profile = $this->resolver()->resolve('qwen3:1.7b');

        $this->assertSame('reasoning', $profile->id);
        $this->assertFalse($profile->think);
        $this->assertTrue($profile->forceJson);
    }

    public function test_profile_maps_to_observation_options(): void
    {
        $options = DefaultObservationProfiles::reasoning()->toObservationOptions(maxTokens: 256);

        $this->assertFalse($options->think);
        $this->assertTrue($options->forceJson);
        $this->assertSame(256, $options->maxTokens);
    }

    public function test_resolver_is_container_resolvable(): void
    {
        $resolver = $this->app->make(ObservationProfileResolver::class);

        $this->assertSame('reasoning', $resolver->resolve('qwen3:1.7b')->id);
    }
}
