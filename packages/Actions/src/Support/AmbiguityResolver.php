<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\Ambiguity\AmbiguityDirective;
use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\LabelSelector;
use Fluxio\Actions\DTO\Ambiguity\OrdinalSelector;
use Fluxio\Actions\DTO\Ambiguity\ResolutionOutcome;

/**
 * Deterministic ambiguity resolution authority (Phase 8C). Given a validated
 * AmbiguityDirective and the CURRENT candidate set, it computes the outcome —
 * resolved | narrowed | unresolved. It is the ambiguity-side parallel of the
 * structural mutation application authority and owns candidate matching,
 * narrowing, and deterministic selection.
 *
 * Invariants:
 *  - It NEVER reads candidate identity (the `id` field) for matching. Matching is
 *    by presentation order, visible label, or a generic candidate attribute. The
 *    resolved candidate (with its id) is returned so the runtime can set
 *    selected_candidate_id — identity is produced here, never supplied by a
 *    provider.
 *  - It is order-preserving and assumes candidates arrive in a stable total order
 *    (confidence-desc, as produced upstream); it does not re-sort. Ordinals and
 *    narrowing therefore stay meaningful and reproducible across refinements.
 *  - resolve-vs-narrow is computed from the candidate set, never declared by the
 *    selector: the same attribute selector resolves when one candidate matches and
 *    narrows when several do.
 *
 * Behaviour is parity-equivalent to the current ActionProposalRefinementService
 * resolveCandidate()/tryNarrowAmbiguity() logic, but generic: no hardcoded
 * company/person branches.
 *
 * @param  array<int, array<string, mixed>>  $candidates
 */
class AmbiguityResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function resolve(AmbiguityDirective $directive, array $candidates): ResolutionOutcome
    {
        $candidates = array_values($candidates);
        $selector = $directive->selector;

        return match (true) {
            $selector instanceof OrdinalSelector => $this->byOrdinal($selector, $candidates),
            $selector instanceof LabelSelector => $this->byLabel($selector, $candidates),
            $selector instanceof AttributeSelector => $this->byAttribute($selector, $candidates),
            default => ResolutionOutcome::unresolved('unsupported_selector'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function byOrdinal(OrdinalSelector $selector, array $candidates): ResolutionOutcome
    {
        $index = $selector->position - 1;

        return isset($candidates[$index])
            ? ResolutionOutcome::resolved($candidates[$index])
            : ResolutionOutcome::unresolved('out_of_range');
    }

    /**
     * Exact label match first, then a single partial (contains) match. Matching is
     * case-insensitive against the candidate LABEL only. Zero or multiple partial
     * matches are Unresolved (parity: label never narrows).
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function byLabel(LabelSelector $selector, array $candidates): ResolutionOutcome
    {
        $needle = mb_strtolower(trim($selector->text));

        foreach ($candidates as $candidate) {
            if (mb_strtolower((string) ($candidate['label'] ?? '')) === $needle) {
                return ResolutionOutcome::resolved($candidate);
            }
        }

        $partial = array_values(array_filter(
            $candidates,
            fn (array $c): bool => $needle !== '' && str_contains(mb_strtolower((string) ($c['label'] ?? '')), $needle),
        ));

        return count($partial) === 1
            ? ResolutionOutcome::resolved($partial[0])
            : ResolutionOutcome::unresolved(count($partial) === 0 ? 'no_match' : 'ambiguous');
    }

    /**
     * Generic attribute filter: candidate[dimension] === value. One match
     * resolves; several narrow (order preserved); none is Unresolved. No
     * hardcoded dimensions — the company/person case is just dimension='type'.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function byAttribute(AttributeSelector $selector, array $candidates): ResolutionOutcome
    {
        $matches = array_values(array_filter(
            $candidates,
            fn (array $c): bool => ($c[$selector->dimension] ?? null) === $selector->value,
        ));

        return match (true) {
            count($matches) === 0 => ResolutionOutcome::unresolved('no_match'),
            count($matches) === 1 => ResolutionOutcome::resolved($matches[0]),
            default => ResolutionOutcome::narrowed($matches),
        };
    }
}
