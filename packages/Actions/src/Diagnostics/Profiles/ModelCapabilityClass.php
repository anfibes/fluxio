<?php

namespace Fluxio\Actions\Diagnostics\Profiles;

/**
 * Diagnostics-only capability class for an LLM, i.e. *how it must be driven* to produce
 * contract-compatible structured output — NOT a model-family or size label.
 *
 * A7.1 proved the axis empirically: an `instruction_following` model complies with the forced
 * `format=json` contract directly (e.g. qwen3:0.6b: 96% contract-valid at baseline), while a
 * `reasoning` model's thinking collides with forced JSON and collapses to an empty `{}` unless
 * it is driven differently (e.g. qwen3:1.7b: 0% → 98% once thinking is disabled).
 *
 * This is the single classification axis. Concrete model → class mapping lives ONLY in
 * ModelCapabilityRegistry; nothing else in the codebase compares model strings. It is consumed
 * exclusively by diagnostics; the runtime interpretation path never sees it.
 */
enum ModelCapabilityClass: string
{
    /** Complies with the strict forced-JSON contract directly (the existing default behavior). */
    case InstructionFollowing = 'instruction_following';

    /** A "thinking" model that needs reasoning-aware driving to stay contract-compatible. */
    case Reasoning = 'reasoning';
}
