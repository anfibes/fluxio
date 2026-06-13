<?php

namespace Fluxio\Actions\Diagnostics\Observation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\ProviderSandboxContract;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmStructuredOutput;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Fluxio\Actions\Validation\NormalizedCommandValidator;
use Throwable;

/**
 * Diagnostics-only LLM observation pipeline.
 *
 * Replays the SAME collaborators the opt-in Ollama provider uses — InterpretationPromptBuilder
 * builds the strict prompt, LlmClientInterface performs one generation, LlmStructuredOutputValidator
 * checks the JSON contract, and LlmStructuredOutput maps a validated payload — but instead of
 * mapping straight to a NormalizedCommand and failing closed, it CAPTURES every stage (raw text,
 * parsed JSON, validation outcome, the candidate NormalizedCommand, and the adapter-equivalent
 * sandbox / runtime-validation outcome) so a developer can inspect raw model behavior.
 *
 * It is observation only: it builds no proposals, never touches ActionInterpreterService, resolves
 * no entities, executes nothing, and persists nothing. Provider failures are captured as data, not
 * rethrown, so one failing case never aborts the run. It is NOT runtime and NOT a provider.
 *
 * It deliberately mirrors OllamaInterpretationProvider's payload→command mapping (via the shared
 * LlmStructuredOutput DTO) rather than redesigning the provider to expose intermediates.
 */
class LlmObservationService
{
    public function __construct(
        private readonly LlmClientInterface $client,
        private readonly InterpretationPromptBuilder $promptBuilder,
        private readonly LlmStructuredOutputValidator $structuredValidator,
        private readonly NormalizedCommandValidator $normalizedValidator,
        private readonly ProviderSandboxContract $sandbox = new ProviderSandboxContract,
    ) {}

    /**
     * Default shipped observation corpus:
     * packages/Actions/evaluation/llm-observation-cases.json
     * (src/Diagnostics/Observation → src/Diagnostics → src → Actions → evaluation)
     */
    public static function defaultCasesPath(): string
    {
        return __DIR__.'/../../../evaluation/llm-observation-cases.json';
    }

    /**
     * @param  list<\Fluxio\Actions\Llm\Prompting\PromptExemplar>  $exemplars  Optional few-shot
     *                                                                         exemplars. Empty (default) keeps the system prompt byte-identical to the runtime
     *                                                                         path; supplying them is an opt-in diagnostics affordance.
     */
    public function observe(InterpretationCorpusCase $case, array $exemplars = []): LlmObservationResult
    {
        $context = new InterpretationContext(locale: $case->locale);

        $request = new LlmRequest(
            prompt: $this->promptBuilder->buildUserPrompt($case->text, $context),
            systemPrompt: $this->promptBuilder->buildSystemPrompt($exemplars),
            temperature: 0.0,
            // Signals structured JSON to the transport; the contract is enforced by the validator.
            jsonSchema: ['type' => 'object'],
        );

        try {
            $response = $this->client->generateStructured($request);
        } catch (Throwable $e) {
            return $this->failure($case, $e);
        }

        $raw = $response->rawText;
        $parsed = is_array($response->parsedJson) ? $response->parsedJson : null;

        if ($parsed === null) {
            return $this->completed($case, $raw, null, false, ['Provider returned no structured JSON object.'], null, [], []);
        }

        $validation = $this->structuredValidator->validate($parsed);

        if (! $validation->valid) {
            return $this->completed($case, $raw, $parsed, false, $validation->errors, null, [], []);
        }

        // Mirror the provider's validated-payload → NormalizedCommand mapping (shared DTO).
        $output = LlmStructuredOutput::fromValidatedPayload($parsed);
        $command = new NormalizedCommand(
            intent: $output->intent,
            confidence: $output->confidence,
            sourceText: $case->text,
            locale: $case->locale,
            entities: $output->entities,
            warnings: $output->notes,
        );

        // Adapter-equivalent outcome: would this command pass NormalizedCommandValidator + the sandbox?
        $normalizedErrors = $this->normalizedValidator->validate($command)->errors;
        $sandboxViolations = $this->sandbox->violations($command);

        return $this->completed($case, $raw, $parsed, true, [], $command, $sandboxViolations, $normalizedErrors);
    }

    private function failure(InterpretationCorpusCase $case, Throwable $e): LlmObservationResult
    {
        return new LlmObservationResult(
            id: $case->id,
            text: $case->text,
            locale: $case->locale,
            expectedIntent: $case->expectedIntent,
            expectedEntities: $case->expectedEntities,
            notes: $case->notes,
            providerSucceeded: false,
            failureClass: $e::class,
            failureMessage: $e->getMessage(),
            rawResponse: null,
            parsedJson: null,
            structuredOutputValid: null,
            structuredOutputErrors: [],
            normalizedCommand: null,
            sandboxViolations: [],
            normalizedValidationErrors: [],
            intentMatch: null,
            entityMatch: null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $parsed
     * @param  list<string>  $structuredErrors
     * @param  list<string>  $sandboxViolations
     * @param  list<string>  $normalizedErrors
     */
    private function completed(
        InterpretationCorpusCase $case,
        ?string $raw,
        ?array $parsed,
        ?bool $structuredValid,
        array $structuredErrors,
        ?NormalizedCommand $command,
        array $sandboxViolations,
        array $normalizedErrors,
    ): LlmObservationResult {
        $normalized = $command === null ? null : [
            'intent' => $command->intent,
            'confidence' => $command->confidence,
            'entities' => $command->entities,
            'warnings' => $command->warnings,
        ];

        return new LlmObservationResult(
            id: $case->id,
            text: $case->text,
            locale: $case->locale,
            expectedIntent: $case->expectedIntent,
            expectedEntities: $case->expectedEntities,
            notes: $case->notes,
            providerSucceeded: true,
            failureClass: null,
            failureMessage: null,
            rawResponse: $raw,
            parsedJson: $parsed,
            structuredOutputValid: $structuredValid,
            structuredOutputErrors: $structuredErrors,
            normalizedCommand: $normalized,
            sandboxViolations: $sandboxViolations,
            normalizedValidationErrors: $normalizedErrors,
            intentMatch: $command === null ? null : $command->intent === $case->expectedIntent,
            entityMatch: $command === null ? null : $this->entitiesMatch($command->entities, $case->expectedEntities),
        );
    }

    /**
     * Partial exact match: every expected key must be present with an identical (===) value.
     * Empty expectations match vacuously. No fuzzy or semantic comparison.
     *
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $expected
     */
    private function entitiesMatch(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual) || $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
