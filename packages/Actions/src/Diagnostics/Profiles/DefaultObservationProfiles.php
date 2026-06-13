<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

/**
 * Diagnostics-only factory for the default observation profile per capability class.
 *
 * One profile per class lives here; there is no per-model profile and no model-string logic.
 */
final class DefaultObservationProfiles
{
    /**
     * Instruction-following models comply with the strict forced-JSON contract directly. This
     * profile is byte-identical to the prior (and runtime) behavior: no thinking override
     * (`think = null`, so the backend default applies) and `format=json` forced.
     */
    public static function instructionFollowing(): ObservationProfile
    {
        return new ObservationProfile(
            id: 'instruction-following',
            capabilityClass: ModelCapabilityClass::InstructionFollowing,
            think: null,
            forceJson: true,
        );
    }

    /**
     * Reasoning models need reasoning-aware driving. A7.1 isolated two strategies that both recover
     * a "thinking" model that otherwise scores 0% under forced JSON:
     *
     *   A) think:false + format=json   → qwen3:1.7b: 98.0% contract-valid, 76.5% intent-match
     *   B) thinking on + free-text+extract → qwen3:1.7b: 96.1% contract-valid, 88.2% intent-match
     *
     * The default is **strategy A (think:false + format=json)**. Although B scored higher
     * intent-match (+11.7pp), A is chosen as the default because it:
     *   - keeps the EXACT forced-JSON contract the runtime uses, so the diagnostic measures the
     *     model against the real fail-closed boundary rather than a parallel free-text-extraction
     *     surface;
     *   - is faster and more robust (B re-runs full reasoning traces per case — markedly slower,
     *     and produced a transport timeout in the A7.1 run);
     *   - recovers the model decisively (0% → 98% contract-valid).
     *
     * Strategy B remains available as a max-accuracy probe via the `--free-text` CLI flag, which
     * overrides this profile.
     */
    public static function reasoning(): ObservationProfile
    {
        return new ObservationProfile(
            id: 'reasoning',
            capabilityClass: ModelCapabilityClass::Reasoning,
            think: false,
            forceJson: true,
        );
    }

    public static function forClass(ModelCapabilityClass $class): ObservationProfile
    {
        return match ($class) {
            ModelCapabilityClass::InstructionFollowing => self::instructionFollowing(),
            ModelCapabilityClass::Reasoning => self::reasoning(),
        };
    }
}
