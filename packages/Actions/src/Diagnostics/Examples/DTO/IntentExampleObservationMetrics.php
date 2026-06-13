<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;

/**
 * Aggregate, diagnostics-only metrics for one Intent-Examples observation run.
 *
 * Every count is derived from the per-example LlmObservationResult set — nothing here
 * is authoritative, persisted, or fed back into the runtime. The three quality signals
 * (produced / contract-valid / intent-match) are reported separately and must not be
 * collapsed: a candidate can be produced and contract-valid while still mismatching the
 * expected intent. Entity agreement is only meaningful for examples that declare expected
 * entities; literal Intent Examples currently declare none, so `entityComparableCount`
 * is 0 and the rate is null (n/a) rather than vacuously 1.0.
 */
final class IntentExampleObservationMetrics
{
    public function __construct(
        public readonly int $total,
        public readonly int $producedCount,
        public readonly int $contractValidCount,
        public readonly int $intentMatchCount,
        public readonly int $entityComparableCount,
        public readonly int $entityAgreementCount,
        public readonly int $providerFailureCount,
        public readonly int $validationFailureCount,
    ) {}

    /**
     * @param  list<LlmObservationResult>  $results
     */
    public static function fromResults(array $results): self
    {
        $produced = 0;
        $contractValid = 0;
        $intentMatch = 0;
        $entityComparable = 0;
        $entityAgreement = 0;
        $providerFailure = 0;
        $validationFailure = 0;

        foreach ($results as $result) {
            if ($result->providerSucceeded === false) {
                $providerFailure++;
            }

            if ($result->providerSucceeded === true && $result->structuredOutputValid === false) {
                $validationFailure++;
            }

            if ($result->structuredOutputValid === true) {
                $contractValid++;
            }

            if ($result->normalizedCommand !== null) {
                $produced++;
            }

            if ($result->intentMatch === true) {
                $intentMatch++;
            }

            // Entity agreement is only counted where the example declared expected entities.
            if ($result->expectedEntities !== []) {
                $entityComparable++;

                if ($result->entityMatch === true) {
                    $entityAgreement++;
                }
            }
        }

        return new self(
            total: count($results),
            producedCount: $produced,
            contractValidCount: $contractValid,
            intentMatchCount: $intentMatch,
            entityComparableCount: $entityComparable,
            entityAgreementCount: $entityAgreement,
            providerFailureCount: $providerFailure,
            validationFailureCount: $validationFailure,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'produced' => ['count' => $this->producedCount, 'rate' => $this->rate($this->producedCount, $this->total)],
            'contract_valid' => ['count' => $this->contractValidCount, 'rate' => $this->rate($this->contractValidCount, $this->total)],
            'intent_match' => ['count' => $this->intentMatchCount, 'rate' => $this->rate($this->intentMatchCount, $this->total)],
            'entity_agreement' => [
                'count' => $this->entityAgreementCount,
                'comparable' => $this->entityComparableCount,
                'rate' => $this->rate($this->entityAgreementCount, $this->entityComparableCount),
            ],
            'provider_failures' => $this->providerFailureCount,
            'validation_failures' => $this->validationFailureCount,
        ];
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : round($numerator / $denominator, 4);
    }
}
