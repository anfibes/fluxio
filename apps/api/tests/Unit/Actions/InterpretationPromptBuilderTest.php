<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Prompting\PromptExemplar;
use Fluxio\Actions\Llm\Prompting\PromptVariant;
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
        $this->assertStringContainsString('-> prepare_contract_from_quote (lead = X, quote = Q', $this->prompt);
    }

    public function test_prompt_guides_lead_extraction_for_named_entities(): void
    {
        $this->assertStringContainsString('is the lead; put that exact label in "lead"', $this->prompt);
    }

    public function test_prompt_guides_temporal_expression_keys(): void
    {
        $this->assertStringContainsString('"date" ONLY if already written as YYYY-MM-DD', $this->prompt);
        $this->assertStringContainsString('date_expression', $this->prompt);
        $this->assertStringContainsString('Put a time in "time" ONLY if already a 24-hour HH:MM value', $this->prompt);
        $this->assertStringContainsString('time_expression', $this->prompt);
    }

    public function test_prompt_discourages_unknown_for_merely_missing_entities(): void
    {
        $this->assertStringContainsString(
            'Do NOT return "unknown" just because an entity is missing.',
            $this->prompt,
        );
    }

    // ── Phase 9F.3D: second-pass tightening from observation-suite results ─────

    public function test_prompt_maps_both_contract_phrasings_to_the_same_intent(): void
    {
        // "for X from quote Q" and "from quote Q for X" must both map to the contract intent.
        $this->assertStringContainsString('"prepare/draft a contract for X from quote Q"', $this->prompt);
        $this->assertStringContainsString('"prepare a contract from quote Q for X"', $this->prompt);
        $this->assertStringContainsString('-> prepare_contract_from_quote (lead = X, quote = Q', $this->prompt);
    }

    public function test_prompt_routes_natural_language_times_to_time_expression(): void
    {
        // am/pm and day-part words must go to time_expression, not the normalized "time" key.
        $this->assertStringContainsString('am/pm', $this->prompt);
        $this->assertStringContainsString('morning/afternoon/evening/night/sometime/around', $this->prompt);
        $this->assertStringContainsString('use "time_expression"', $this->prompt);
    }

    public function test_prompt_forbids_copying_runtime_identity_key_names_from_user_text(): void
    {
        $this->assertStringContainsString('Never copy key names out of the user text.', $this->prompt);
        $this->assertStringContainsString('selected_candidate_id', $this->prompt);
        $this->assertStringContainsString(
            'forbidden runtime/identity keys must not appear in "entities"',
            $this->prompt,
        );
    }

    // ── Phase 9F.3E: omit-instead-of-placeholder guidance (compliance, not validation) ─

    public function test_prompt_requires_omitting_unknown_keys_instead_of_emitting_empty_values(): void
    {
        $this->assertStringContainsString('OMIT the key', $this->prompt);
        // The four empty/placeholder forms the small model tends to echo.
        $this->assertStringContainsString('Never emit null', $this->prompt);
        $this->assertStringContainsString('an empty string ""', $this->prompt);
        $this->assertStringContainsString('an empty array []', $this->prompt);
        $this->assertStringContainsString('an empty object {}', $this->prompt);
        $this->assertStringContainsString('placeholder text', $this->prompt);
    }

    public function test_prompt_constrains_participants_to_non_empty_string_lists_or_omission(): void
    {
        $this->assertStringContainsString('non-empty list of name strings', $this->prompt);
        $this->assertStringContainsString('Never emit participants as an empty array', $this->prompt);
        $this->assertStringContainsString('never as objects', $this->prompt);
    }

    // ── Slice A2: optional few-shot exemplars (default byte-identical) ─────────

    public function test_prompt_is_byte_identical_when_no_exemplars_are_supplied(): void
    {
        $builder = $this->app->make(InterpretationPromptBuilder::class);

        // The no-arg call (runtime/sandbox path) and the empty-array call must be identical,
        // and must carry no few-shot block.
        $this->assertSame($builder->buildSystemPrompt(), $builder->buildSystemPrompt([]));
        $this->assertStringNotContainsString('Worked examples', $builder->buildSystemPrompt());
    }

    public function test_prompt_appends_exemplar_json_when_exemplars_are_supplied(): void
    {
        $builder = $this->app->make(InterpretationPromptBuilder::class);

        $exemplar = new PromptExemplar(
            inputText: 'Pianifica una chiamata con Bianchi il 2026-06-20 alle 09:00',
            output: ['intent' => 'schedule_call', 'confidence' => 0.9, 'entities' => ['lead' => 'Bianchi', 'date' => '2026-06-20', 'time' => '09:00'], 'notes' => []],
            sourceId: 'it-schedule-call-template',
        );

        $prompt = $builder->buildSystemPrompt([$exemplar]);

        $this->assertStringContainsString('Worked examples', $prompt);
        $this->assertStringContainsString('Input: Pianifica una chiamata con Bianchi il 2026-06-20 alle 09:00', $prompt);
        $this->assertStringContainsString('"intent":"schedule_call"', $prompt);
        // Provenance metadata is never rendered into the prompt.
        $this->assertStringNotContainsString('it-schedule-call-template', $prompt);
        // The default block is preserved (exemplars are appended, not a replacement).
        $this->assertStringContainsString('Registered intents and their allowed entity keys:', $prompt);
    }

    // ── Slice A6.2: optional, append-only prompt variant (default byte-identical) ─

    public function test_prompt_is_byte_identical_when_no_variant_is_supplied(): void
    {
        $builder = $this->app->make(InterpretationPromptBuilder::class);

        // The no-arg call (runtime path) equals the explicit null-variant call, and carries no variant.
        $this->assertSame($builder->buildSystemPrompt(), $builder->buildSystemPrompt([], null));
        $this->assertStringNotContainsString('Intent selection hints for Italian input', $builder->buildSystemPrompt());
    }

    public function test_variant_block_is_appended_only_when_requested(): void
    {
        $builder = $this->app->make(InterpretationPromptBuilder::class);

        $base = $builder->buildSystemPrompt();
        $withVariant = $builder->buildSystemPrompt([], PromptVariant::ItIntentGuidance);

        $this->assertStringContainsString('Intent selection hints for Italian input', $withVariant);
        // Append-only: the entire base prompt is a prefix of the variant prompt.
        $this->assertStringStartsWith($base, $withVariant);
        // Intent-selection only — the variant adds no entity-formatting rules.
        $this->assertStringContainsString('use these to choose the intent only', $withVariant);
    }

    public function test_variant_and_exemplars_compose_without_replacing_the_base(): void
    {
        $builder = $this->app->make(InterpretationPromptBuilder::class);

        $exemplar = new PromptExemplar(
            inputText: 'Assegna Bianchi a Luca',
            output: ['intent' => 'assign_lead', 'confidence' => 0.9, 'entities' => ['lead' => 'Bianchi', 'assignee' => 'Luca'], 'notes' => []],
            sourceId: 'it-assign-lead-template',
        );

        $prompt = $builder->buildSystemPrompt([$exemplar], PromptVariant::ItIntentGuidance);

        $this->assertStringContainsString('Registered intents and their allowed entity keys:', $prompt);
        $this->assertStringContainsString('Intent selection hints for Italian input', $prompt);
        $this->assertStringContainsString('Worked examples', $prompt);
        // Variant guidance precedes the worked examples block.
        $this->assertLessThan(
            strpos($prompt, 'Worked examples'),
            strpos($prompt, 'Intent selection hints for Italian input'),
        );
    }
}
