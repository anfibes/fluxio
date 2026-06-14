<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\Examples\ExemplarStrategy;
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

    // ── Path 1: capability-aware few-shot policy ──────────────────────────────

    public function test_instruction_following_profile_defaults_few_shot_off(): void
    {
        $profile = DefaultObservationProfiles::instructionFollowing();

        // A4.2 showed few-shot HURTS this class (qwen3:0.6b 41.9% → 33–34%), so the default is off.
        $this->assertFalse($profile->fewShotDefault, 'Instruction-following must default few-shot off.');
        $this->assertNull($profile->defaultExemplarStrategy, 'Instruction-following must declare no default strategy.');
    }

    public function test_reasoning_profile_defaults_few_shot_on_with_selected_strategy(): void
    {
        $profile = DefaultObservationProfiles::reasoning();

        // A4.2 showed few-shot STRONGLY helps this class (qwen3:1.7b 74.2% → 91.4%); selected is the
        // chosen default (deterministic, per-case, no aggregate regression vs blind).
        $this->assertTrue($profile->fewShotDefault, 'Reasoning must default few-shot on.');
        $this->assertSame(ExemplarStrategy::Selected, $profile->defaultExemplarStrategy);
    }

    public function test_shipped_reasoning_profile_carries_no_prompt_variant_by_default(): void
    {
        // A6.2: the it_intent_guidance variant stays CLI-only — the A6.2 benchmark showed it fixes 4
        // targeted cases but regresses schedule_meeting/unknown and adds validation failures, so it
        // must NOT be a profile default. (The mechanism below proves a profile *could* carry one.)
        $this->assertNull(DefaultObservationProfiles::reasoning()->promptVariantId);
        $this->assertNull(DefaultObservationProfiles::instructionFollowing()->promptVariantId);
    }

    public function test_a_profile_can_carry_and_resolve_a_prompt_variant(): void
    {
        // Proves profile-level promptVariantId is honored end-to-end (id round-trips to the enum),
        // so enabling it by default later is a one-line change if a future benchmark justifies it.
        $profile = new \Fluxio\Actions\Diagnostics\Profiles\ObservationProfile(
            id: 'reasoning',
            capabilityClass: ModelCapabilityClass::Reasoning,
            think: false,
            forceJson: true,
            promptVariantId: 'it_intent_guidance',
        );

        $this->assertSame('it_intent_guidance', $profile->toArray()['prompt_variant']);
        $this->assertSame(\Fluxio\Actions\Llm\Prompting\PromptVariant::ItIntentGuidance, \Fluxio\Actions\Llm\Prompting\PromptVariant::fromString((string) $profile->promptVariantId));
    }

    public function test_profile_few_shot_policy_is_serialized_in_to_array(): void
    {
        $this->assertSame(false, DefaultObservationProfiles::instructionFollowing()->toArray()['few_shot_default']);
        $this->assertNull(DefaultObservationProfiles::instructionFollowing()->toArray()['default_exemplar_strategy']);
        $this->assertSame(true, DefaultObservationProfiles::reasoning()->toArray()['few_shot_default']);
        $this->assertSame('selected', DefaultObservationProfiles::reasoning()->toArray()['default_exemplar_strategy']);
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
