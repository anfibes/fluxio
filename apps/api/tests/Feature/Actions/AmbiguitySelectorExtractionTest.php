<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\LabelSelector;
use Fluxio\Actions\DTO\Ambiguity\OrdinalSelector;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Tests\TestCase;

/**
 * Phase 8D.3: the interpreter extracts ambiguity selectors statelessly. It emits a
 * SemanticAmbiguityClarification (with an UNBOUND key — the service binds it) for
 * clarification-shaped text, choosing the selector by the former resolveCandidate
 * precedence: ordinal → type → label. It never reads proposal state, never matches
 * candidates, and never decides resolve-vs-narrow.
 */
class AmbiguitySelectorExtractionTest extends TestCase
{
    private RuleBasedRefinementInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interpreter = $this->app->make(RuleBasedRefinementInterpreter::class);
    }

    private function onlyClarification(string $text): SemanticAmbiguityClarification
    {
        $ops = $this->interpreter->interpret($text);
        $this->assertCount(1, $ops, "Expected exactly one operation for [{$text}].");
        $this->assertInstanceOf(SemanticAmbiguityClarification::class, $ops[0]);

        return $ops[0];
    }

    public function test_the_company_extracts_attribute_type_company(): void
    {
        $selector = $this->onlyClarification('The company')->selector;

        $this->assertInstanceOf(AttributeSelector::class, $selector);
        $this->assertSame('type', $selector->dimension);
        $this->assertSame('company', $selector->value);
    }

    public function test_the_person_extracts_attribute_type_person(): void
    {
        $selector = $this->onlyClarification('The person')->selector;

        $this->assertInstanceOf(AttributeSelector::class, $selector);
        $this->assertSame('person', $selector->value);
    }

    public function test_the_first_one_extracts_ordinal_position_one(): void
    {
        $selector = $this->onlyClarification('The first one')->selector;

        $this->assertInstanceOf(OrdinalSelector::class, $selector);
        $this->assertSame(1, $selector->position);
    }

    public function test_the_second_one_extracts_ordinal_position_two(): void
    {
        $selector = $this->onlyClarification('The second one')->selector;

        $this->assertInstanceOf(OrdinalSelector::class, $selector);
        $this->assertSame(2, $selector->position);
    }

    public function test_exact_label_extracts_label_selector(): void
    {
        $selector = $this->onlyClarification('Rossi SRL')->selector;

        $this->assertInstanceOf(LabelSelector::class, $selector);
        $this->assertSame('Rossi SRL', $selector->text);
    }

    public function test_partial_label_extracts_label_selector(): void
    {
        // Exact-vs-partial is decided downstream by the resolver; both are LabelSelector.
        $selector = $this->onlyClarification('Mario')->selector;

        $this->assertInstanceOf(LabelSelector::class, $selector);
        $this->assertSame('Mario', $selector->text);
    }

    public function test_company_abc_extracts_label_selector_not_type(): void
    {
        // A label that merely contains a type word must NOT be stolen by the type
        // selector — type extraction is strict (whole-text pure type clarification).
        $selector = $this->onlyClarification('Company ABC')->selector;

        $this->assertInstanceOf(LabelSelector::class, $selector);
        $this->assertSame('Company ABC', $selector->text);
    }

    public function test_person_studio_extracts_label_selector_not_type(): void
    {
        $selector = $this->onlyClarification('Person Studio')->selector;

        $this->assertInstanceOf(LabelSelector::class, $selector);
        $this->assertSame('Person Studio', $selector->text);
    }

    public function test_multiword_label_extracts_label_selector(): void
    {
        $selector = $this->onlyClarification('Mario Rossi')->selector;

        $this->assertInstanceOf(LabelSelector::class, $selector);
        $this->assertSame('Mario Rossi', $selector->text);
    }

    public function test_pure_type_phrases_extract_attribute_selector(): void
    {
        foreach (['company', 'companies', 'the companies', 'business', 'the business'] as $phrase) {
            $selector = $this->onlyClarification($phrase)->selector;
            $this->assertInstanceOf(AttributeSelector::class, $selector, "[{$phrase}] should be a type selector.");
            $this->assertSame('company', $selector->value, "[{$phrase}] should map to company.");
        }

        foreach (['person', 'the individual', 'people', 'the people'] as $phrase) {
            $selector = $this->onlyClarification($phrase)->selector;
            $this->assertInstanceOf(AttributeSelector::class, $selector, "[{$phrase}] should be a type selector.");
            $this->assertSame('person', $selector->value, "[{$phrase}] should map to person.");
        }
    }

    // ── Precedence ───────────────────────────────────────────────────────────

    public function test_ordinal_beats_type_when_both_present(): void
    {
        // "the first company" contains both an ordinal and a type word; ordinal must
        // win, matching the former resolveCandidate cascade (ordinal before type).
        $selector = $this->onlyClarification('The first company')->selector;

        $this->assertInstanceOf(OrdinalSelector::class, $selector);
        $this->assertSame(1, $selector->position);
    }

    public function test_ordinal_beats_label(): void
    {
        $selector = $this->onlyClarification('first')->selector;

        $this->assertInstanceOf(OrdinalSelector::class, $selector);
    }

    public function test_clarification_key_is_unbound_for_stateless_interpreter(): void
    {
        // The interpreter cannot know which ambiguity is being clarified; the key is
        // bound by the service from proposal state.
        $clarification = $this->onlyClarification('The company');

        $this->assertSame('', $clarification->ambiguityKey);
    }

    public function test_field_mutation_text_does_not_emit_a_clarification(): void
    {
        // A refinement is either a field edit or a clarification; date text yields no
        // clarification.
        $ops = $this->interpreter->interpret('Tomorrow morning');

        foreach ($ops as $op) {
            $this->assertNotInstanceOf(SemanticAmbiguityClarification::class, $op);
        }
    }

    public function test_empty_text_emits_nothing(): void
    {
        $this->assertSame([], $this->interpreter->interpret('   '));
    }
}
