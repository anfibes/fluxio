<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;

/**
 * Diagnostics-only A3.1 comparison of three few-shot strategies over the same held-out corpus:
 * no few-shot (baseline), BLIND few-shot (one template per intent, registry order), and SELECTED
 * few-shot (per-case relevance-ranked exemplars).
 *
 * It reports, per strategy, the required quality metrics (intent match, contract validity, entity
 * agreement) plus a per-intent intent-match breakdown, and then the headline A3.1 question: does
 * SELECTED few-shot improve, regress, or match BLIND few-shot? That verdict is derived from the
 * intent-match delta and is backed by per-case improved/regressed outcomes so the change is never
 * hidden inside an aggregate. Nothing here is authoritative: no proposal is built, nothing executed,
 * nothing persisted.
 */
final class ExemplarStrategyComparisonReport
{
    /**
     * @param  list<string>  $blindExampleIds
     * @param  list<string>  $selectedExampleIds  Distinct source ids selected across all cases.
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $blind
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $selectedVsBlind
     */
    public function __construct(
        public readonly string $locale,
        public readonly int $total,
        public readonly array $blindExampleIds,
        public readonly array $selectedExampleIds,
        public readonly array $baseline,
        public readonly array $blind,
        public readonly array $selected,
        public readonly array $selectedVsBlind,
    ) {}

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $baseline  index-aligned with $cases
     * @param  list<LlmObservationResult>  $blind  index-aligned with $cases
     * @param  list<LlmObservationResult>  $selected  index-aligned with $cases
     * @param  list<string>  $blindExampleIds
     * @param  list<string>  $selectedExampleIds
     */
    public static function build(
        string $locale,
        array $cases,
        array $baseline,
        array $blind,
        array $selected,
        array $blindExampleIds,
        array $selectedExampleIds,
    ): self {
        return new self(
            locale: $locale,
            total: count($cases),
            blindExampleIds: $blindExampleIds,
            selectedExampleIds: $selectedExampleIds,
            baseline: self::strategySummary($cases, $baseline),
            blind: self::strategySummary($cases, $blind),
            selected: self::strategySummary($cases, $selected),
            selectedVsBlind: self::diff($cases, $blind, $selected),
        );
    }

    /**
     * Per-strategy aggregate metrics + per-intent intent-match breakdown.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $results
     * @return array<string, mixed>
     */
    private static function strategySummary(array $cases, array $results): array
    {
        $metrics = IntentExampleObservationMetrics::fromResults($results)->toArray();

        return [
            'intent_match' => $metrics['intent_match'],
            'contract_valid' => $metrics['contract_valid'],
            'entity_agreement' => $metrics['entity_agreement'],
            'per_intent' => self::perIntent($cases, $results),
        ];
    }

    /**
     * Intent-match count/total grouped by the case's expected intent, in first-seen order.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $results
     * @return array<string, array{total: int, intent_match: int}>
     */
    private static function perIntent(array $cases, array $results): array
    {
        $breakdown = [];

        foreach ($cases as $i => $case) {
            $intent = $case->expectedIntent;
            $breakdown[$intent] ??= ['total' => 0, 'intent_match' => 0];
            $breakdown[$intent]['total']++;

            if (($results[$i] ?? null)?->intentMatch === true) {
                $breakdown[$intent]['intent_match']++;
            }
        }

        return $breakdown;
    }

    /**
     * Per-case SELECTED-vs-BLIND outcome + the improve/regress/match verdict.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $blind
     * @param  list<LlmObservationResult>  $selected
     * @return array<string, mixed>
     */
    private static function diff(array $cases, array $blind, array $selected): array
    {
        $rows = [];
        $improvedIds = [];
        $regressedIds = [];
        $blindMatch = 0;
        $selectedMatch = 0;
        $improved = 0;
        $regressed = 0;
        $matchedPass = 0;
        $matchedFail = 0;

        foreach ($cases as $i => $case) {
            $bPass = ($blind[$i] ?? null)?->intentMatch === true;
            $sPass = ($selected[$i] ?? null)?->intentMatch === true;

            $blindMatch += $bPass ? 1 : 0;
            $selectedMatch += $sPass ? 1 : 0;

            $outcome = match (true) {
                $bPass && $sPass => 'matched_pass',
                ! $bPass && ! $sPass => 'matched_fail',
                ! $bPass && $sPass => 'improved',
                default => 'regressed',
            };

            switch ($outcome) {
                case 'matched_pass': $matchedPass++;
                    break;
                case 'matched_fail': $matchedFail++;
                    break;
                case 'improved': $improved++;
                    $improvedIds[] = $case->id;
                    break;
                case 'regressed': $regressed++;
                    $regressedIds[] = $case->id;
                    break;
            }

            $rows[] = [
                'id' => $case->id,
                'expected_intent' => $case->expectedIntent,
                'blind_intent' => self::intentOf($blind[$i] ?? null),
                'selected_intent' => self::intentOf($selected[$i] ?? null),
                'blind_pass' => $bPass,
                'selected_pass' => $sPass,
                'outcome' => $outcome,
            ];
        }

        $delta = $selectedMatch - $blindMatch;

        return [
            'verdict' => $delta > 0 ? 'improved' : ($delta < 0 ? 'regressed' : 'matched'),
            'intent_match_delta' => $delta,
            'improved' => $improved,
            'regressed' => $regressed,
            'matched_pass' => $matchedPass,
            'matched_fail' => $matchedFail,
            'improved_ids' => $improvedIds,
            'regressed_ids' => $regressedIds,
            'cases' => $rows,
        ];
    }

    private static function intentOf(?LlmObservationResult $result): ?string
    {
        return $result?->normalizedCommand['intent'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'total' => $this->total,
            'few_shot' => [
                'blind' => ['count' => count($this->blindExampleIds), 'example_ids' => $this->blindExampleIds],
                'selected' => ['count' => count($this->selectedExampleIds), 'example_ids' => $this->selectedExampleIds],
            ],
            'strategies' => [
                'none' => $this->baseline,
                'blind' => $this->blind,
                'selected' => $this->selected,
            ],
            'selected_vs_blind' => $this->selectedVsBlind,
        ];
    }
}
