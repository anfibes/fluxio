<?php

namespace Fluxio\Actions\Diagnostics\Observation\DTO;

use Fluxio\Actions\Llm\Prompting\PromptVariant;

/**
 * Diagnostics-only transport + prompt knobs for the A7.1 experiments and A6.2 prompt variants.
 *
 * The default instance is byte-identical to the prior observation behavior (forced JSON, no
 * thinking override, backend-default generation budget, no prompt variant), so existing diagnostics
 * and the runtime path are unaffected. Each field maps to one knob:
 *
 *  - $think        E1 — disable a reasoning model's thinking (false) while keeping prompt/format/validator.
 *  - $forceJson    E2 — when false, do NOT force `format=json`; the model emits free text and the
 *                  diagnostic extracts the JSON object before running the SAME validator (no relaxation).
 *  - $maxTokens    E3 — raise the generation budget (Ollama num_predict) to test truncation.
 *  - $promptVariant A6.2 — append a diagnostics-only intent-selection guidance block after the base
 *                  prompt. Null (default) keeps buildSystemPrompt() byte-identical to the runtime path.
 *
 * These never reach the runtime: only LlmObservationService (diagnostics) reads them.
 */
final readonly class ObservationOptions
{
    public function __construct(
        public ?bool $think = null,
        public bool $forceJson = true,
        public ?int $maxTokens = null,
        public ?PromptVariant $promptVariant = null,
    ) {}
}
