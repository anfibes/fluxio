<?php

namespace Tests\Unit\Actions\Ambiguity;

use Fluxio\Actions\DTO\Ambiguity\AmbiguityDirective;
use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\LabelSelector;
use Fluxio\Actions\DTO\Ambiguity\OrdinalSelector;
use Fluxio\Actions\Support\AmbiguityResolver;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8C: the deterministic ambiguity resolution authority. Selectors produce
 * outcomes from the CURRENT candidate set (resolve / narrow / unresolved); the
 * provider never decides the outcome and never references identity. Pure unit.
 *
 * The fixture mirrors the canonical "Rossi" lead ambiguity used across the
 * existing AmbiguousProposalTest, in confidence-desc order:
 *   [Rossi SRL (company), Mario Rossi (person), Studio Rossi (company)].
 */
class AmbiguityResolverTest extends TestCase
{
    private AmbiguityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AmbiguityResolver;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rossiCandidates(): array
    {
        return [
            ['id' => 7, 'label' => 'Rossi SRL', 'type' => 'company', 'confidence' => 0.8],
            ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person', 'confidence' => 0.65],
            ['id' => 9, 'label' => 'Studio Rossi', 'type' => 'company', 'confidence' => 0.65],
        ];
    }

    private function directive(\Fluxio\Actions\DTO\Ambiguity\AmbiguitySelector $selector): AmbiguityDirective
    {
        return new AmbiguityDirective('lead', $selector);
    }

    private function labels(array $candidates): array
    {
        return array_map(fn (array $c) => $c['label'], $candidates);
    }

    public function test_attribute_selector_narrows_when_multiple_match(): void
    {
        $outcome = $this->resolver->resolve(
            $this->directive(new AttributeSelector('type', 'company')),
            $this->rossiCandidates(),
        );

        $this->assertTrue($outcome->isNarrowed());
        $this->assertCount(2, $outcome->narrowedCandidates);
        // Order preserved; the person candidate is removed.
        $this->assertSame(['Rossi SRL', 'Studio Rossi'], $this->labels($outcome->narrowedCandidates));
    }

    public function test_attribute_selector_resolves_when_one_matches(): void
    {
        $outcome = $this->resolver->resolve(
            $this->directive(new AttributeSelector('type', 'person')),
            $this->rossiCandidates(),
        );

        $this->assertTrue($outcome->isResolved());
        $this->assertSame('Mario Rossi', $outcome->selectedCandidate['label']);
    }

    public function test_attribute_selector_with_no_match_is_unresolved(): void
    {
        $outcome = $this->resolver->resolve(
            $this->directive(new AttributeSelector('type', 'ghost')),
            $this->rossiCandidates(),
        );

        $this->assertTrue($outcome->isUnresolved());
        $this->assertSame('no_match', $outcome->reason);
    }

    public function test_ordinal_selector_resolves_to_position(): void
    {
        $first = $this->resolver->resolve($this->directive(new OrdinalSelector(1)), $this->rossiCandidates());
        $second = $this->resolver->resolve($this->directive(new OrdinalSelector(2)), $this->rossiCandidates());

        $this->assertTrue($first->isResolved());
        $this->assertSame('Rossi SRL', $first->selectedCandidate['label']);
        $this->assertSame('Mario Rossi', $second->selectedCandidate['label']);
    }

    public function test_ordinal_out_of_range_is_unresolved(): void
    {
        $outcome = $this->resolver->resolve($this->directive(new OrdinalSelector(99)), $this->rossiCandidates());

        $this->assertTrue($outcome->isUnresolved());
        $this->assertSame('out_of_range', $outcome->reason);
    }

    public function test_label_selector_resolves_exact_and_unique_partial(): void
    {
        $exact = $this->resolver->resolve($this->directive(new LabelSelector('Rossi SRL')), $this->rossiCandidates());
        $partial = $this->resolver->resolve($this->directive(new LabelSelector('Mario')), $this->rossiCandidates());

        $this->assertTrue($exact->isResolved());
        $this->assertSame('Rossi SRL', $exact->selectedCandidate['label']);
        $this->assertTrue($partial->isResolved());
        $this->assertSame('Mario Rossi', $partial->selectedCandidate['label']);
    }

    public function test_label_selector_with_ambiguous_partial_is_unresolved(): void
    {
        // "Rossi" is a substring of all three labels → not a unique match.
        $outcome = $this->resolver->resolve($this->directive(new LabelSelector('Rossi')), $this->rossiCandidates());

        $this->assertTrue($outcome->isUnresolved());
        $this->assertSame('ambiguous', $outcome->reason);
    }

    public function test_label_selector_matches_label_not_identity(): void
    {
        // "7" is the id of Rossi SRL, but matching is on the visible label only,
        // so it must NOT resolve to that candidate.
        $outcome = $this->resolver->resolve($this->directive(new LabelSelector('7')), $this->rossiCandidates());

        $this->assertTrue($outcome->isUnresolved());
    }

    public function test_narrowing_is_deterministic_and_order_preserving(): void
    {
        $a = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'company')), $this->rossiCandidates());
        $b = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'company')), $this->rossiCandidates());

        $this->assertSame($this->labels($a->narrowedCandidates), $this->labels($b->narrowedCandidates));
    }

    public function test_same_selector_resolves_after_set_shrinks_to_one(): void
    {
        // The behavioral invariant: provider does not decide resolve vs narrow.
        // The same attribute selector narrows on the full set...
        $full = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'company')), $this->rossiCandidates());
        $this->assertTrue($full->isNarrowed());

        // ...and resolves once the candidate set contains a single company.
        $shrunk = [
            ['id' => 7, 'label' => 'Rossi SRL', 'type' => 'company'],
            ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person'],
        ];
        $outcome = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'company')), $shrunk);

        $this->assertTrue($outcome->isResolved());
        $this->assertSame('Rossi SRL', $outcome->selectedCandidate['label']);
    }

    /**
     * Parity with the canonical AmbiguousProposalTest scenarios: the deterministic
     * authority reproduces the documented service outcomes.
     *  - "the company" → narrow to [Rossi SRL, Studio Rossi], still blocking
     *  - "the person"  → resolve Mario Rossi
     *  - "the first one" → resolve Rossi SRL (the highest-confidence candidate)
     */
    public function test_parity_with_existing_ambiguity_scenarios(): void
    {
        $company = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'company')), $this->rossiCandidates());
        $person = $this->resolver->resolve($this->directive(new AttributeSelector('type', 'person')), $this->rossiCandidates());
        $firstOne = $this->resolver->resolve($this->directive(new OrdinalSelector(1)), $this->rossiCandidates());

        $this->assertSame(['Rossi SRL', 'Studio Rossi'], $this->labels($company->narrowedCandidates));
        $this->assertSame('Mario Rossi', $person->selectedCandidate['label']);
        $this->assertSame('Rossi SRL', $firstOne->selectedCandidate['label']);
    }
}
