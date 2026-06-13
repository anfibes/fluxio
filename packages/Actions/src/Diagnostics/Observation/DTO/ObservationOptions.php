<?php

namespace Fluxio\Actions\Diagnostics\Observation\DTO;

/**
 * Diagnostics-only transport knobs for the A7.1 root-cause experiments.
 *
 * The default instance is byte-identical to the prior observation behavior (forced JSON, no
 * thinking override, backend-default generation budget), so existing diagnostics and the runtime
 * path are unaffected. Each field maps to one experiment:
 *
 *  - $think     E1 — disable a reasoning model's thinking (false) while keeping prompt/format/validator.
 *  - $forceJson E2 — when false, do NOT force `format=json`; the model emits free text and the
 *               diagnostic extracts the JSON object before running the SAME validator (no relaxation).
 *  - $maxTokens E3 — raise the generation budget (Ollama num_predict) to test truncation.
 *
 * These never reach the runtime: only LlmObservationService (diagnostics) reads them.
 */
final readonly class ObservationOptions
{
    public function __construct(
        public ?bool $think = null,
        public bool $forceJson = true,
        public ?int $maxTokens = null,
    ) {}
}
