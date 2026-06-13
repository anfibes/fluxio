<?php

namespace Fluxio\Actions\Diagnostics\Examples;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Examples\DTO\IntentExampleObservationReport;
use Fluxio\Actions\Diagnostics\Examples\DTO\ItalianCorpusComparisonReport;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;
use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;
use Fluxio\Actions\Diagnostics\Observation\LlmObservationService;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
use Fluxio\Actions\Llm\Prompting\PromptExemplar;

/**
 * Diagnostics-only evaluation of the held-out Italian interpretation corpus (Slice A4).
 *
 * The corpus (packages/Actions/evaluation/it-interpretation-corpus.json) is the measurement set;
 * it is authored separately from the IntentExamples library and never overlaps it (leakage guard).
 * Each case is replayed through the SAME LlmObservationService pipeline A1/A2 use — so produced /
 * contract-valid / intent-match / entity-agreement are all measured identically — and, when
 * requested, the few-shot exemplars come from the IntentExamples TEMPLATE library (held out from
 * the corpus, contract-validated). The A5 compare mode runs the corpus twice (baseline vs
 * few-shot) and diffs per case so regressions are explicit.
 *
 * It is a second diagnostics ENTRY POINT into the existing observation pipeline, not a second
 * pipeline: it builds no proposals, resolves no entities, executes nothing, persists nothing, and
 * adds no relaxed/parallel validator. The deterministic provider stays the runtime authority;
 * IntentExamples and this corpus stay non-authoritative.
 */
class ItalianCorpusObservationService
{
    public function __construct(
        private readonly InterpretationCorpusLoader $loader,
        private readonly LlmObservationService $observation,
        private readonly IntentExampleObservationService $examples,
    ) {}

    /**
     * Default shipped held-out Italian corpus:
     * packages/Actions/evaluation/it-interpretation-corpus.json
     * (src/Diagnostics/Examples → src/Diagnostics → src → Actions → evaluation)
     */
    public static function defaultCorpusPath(): string
    {
        return __DIR__.'/../../../evaluation/it-interpretation-corpus.json';
    }

    /**
     * @return list<InterpretationCorpusCase>
     */
    public function load(?string $path = null): array
    {
        return $this->loader->load($path ?? self::defaultCorpusPath());
    }

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     * @return list<InterpretationCorpusCase>
     */
    public function filter(array $cases, ?string $caseId = null, ?int $limit = null): array
    {
        if ($caseId !== null) {
            $cases = array_values(array_filter($cases, static fn (InterpretationCorpusCase $c): bool => $c->id === $caseId));
        }

        if ($limit !== null) {
            $cases = array_slice($cases, 0, max(0, $limit));
        }

        return $cases;
    }

    /**
     * Few-shot exemplars for the corpus, drawn from the IntentExamples TEMPLATE library. The corpus
     * texts are passed as exclude-texts so an exemplar can never duplicate a scored input (the
     * separation is structural — corpus in evaluation/, exemplars in resources/intent-examples/ —
     * this is the explicit belt-and-braces guard).
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @return list<PromptExemplar>
     */
    public function exemplarsFor(string $locale, array $cases, ?int $fewShotLimit = null): array
    {
        return $this->examples->fewShotExemplars(
            $locale,
            $fewShotLimit,
            array_map(static fn (InterpretationCorpusCase $c): string => $c->text, $cases),
        );
    }

    /**
     * Observe the corpus once, with the given exemplars (empty = baseline). Reuses the shared
     * IntentExampleObservationReport so the metric shape matches the A1/A2 command exactly.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<PromptExemplar>  $exemplars
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     * @param  string|null  $model  Diagnostics-only per-request model override (A7). Null = configured default.
     * @param  ObservationOptions|null  $options  Diagnostics-only transport knobs (A7.1). Null = default.
     */
    public function observe(array $cases, array $exemplars = [], string $locale = 'it', bool $fewShot = false, ?callable $onProgress = null, ?string $model = null, ?ObservationOptions $options = null): IntentExampleObservationReport
    {
        $results = $this->run($cases, $exemplars, $onProgress, $model, $options);

        return $this->examples->report($locale, $results, $fewShot, $this->exampleIds($exemplars));
    }

    /**
     * A5: run the corpus with no few-shot and with few-shot, then diff per case.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     * @param  string|null  $model  Diagnostics-only per-request model override (A7). Null = configured default.
     * @param  ObservationOptions|null  $options  Diagnostics-only transport knobs (A7.1/A7.2). Null = default.
     *                                            The SAME options drive both the baseline and few-shot passes, so a
     *                                            resolved profile (e.g. reasoning → think=false) applies to --compare too.
     */
    public function compare(array $cases, string $locale = 'it', ?int $fewShotLimit = null, ?callable $onProgress = null, ?string $model = null, ?ObservationOptions $options = null): ItalianCorpusComparisonReport
    {
        $exemplars = $this->exemplarsFor($locale, $cases, $fewShotLimit);

        $total = count($cases) * 2;
        $completed = 0;
        $tick = static function () use (&$completed, $total, $onProgress): void {
            $completed++;
            if ($onProgress !== null) {
                $onProgress($completed, $total);
            }
        };

        $baseline = $this->run($cases, [], $tick, $model, $options);
        $fewShot = $this->run($cases, $exemplars, $tick, $model, $options);

        return ItalianCorpusComparisonReport::build($locale, $cases, $baseline, $fewShot, $this->exampleIds($exemplars));
    }

    /**
     * Diagnostics-only model availability probe (A7). Runs a single case through the given model
     * and reports whether the model is reachable. "Unavailable" is specifically a transport-level
     * failure (e.g. Ollama HTTP 404 for an unknown model) — a model that responds but produces
     * contract-invalid output is still "available" (it ran). One model call; never throws.
     *
     * @return array{available: bool, reason: ?string}
     */
    public function checkModel(InterpretationCorpusCase $probeCase, string $model): array
    {
        $result = $this->observation->observe($probeCase, [], $model);

        $unavailable = $result->providerSucceeded === false
            && $result->failureClass === LlmTransportException::class;

        return [
            'available' => ! $unavailable,
            'reason' => $unavailable ? $result->failureMessage : null,
        ];
    }

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  list<PromptExemplar>  $exemplars
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     * @return list<LlmObservationResult>
     */
    private function run(array $cases, array $exemplars, ?callable $onProgress, ?string $model = null, ?ObservationOptions $options = null): array
    {
        $results = [];
        $total = count($cases);

        foreach ($cases as $index => $case) {
            $results[] = $this->observation->observe($case, $exemplars, $model, $options);

            if ($onProgress !== null) {
                $onProgress($index + 1, $total);
            }
        }

        return $results;
    }

    /**
     * @param  list<PromptExemplar>  $exemplars
     * @return list<string>
     */
    private function exampleIds(array $exemplars): array
    {
        return array_values(array_filter(array_map(static fn (PromptExemplar $x): ?string => $x->sourceId, $exemplars)));
    }
}
