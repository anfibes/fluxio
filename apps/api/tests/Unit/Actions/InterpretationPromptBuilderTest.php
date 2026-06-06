<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Tests\TestCase;

/**
 * Phase 9F.3C — the prompt-contract guidance tightened from real qwen3:0.6b
 * diagnostics. These assert the critical guidance is present and stable; they do
 * not pin the whole prompt verbatim (the per-intent key lines are covered by
 * InterpretationSurfaceSandboxParityTest, which keeps the prompt faithful to the
 * runtime-owned grammar). Prompt-only: no runtime behaviour is asserted here.
 */
class InterpretationPromptBuilderTest extends TestCase
{
    private string $prompt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prompt = $this->app->make(InterpretationPromptBuilder::class)->buildSystemPrompt();
    }

    public function test_prompt_maps_the_weak_intents_explicitly(): void
    {
        // schedule_call / schedule_meeting were the weakest (returned "unknown").
        $this->assertStringContainsString('-> schedule_call', $this->prompt);
        $this->assertStringContainsString('-> schedule_meeting', $this->prompt);
        $this->assertStringContainsString('-> assign_lead (lead = X, assignee = Y)', $this->prompt);
        $this->assertStringContainsString('-> prepare_contract_from_quote (lead = X, quote = Q)', $this->prompt);
    }

    public function test_prompt_guides_lead_extraction_for_named_entities(): void
    {
        $this->assertStringContainsString('is the lead; put that exact label in "lead"', $this->prompt);
    }

    public function test_prompt_guides_temporal_expression_keys(): void
    {
        $this->assertStringContainsString('"date" ONLY if already written as YYYY-MM-DD', $this->prompt);
        $this->assertStringContainsString('date_expression', $this->prompt);
        $this->assertStringContainsString('"time" ONLY if already written as HH:MM', $this->prompt);
        $this->assertStringContainsString('time_expression', $this->prompt);
    }

    public function test_prompt_discourages_unknown_for_merely_missing_entities(): void
    {
        $this->assertStringContainsString(
            'Do NOT return "unknown" just because an entity is missing.',
            $this->prompt,
        );
    }
}
