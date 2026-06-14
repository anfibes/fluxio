<?php

namespace Fluxio\Actions\Diagnostics\Examples;

use Fluxio\Actions\Diagnostics\Examples\DTO\ScoredExemplar;
use Fluxio\Actions\Examples\IntentExample;
use Fluxio\Actions\Examples\IntentExampleRegistry;

/**
 * Deterministic, diagnostics-only exemplar selector (Slice A3.1 — Dynamic Exemplar Selection).
 *
 * Given an evaluation case's ALREADY-KNOWN metadata (its locale, its expected intent, and the
 * entity keys it asserts), it ranks the locale's IntentExample candidates by a small, explainable
 * score and returns the most relevant ones for that case. It does NOT embed, vector-search,
 * fuzzy-match, retrieve, or re-infer intent from free text — it only reads structured metadata the
 * corpus already carries.
 *
 * Signals (in priority order, encoded as additive weights so the ordering is transparent):
 *   1. same locale          — a hard filter (candidates are pulled per locale).
 *   2. same intent          — the dominant signal (INTENT_MATCH_SCORE).
 *   3. slot overlap         — |candidate slot entity-keys ∩ target entity keys| × SLOT_OVERLAP_WEIGHT.
 *   4. template preference   — a small TEMPLATE_BONUS so a renderable template outranks a bare
 *                              literal of otherwise-equal relevance.
 *   5. diversity guard       — selection skips a candidate whose (intent + sorted slot-keys)
 *                              signature was already chosen, so near-duplicates never crowd out
 *                              variety.
 *
 * Ranking is stable: ties break by the candidate's original position, so the same inputs always
 * yield the same order. The service lives entirely in the diagnostics namespace, builds no
 * proposals, resolves no entities, and changes no runtime behavior — it only decides which
 * exemplars condition the diagnostic prompt.
 */
class ExemplarSelectionService
{
    /** Same-intent is the dominant signal: it must outweigh any achievable slot-overlap total. */
    public const INTENT_MATCH_SCORE = 100;

    /** Each overlapping slot key adds this much; keeps slot overlap a secondary tie-breaker. */
    public const SLOT_OVERLAP_WEIGHT = 10;

    /** A renderable template edges out an equally-relevant literal. */
    public const TEMPLATE_BONUS = 1;

    public function __construct(private readonly IntentExampleRegistry $registry) {}

    /**
     * Rank a candidate set against a target (pure; no registry access). Returns ScoredExemplars
     * sorted by score descending, ties broken by original index so the order is deterministic.
     *
     * @param  list<IntentExample>  $candidates
     * @param  list<string>  $targetSlotKeys  Entity keys the target case asserts (may be empty).
     * @return list<ScoredExemplar>
     */
    public function rank(array $candidates, string $targetIntent, array $targetSlotKeys): array
    {
        $targetSlotKeys = array_values(array_unique($targetSlotKeys));

        $scored = [];
        foreach ($candidates as $index => $candidate) {
            $scored[] = ['index' => $index, 'exemplar' => $this->score($candidate, $targetIntent, $targetSlotKeys)];
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['exemplar']->score <=> $a['exemplar']->score
                ?: $a['index'] <=> $b['index'];
        });

        return array_map(static fn (array $row): ScoredExemplar => $row['exemplar'], $scored);
    }

    /**
     * Rank a candidate set, then apply the diversity guard and an optional limit, returning the
     * chosen IntentExamples in ranked order (pure; no registry access).
     *
     * @param  list<IntentExample>  $candidates
     * @param  list<string>  $targetSlotKeys
     * @return list<IntentExample>
     */
    public function selectFrom(array $candidates, string $targetIntent, array $targetSlotKeys, ?int $limit = null): array
    {
        $selected = [];
        $seenSignatures = [];

        foreach ($this->rank($candidates, $targetIntent, $targetSlotKeys) as $scored) {
            if ($limit !== null && count($selected) >= $limit) {
                break;
            }

            $signature = $this->signature($scored->example);
            if (isset($seenSignatures[$signature])) {
                continue; // diversity guard: skip a near-duplicate (same intent + slot shape).
            }

            $seenSignatures[$signature] = true;
            $selected[] = $scored->example;
        }

        return $selected;
    }

    /**
     * Registry-backed convenience: select from the locale's examples (same-locale hard filter).
     *
     * @param  list<string>  $targetSlotKeys
     * @return list<IntentExample>
     */
    public function selectForLocale(string $locale, string $targetIntent, array $targetSlotKeys, ?int $limit = null): array
    {
        return $this->selectFrom($this->registry->byLocale($locale), $targetIntent, $targetSlotKeys, $limit);
    }

    /**
     * @param  list<string>  $targetSlotKeys
     */
    private function score(IntentExample $candidate, string $targetIntent, array $targetSlotKeys): ScoredExemplar
    {
        $intentMatch = $candidate->intent === $targetIntent;

        $candidateSlots = $this->slotKeysOf($candidate);
        $matchedSlots = array_values(array_intersect($candidateSlots, $targetSlotKeys));
        $slotOverlap = count($matchedSlots);

        $isTemplate = $candidate->hasTemplate();

        $score = ($intentMatch ? self::INTENT_MATCH_SCORE : 0)
            + ($slotOverlap * self::SLOT_OVERLAP_WEIGHT)
            + ($isTemplate ? self::TEMPLATE_BONUS : 0);

        return new ScoredExemplar(
            example: $candidate,
            score: $score,
            intentMatch: $intentMatch,
            slotOverlap: $slotOverlap,
            isTemplate: $isTemplate,
            matchedSlots: $matchedSlots,
        );
    }

    /**
     * Distinct entity keys a candidate's slots map to.
     *
     * @return list<string>
     */
    private function slotKeysOf(IntentExample $example): array
    {
        $keys = array_map(static fn (array $slot): string => $slot['entity_key'], array_values($example->slots));

        return array_values(array_unique($keys));
    }

    /** Diversity signature: intent + the candidate's sorted slot keys. */
    private function signature(IntentExample $example): string
    {
        $keys = $this->slotKeysOf($example);
        sort($keys);

        return $example->intent.'|'.implode(',', $keys);
    }
}
