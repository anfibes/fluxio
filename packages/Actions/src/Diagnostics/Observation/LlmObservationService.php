<?php

namespace Fluxio\Actions\Diagnostics\Observation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;
use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;
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
     * @param  string|null  $model  Optional per-request model override (diagnostics-only). Null (default)
     *                              leaves the request's model unset, so the client uses its configured
     *                              default — byte-identical to the runtime path.
     * @param  ObservationOptions|null  $options  Diagnostics-only transport knobs (A7.1 experiments).
     *                                            Null (default) = forced JSON, no thinking override, default budget —
     *                                            byte-identical to the runtime path. Never relaxes validation.
     */
    public function observe(InterpretationCorpusCase $case, array $exemplars = [], ?string $model = null, ?ObservationOptions $options = null): LlmObservationResult
    {
        $options ??= new ObservationOptions;
        $context = new InterpretationContext(locale: $case->locale);

        $request = new LlmRequest(
            prompt: $this->promptBuilder->buildUserPrompt($case->text, $context),
            // A6.2: an opt-in, append-only prompt variant (null by default → byte-identical to runtime).
            systemPrompt: $this->promptBuilder->buildSystemPrompt($exemplars, $options->promptVariant),
            model: $model,
            temperature: 0.0,
            maxTokens: $options->maxTokens,
            // Signals structured JSON to the transport; the contract is enforced by the validator.
            // E2 (forceJson=false) drops format=json so a reasoning model can emit free text.
            jsonSchema: $options->forceJson ? ['type' => 'object'] : null,
            think: $options->think,
        );

        try {
            $response = $this->client->generateStructured($request);
        } catch (Throwable $e) {
            return $this->failure($case, $e);
        }

        $raw = $response->rawText;
        $rawBody = $response->rawBody;

        // When JSON is forced the client decodes it; otherwise (E2) extract the first JSON object
        // from the free text. Either way the SAME validator judges the candidate — no relaxation.
        if ($options->forceJson) {
            $parsed = is_array($response->parsedJson) ? $response->parsedJson : null;
            $emptyMessage = 'Provider returned no structured JSON object.';
        } else {
            $parsed = JsonObjectExtractor::extract($raw);
            $emptyMessage = 'Provider returned no extractable JSON object in free-text mode.';
        }

        if ($parsed === null) {
            return $this->completed($case, $raw, null, false, [$emptyMessage], null, [], [], $rawBody);
        }

        // A6.2 de-confound: a reasoning model driven with think=false can echo Ollama's internal
        // control directive (e.g. "/no_think") into an entity value. That is a transport artifact,
        // never a real interpretation, so drop any entity whose value is exactly a control token
        // BEFORE validation/comparison. The raw response/body are left untouched for transparency,
        // so this never hides a genuine interpretation error — it only stops the artifact from
        // appearing in the extracted entities. Diagnostics-only.
        $parsed = $this->stripControlTokenEntities($parsed);

        $validation = $this->structuredValidator->validate($parsed);

        if (! $validation->valid) {
            return $this->completed($case, $raw, $parsed, false, $validation->errors, null, [], [], $rawBody);
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

        return $this->completed($case, $raw, $parsed, true, [], $command, $sandboxViolations, $normalizedErrors, $rawBody);
    }

    /**
     * Reasoning-model control directives that must never survive as entity values. These are
     * transport/template artifacts (the qwen3 "/think" soft switches), not user content.
     *
     * @var list<string>
     */
    private const CONTROL_TOKENS = ['/no_think', '/think'];

    /**
     * Drop any entity whose (trimmed) string value is exactly a reasoning control token. Only the
     * `entities` map is touched; intent/confidence/notes are untouched. A standalone control token
     * is never a legitimate entity value, so removing it cannot hide a real interpretation.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function stripControlTokenEntities(array $parsed): array
    {
        if (! isset($parsed['entities']) || ! is_array($parsed['entities'])) {
            return $parsed;
        }

        foreach ($parsed['entities'] as $key => $value) {
            if (is_string($value) && in_array(strtolower(trim($value)), self::CONTROL_TOKENS, true)) {
                unset($parsed['entities'][$key]);
            }
        }

        return $parsed;
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
     * @param  array<string, mixed>|null  $rawBody
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
        ?array $rawBody = null,
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
            rawBody: $rawBody,
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
