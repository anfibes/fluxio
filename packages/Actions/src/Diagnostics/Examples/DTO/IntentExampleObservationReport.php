<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;

/**
 * Diagnostics-only report for one Intent-Examples → LLM-sandbox observation run.
 *
 * Bundles the per-example observation results (captured via the SAME LlmObservationService
 * pipeline the EN observation command uses) with the aggregate metrics, scoped to one
 * locale. It builds no proposals, persists nothing, and never reaches the runtime — it is a
 * baseline snapshot of how the existing Ollama sandbox interprets literal Intent Examples
 * before any prompt change or few-shot injection.
 */
final class IntentExampleObservationReport
{
    /**
     * @param  list<LlmObservationResult>  $results
     * @param  list<string>  $fewShotExampleIds  Source IntentExample ids used as few-shot exemplars.
     */
    public function __construct(
        public readonly string $locale,
        public readonly array $results,
        public readonly IntentExampleObservationMetrics $metrics,
        public readonly bool $fewShotEnabled = false,
        public readonly array $fewShotExampleIds = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'evaluated' => count($this->results),
            'few_shot' => [
                'enabled' => $this->fewShotEnabled,
                'count' => count($this->fewShotExampleIds),
                'example_ids' => $this->fewShotExampleIds,
            ],
            'metrics' => $this->metrics->toArray(),
            'cases' => array_map(fn (LlmObservationResult $r): array => $this->caseToArray($r), $this->results),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caseToArray(LlmObservationResult $result): array
    {
        $case = $result->toArray();

        // Honest entity agreement: literal Intent Examples declare no expected entities, so
        // entity match is not measurable here. Report null (n/a) rather than the vacuous
        // "true" that an empty-expectations comparison would otherwise yield.
        $comparable = $result->expectedEntities !== [];
        $case['match']['entities'] = $comparable ? $result->entityMatch : null;
        $case['match']['entities_comparable'] = $comparable;

        return $case;
    }
}
