<?php

namespace Fluxio\Actions\Llm\Prompting;

/**
 * One provider-facing few-shot exemplar for the interpretation prompt.
 *
 * It pairs a user-facing input phrase with the exact structured-output JSON the model
 * should have produced for it: an `{intent, confidence, entities, notes}` object using
 * only contract-legal entity keys. It is the SAME contract the structured-output
 * validator enforces on real model output — an exemplar is "contract-safe" iff its
 * `output` passes LlmStructuredOutputValidator.
 *
 * It is provider-facing only: it must never carry database IDs, selected_candidate_id,
 * proposal/lifecycle/execution state, or resolved entity IDs. `sourceId` is optional
 * diagnostics provenance (which IntentExample produced it) and is NEVER rendered into the
 * prompt — only `inputText` and `output` are shown to the model.
 */
final readonly class PromptExemplar
{
    /**
     * @param  array{intent: string, confidence: float, entities: array<string, mixed>, notes: list<string>}  $output
     */
    public function __construct(
        public string $inputText,
        public array $output,
        public ?string $sourceId = null,
    ) {}

    /**
     * Compact JSON rendering of the structured output, matching the prompt's example shape
     * (single line, unescaped slashes/unicode).
     */
    public function outputJson(): string
    {
        return (string) json_encode($this->output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
