<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;

/**
 * Diagnostics-only comparison of three few-shot strategies over the same held-out corpus:
 * no few-shot (baseline/`none`), BLIND few-shot (one template per intent, registry order), and
 * SELECTED few-shot (per-case relevance-ranked exemplars). Introduced in A3.1; widened in A5 to
 * answer the benchmark questions directly.
 *
 * Per strategy it reports the full quality metric set (produced, contract-valid, intent-match,
 * entity-agreement, provider/validation failures) plus a per-intent breakdown of BOTH intent-match
 * and entity-agreement. It then reports the three pairwise comparisons the A5 slice asks for —
 * selected-vs-none, selected-vs-blind, blind-vs-none — each as an aggregate delta + per-intent
 * deltas + improved/regressed case ids, so "does selection help, and where?" is answerable without
 * re-deriving anything by hand. `selected_vs_blind` is also surfaced at the top level (with per-case
 * rows) as the headline question. Nothing here is authoritative: no proposal is built, nothing
 * executed, nothing persisted.
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
     * @param  array<string, mixed>  $comparisons
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
        public readonly array $comparisons,
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
            selectedVsBlind: self::pairwise($cases, $blind, $selected) + ['cases' => self::rows($cases, $blind, $selected)],
            comparisons: [
                'selected_vs_none' => self::pairwise($cases, $baseline, $selected),
                'selected_vs_blind' => self::pairwise($cases, $blind, $selected),
                'blind_vs_none' => self::pairwise($cases, $baseline, $blind),
            ],
        );
    }

    /**
     * Per-strategy full aggregate metrics + per-intent breakdown (intent-match AND entity-agreement).
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $results
     * @return array<string, mixed>
     */
    private static function strategySummary(array $cases, array $results): array
    {
        $metrics = IntentExampleObservationMetrics::fromResults($results)->toArray();
        $metrics['per_intent'] = self::perIntent($cases, $results);

        return $metrics;
    }

    /**
     * Per-intent intent-match and entity-agreement, grouped by the case's expected intent in
     * first-seen order. Entity agreement is only counted for intents whose cases assert entities.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $results
     * @return array<string, array{total: int, intent_match: int, entity_comparable: int, entity_agreement: int}>
     */
    private static function perIntent(array $cases, array $results): array
    {
        $breakdown = [];

        foreach ($cases as $i => $case) {
            $intent = $case->expectedIntent;
            $breakdown[$intent] ??= ['total' => 0, 'intent_match' => 0, 'entity_comparable' => 0, 'entity_agreement' => 0];
            $breakdown[$intent]['total']++;

            $result = $results[$i] ?? null;

            if ($result?->intentMatch === true) {
                $breakdown[$intent]['intent_match']++;
            }

            if ($case->expectedEntities !== []) {
                $breakdown[$intent]['entity_comparable']++;

                if ($result?->entityMatch === true) {
                    $breakdown[$intent]['entity_agreement']++;
                }
            }
        }

        return $breakdown;
    }

    /**
     * One pairwise strategy comparison (intent-match): aggregate delta + verdict, per-case
     * improved/regressed counts and ids, and a per-intent intent-match delta. "Improved" = a case
     * the FROM strategy missed and the TO strategy matched; "regressed" = the reverse.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $from
     * @param  list<LlmObservationResult>  $to
     * @return array<string, mixed>
     */
    private static function pairwise(array $cases, array $from, array $to): array
    {
        $improvedIds = [];
        $regressedIds = [];
        $fromMatch = 0;
        $toMatch = 0;
        $improved = 0;
        $regressed = 0;
        $matchedPass = 0;
        $matchedFail = 0;

        /** @var array<string, array{from: int, to: int}> $perIntent */
        $perIntent = [];

        foreach ($cases as $i => $case) {
            $fPass = ($from[$i] ?? null)?->intentMatch === true;
            $tPass = ($to[$i] ?? null)?->intentMatch === true;

            $fromMatch += $fPass ? 1 : 0;
            $toMatch += $tPass ? 1 : 0;

            $perIntent[$case->expectedIntent] ??= ['from' => 0, 'to' => 0];
            $perIntent[$case->expectedIntent]['from'] += $fPass ? 1 : 0;
            $perIntent[$case->expectedIntent]['to'] += $tPass ? 1 : 0;

            $outcome = match (true) {
                $fPass && $tPass => 'matched_pass',
                ! $fPass && ! $tPass => 'matched_fail',
                ! $fPass && $tPass => 'improved',
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
        }

        $delta = $toMatch - $fromMatch;

        $perIntentOut = [];
        foreach ($perIntent as $intent => $counts) {
            $perIntentOut[$intent] = [
                'from' => $counts['from'],
                'to' => $counts['to'],
                'delta' => $counts['to'] - $counts['from'],
            ];
        }

        return [
            'verdict' => $delta > 0 ? 'improved' : ($delta < 0 ? 'regressed' : 'matched'),
            'from_intent_match' => $fromMatch,
            'to_intent_match' => $toMatch,
            'intent_match_delta' => $delta,
            'improved' => $improved,
            'regressed' => $regressed,
            'matched_pass' => $matchedPass,
            'matched_fail' => $matchedFail,
            'improved_ids' => $improvedIds,
            'regressed_ids' => $regressedIds,
            'per_intent' => $perIntentOut,
        ];
    }

    /**
     * Per-case rows for the headline selected-vs-blind view.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $blind
     * @param  list<LlmObservationResult>  $selected
     * @return list<array<string, mixed>>
     */
    private static function rows(array $cases, array $blind, array $selected): array
    {
        $rows = [];

        foreach ($cases as $i => $case) {
            $bPass = ($blind[$i] ?? null)?->intentMatch === true;
            $sPass = ($selected[$i] ?? null)?->intentMatch === true;

            $rows[] = [
                'id' => $case->id,
                'expected_intent' => $case->expectedIntent,
                'blind_intent' => self::intentOf($blind[$i] ?? null),
                'selected_intent' => self::intentOf($selected[$i] ?? null),
                'blind_pass' => $bPass,
                'selected_pass' => $sPass,
                'outcome' => match (true) {
                    $bPass && $sPass => 'matched_pass',
                    ! $bPass && ! $sPass => 'matched_fail',
                    ! $bPass && $sPass => 'improved',
                    default => 'regressed',
                },
            ];
        }

        return $rows;
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
            // A5: all three pairwise comparisons (aggregate + per-intent + improved/regressed ids).
            'comparisons' => $this->comparisons,
            // Headline question, kept at top level (with per-case rows) for back-compat.
            'selected_vs_blind' => $this->selectedVsBlind,
        ];
    }
}
