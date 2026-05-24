<?php

namespace Fluxio\Actions\Interpretation\Providers;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmStructuredOutputException;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;

/**
 * Opt-in LLM (Ollama) interpretation sandbox provider.
 *
 * Pipeline:
 *   user text
 *     → InterpretationPromptBuilder (strict prompt from IntentRegistry)
 *     → LlmClientInterface::generateStructured()
 *     → parsed JSON from LlmResponse
 *     → LlmStructuredOutputValidator (fail closed)
 *     → NormalizedCommand
 *
 * Boundaries — this provider ONLY produces a candidate NormalizedCommand. It
 * never builds an ActionProposal, resolves entities, decides readiness,
 * confirms, executes, or mutates proposal state. The deterministic provider
 * remains the default; this one is wired only when
 * ACTIONS_INTERPRETATION_PROVIDER=ollama.
 *
 * Fail-closed: missing/invalid structured output, or output that violates the
 * LLM structured-output contract, raises an exception. No semantic repair, no
 * fallback command, no hidden fallback chain.
 */
class OllamaInterpretationProvider implements InterpretationProviderInterface
{
    public function __construct(
        private readonly LlmClientInterface $client,
        private readonly LlmStructuredOutputValidator $validator,
        private readonly InterpretationPromptBuilder $promptBuilder,
        private readonly ?string $model = null,
        private readonly float $temperature = 0.0,
        private readonly ?int $maxTokens = null,
    ) {}

    public function interpret(string $text, InterpretationContext $context): NormalizedCommand
    {
        $request = new LlmRequest(
            prompt: $this->promptBuilder->buildUserPrompt($text, $context),
            systemPrompt: $this->promptBuilder->buildSystemPrompt(),
            model: $this->model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            // Signals structured JSON output to the transport. The schema is a
            // hint only; the real contract is enforced by the validator below.
            jsonSchema: ['type' => 'object'],
        );

        $response = $this->client->generateStructured($request);

        $payload = $response->parsedJson;

        if (! is_array($payload)) {
            throw new InvalidLlmStructuredOutputException(
                ['LLM response did not contain a structured JSON object.'],
            );
        }

        // Fail closed: any contract violation throws InvalidLlmStructuredOutputException.
        $this->validator->validateOrFail($payload);

        return $this->toNormalizedCommand($payload, $text, $context);
    }

    /**
     * @param  array<string, mixed>  $payload  Validated structured output.
     */
    private function toNormalizedCommand(array $payload, string $text, InterpretationContext $context): NormalizedCommand
    {
        /** @var array<string, mixed> $entities */
        $entities = $payload['entities'] ?? [];

        /** @var list<string> $warnings */
        $warnings = array_values($payload['notes'] ?? []);

        return new NormalizedCommand(
            intent: (string) $payload['intent'],
            confidence: (float) $payload['confidence'],
            sourceText: $text,
            locale: $context->locale,
            entities: $entities,
            warnings: $warnings,
        );
    }
}
