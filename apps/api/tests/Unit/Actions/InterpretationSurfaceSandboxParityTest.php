<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Tests\TestCase;

/**
 * Phase 9F.1A/9F.1B — parity guardrail between the LLM-advertised interpretation
 * surface and the runtime-owned grammar descriptor.
 *
 * The prompt builder and the structured-output validator must both derive their allowed
 * entity keys from the single InterpretationGrammar descriptor (which is itself narrowed
 * to the frozen ProviderSandboxContract). These tests fail loudly if either consumer
 * re-grows its own derivation or drifts from the descriptor: unsupported `*_query` keys
 * (participant_query, user_query) stay rejected; lead_query stays accepted.
 */
class InterpretationSurfaceSandboxParityTest extends TestCase
{
    private InterpretationGrammar $grammar;

    private InterpretationPromptBuilder $promptBuilder;

    private LlmStructuredOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar       = $this->app->make(InterpretationGrammar::class);
        $this->promptBuilder = $this->app->make(InterpretationPromptBuilder::class);
        $this->validator     = $this->app->make(LlmStructuredOutputValidator::class);
    }

    // ── Prompt renders the descriptor ─────────────────────────────────────────

    public function test_prompt_advertises_exactly_the_descriptor_keys_per_intent(): void
    {
        $prompt = $this->promptBuilder->buildSystemPrompt();

        foreach ($this->grammar->entityKeysByIntent() as $intent => $keys) {
            // The prompt builder renders each intent's descriptor keys verbatim.
            $this->assertStringContainsString(
                '- '.$intent.': '.implode(', ', $keys),
                $prompt,
                "Prompt does not render the descriptor keys for intent [{$intent}].",
            );
        }
    }

    public function test_forbidden_reference_keys_are_never_advertised(): void
    {
        $prompt = $this->promptBuilder->buildSystemPrompt();

        $this->assertStringNotContainsString('participant_query', $prompt);
        $this->assertStringNotContainsString('user_query', $prompt);
        // The generic entityType marker is not a real entity key.
        $this->assertStringNotContainsString('scalar', $prompt);
    }

    public function test_allowed_reference_key_is_still_advertised(): void
    {
        $this->assertStringContainsString('lead_query', $this->promptBuilder->buildSystemPrompt());
    }

    // ── Validator enforces the descriptor ─────────────────────────────────────

    public function test_validator_accepts_exactly_the_descriptor_keys(): void
    {
        $keys = $this->grammar->allowedEntityKeys('schedule_call');

        // A payload using every descriptor key (with non-empty scalar values) is accepted.
        $result = $this->validator->validate([
            'intent'     => 'schedule_call',
            'confidence' => 0.8,
            'entities'   => array_fill_keys($keys, 'x'),
        ]);
        $this->assertTrue($result->valid, implode(' | ', $result->errors));

        // A key outside the descriptor is rejected as incompatible.
        $rejected = $this->validator->validate([
            'intent'     => 'schedule_call',
            'confidence' => 0.8,
            'entities'   => ['not_a_descriptor_key' => 'x'],
        ]);
        $this->assertFalse($rejected->valid);
        $this->assertStringContainsString(
            'Entity key [not_a_descriptor_key] is not compatible with intent [schedule_call].',
            implode(' | ', $rejected->errors),
        );
    }

    public function test_validator_rejects_participant_query_reference_key(): void
    {
        $result = $this->validator->validate([
            'intent'     => 'schedule_call',
            'confidence' => 0.8,
            'entities'   => ['participant_query' => 'Marco'],
        ]);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString(
            'Entity key [participant_query] is not compatible with intent [schedule_call].',
            implode(' | ', $result->errors),
        );
    }

    public function test_validator_rejects_user_query_reference_key(): void
    {
        $result = $this->validator->validate([
            'intent'     => 'assign_lead',
            'confidence' => 0.8,
            'entities'   => ['user_query' => 'Marco'],
        ]);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString(
            'Entity key [user_query] is not compatible with intent [assign_lead].',
            implode(' | ', $result->errors),
        );
    }

    public function test_validator_still_accepts_lead_query_reference_key(): void
    {
        $result = $this->validator->validate([
            'intent'     => 'schedule_call',
            'confidence' => 0.8,
            'entities'   => ['lead_query' => 'Rossi'],
        ]);

        $this->assertTrue($result->valid, implode(' | ', $result->errors));
    }
}
