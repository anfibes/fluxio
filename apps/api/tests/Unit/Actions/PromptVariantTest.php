<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Llm\Prompting\PromptVariant;
use Tests\TestCase;

/**
 * Slice A6.2 — diagnostics-only prompt variant value object. Pure, no I/O.
 */
class PromptVariantTest extends TestCase
{
    public function test_from_string_resolves_the_known_variant(): void
    {
        $this->assertSame(PromptVariant::ItIntentGuidance, PromptVariant::fromString('it_intent_guidance'));
        $this->assertSame(PromptVariant::ItIntentGuidance, PromptVariant::fromString('  IT_INTENT_GUIDANCE  '));
    }

    public function test_from_string_returns_null_for_unknown_variant(): void
    {
        $this->assertNull(PromptVariant::fromString('does_not_exist'));
        $this->assertNull(PromptVariant::fromString(''));
    }

    public function test_guidance_is_intent_selection_only_and_starts_with_a_blank_line(): void
    {
        $lines = PromptVariant::ItIntentGuidance->guidanceLines();

        // Append-only: the block begins with a blank separator line.
        $this->assertSame('', $lines[0]);

        $text = implode("\n", $lines);
        // Targets the A6.1 intent clusters...
        $this->assertStringContainsString('create_task', $text);
        $this->assertStringContainsString('assign_lead', $text);
        $this->assertStringContainsString('prepare_contract_from_quote', $text);
        $this->assertStringContainsString('unknown', $text);
        // ...and is scoped to intent selection, not entity formatting.
        $this->assertStringContainsString('choose the intent only', $text);
        $this->assertStringNotContainsString('date_expression', $text);
        $this->assertStringNotContainsString('time_expression', $text);
    }
}
