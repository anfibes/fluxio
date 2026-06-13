<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;

/**
 * Diagnostics-only A5 comparison of the Italian held-out corpus run twice through the LLM
 * sandbox: once with no few-shot (baseline) and once with IntentExamples template exemplars.
 *
 * For each case it records the baseline vs few-shot intent, whether each passed (intent-match),
 * and a per-case outcome (unchanged_pass / unchanged_fail / improved / regressed) so regressions
 * are explicit rather than hidden inside an aggregate. Nothing here is authoritative: no proposal
 * is built, nothing is executed, nothing is persisted.
 */
final class ItalianCorpusComparisonReport
{
    /**
     * @param  list<string>  $fewShotExampleIds
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $improvedIds
     * @param  list<string>  $regressedIds
     */
    public function __construct(
        public readonly string $locale,
        public readonly int $total,
        public readonly array $fewShotExampleIds,
        public readonly int $baselineMatchCount,
        public readonly int $fewShotMatchCount,
        public readonly int $improvedCount,
        public readonly int $regressedCount,
        public readonly int $unchangedPassCount,
        public readonly int $unchangedFailCount,
        public readonly array $rows,
        public readonly array $improvedIds,
        public readonly array $regressedIds,
    ) {}

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<LlmObservationResult>  $baseline  index-aligned with $cases
     * @param  list<LlmObservationResult>  $fewShot  index-aligned with $cases
     * @param  list<string>  $fewShotExampleIds
     */
    public static function build(string $locale, array $cases, array $baseline, array $fewShot, array $fewShotExampleIds): self
    {
        $rows = [];
        $improvedIds = [];
        $regressedIds = [];
        $baselineMatch = 0;
        $fewShotMatch = 0;
        $improved = 0;
        $regressed = 0;
        $unchangedPass = 0;
        $unchangedFail = 0;

        foreach ($cases as $i => $case) {
            $b = $baseline[$i];
            $f = $fewShot[$i];

            $bPass = $b->intentMatch === true;
            $fPass = $f->intentMatch === true;

            $baselineMatch += $bPass ? 1 : 0;
            $fewShotMatch += $fPass ? 1 : 0;

            $outcome = match (true) {
                $bPass && $fPass => 'unchanged_pass',
                ! $bPass && ! $fPass => 'unchanged_fail',
                ! $bPass && $fPass => 'improved',
                default => 'regressed',
            };

            switch ($outcome) {
                case 'unchanged_pass': $unchangedPass++;
                    break;
                case 'unchanged_fail': $unchangedFail++;
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
                'text' => $case->text,
                'expected_intent' => $case->expectedIntent,
                'baseline_intent' => self::intentOf($b),
                'fewshot_intent' => self::intentOf($f),
                'baseline_pass' => $bPass,
                'fewshot_pass' => $fPass,
                'outcome' => $outcome,
            ];
        }

        return new self(
            locale: $locale,
            total: count($cases),
            fewShotExampleIds: $fewShotExampleIds,
            baselineMatchCount: $baselineMatch,
            fewShotMatchCount: $fewShotMatch,
            improvedCount: $improved,
            regressedCount: $regressed,
            unchangedPassCount: $unchangedPass,
            unchangedFailCount: $unchangedFail,
            rows: $rows,
            improvedIds: $improvedIds,
            regressedIds: $regressedIds,
        );
    }

    private static function intentOf(LlmObservationResult $result): ?string
    {
        return $result->normalizedCommand['intent'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $rate = fn (int $n): ?float => $this->total === 0 ? null : round($n / $this->total, 4);

        return [
            'locale' => $this->locale,
            'total' => $this->total,
            'few_shot' => [
                'count' => count($this->fewShotExampleIds),
                'example_ids' => $this->fewShotExampleIds,
            ],
            'baseline' => ['intent_match' => ['count' => $this->baselineMatchCount, 'rate' => $rate($this->baselineMatchCount)]],
            'few_shot_result' => ['intent_match' => ['count' => $this->fewShotMatchCount, 'rate' => $rate($this->fewShotMatchCount)]],
            'delta' => [
                'improved' => $this->improvedCount,
                'regressed' => $this->regressedCount,
                'unchanged_pass' => $this->unchangedPassCount,
                'unchanged_fail' => $this->unchangedFailCount,
                'improved_ids' => $this->improvedIds,
                'regressed_ids' => $this->regressedIds,
            ],
            'cases' => $this->rows,
        ];
    }
}
