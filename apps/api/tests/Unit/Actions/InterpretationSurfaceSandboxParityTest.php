<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\ProviderSandboxContract;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Fluxio\Actions\Registry\IntentRegistry;
use Tests\TestCase;

/**
 * Phase 9F.1A — parity guardrail between the LLM-advertised interpretation surface
 * and the frozen ProviderSandboxContract.
 *
 * The prompt builder and the structured-output validator must describe exactly the
 * narrowed surface the adapter sandbox enforces: lead_query is the only resolver-backed
 * reference key, and unsupported `*_query` keys (participant_query, user_query) are never
 * advertised or accepted. These tests fail loudly if the surfaces drift apart again.
 */
class InterpretationSurfaceSandboxParityTest extends TestCase
{
    private IntentRegistry $registry;

    private InterpretationPromptBuilder $promptBuilder;

    private LlmStructuredOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry      = $this->app->make(IntentRegistry::class);
        $this->promptBuilder = new InterpretationPromptBuilder($this->registry);
        $this->validator     = new LlmStructuredOutputValidator($this->registry);
    }

    // ── Prompt-advertised keys ⊆ sandbox-legal provider surface ───────────────

    public function test_every_advertised_entity_key_is_sandbox_legal(): void
    {
        $sandbox = new ProviderSandboxContract;

        foreach ($this->registry->all() as $definition) {
            $keys = $this->promptBuilder->allowedEntityKeys($definition);

            // The sandbox inspects keys only; any non-empty value suffices here.
            $entities = array_fill_keys($keys, 'x');

            $violations = $sandbox->violations(new NormalizedCommand(
                intent: $definition->intent,
                confidence: 0.8,
                sourceText: 'test',
                locale: 'en',
                entities: $entities,
            ));

            $this->assertSame(
                [],
                $violations,
                "Intent [{$definition->intent}] advertises a non-sandbox-legal key: ".implode(' | ', $violations),
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

    // ── Validator rejects keys the sandbox forbids ────────────────────────────

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
