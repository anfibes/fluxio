<?php

namespace Fluxio\Actions\Diagnostics\Examples;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Examples\DTO\ExemplarStrategyComparisonReport;
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
        private readonly ExemplarSelectionService $selection,
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
        $results = $this->run($cases, $this->constantResolver($exemplars), $onProgress, $model, $options);

        return $this->examples->report($locale, $results, $fewShot, $this->exampleIds($exemplars));
    }

    /**
     * SELECTED-strategy single run (A3.1): each case sees its own relevance-ranked exemplars, chosen
     * by ExemplarSelectionService from the case's known intent + asserted entity keys. Reported in
     * the same shape as a blind few-shot run; few_shot.example_ids is the DISTINCT set used across
     * all cases.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     */
    public function observeSelected(array $cases, string $locale = 'it', ?int $fewShotLimit = null, ?callable $onProgress = null, ?string $model = null, ?ObservationOptions $options = null): IntentExampleObservationReport
    {
        [$resolver, $selectedIds] = $this->selectedResolver($locale, $cases, $fewShotLimit);

        $results = $this->run($cases, $resolver, $onProgress, $model, $options);

        return $this->examples->report($locale, $results, true, $selectedIds());
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

        $baseline = $this->run($cases, $this->constantResolver([]), $tick, $model, $options);
        $fewShot = $this->run($cases, $this->constantResolver($exemplars), $tick, $model, $options);

        return ItalianCorpusComparisonReport::build($locale, $cases, $baseline, $fewShot, $this->exampleIds($exemplars));
    }

    /**
     * A3.1: run the corpus under all three strategies — no few-shot (baseline), BLIND few-shot
     * (one template per intent, registry order, shared across cases), and SELECTED few-shot
     * (per-case relevance-ranked exemplars) — then diff so the report shows whether SELECTED
     * improved, regressed, or matched BLIND, with per-intent breakdowns and explicit per-case
     * outcomes. The SAME model + options drive all three passes.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     */
    public function compareStrategies(array $cases, string $locale = 'it', ?int $fewShotLimit = null, ?callable $onProgress = null, ?string $model = null, ?ObservationOptions $options = null): ExemplarStrategyComparisonReport
    {
        $blindExemplars = $this->exemplarsFor($locale, $cases, $fewShotLimit);
        [$selectedResolver, $selectedIds] = $this->selectedResolver($locale, $cases, $fewShotLimit);

        $total = count($cases) * 3;
        $completed = 0;
        $tick = static function () use (&$completed, $total, $onProgress): void {
            $completed++;
            if ($onProgress !== null) {
                $onProgress($completed, $total);
            }
        };

        $baseline = $this->run($cases, $this->constantResolver([]), $tick, $model, $options);
        $blind = $this->run($cases, $this->constantResolver($blindExemplars), $tick, $model, $options);
        $selected = $this->run($cases, $selectedResolver, $tick, $model, $options);

        return ExemplarStrategyComparisonReport::build(
            $locale,
            $cases,
            $baseline,
            $blind,
            $selected,
            $this->exampleIds($blindExemplars),
            $selectedIds(),
        );
    }

    /**
     * Build the per-case SELECTED exemplar resolver and a closure that, after the run, returns the
     * DISTINCT set of source ids the selector actually used (in first-seen order). The corpus texts
     * are passed as exclude-texts so a selected exemplar can never duplicate a scored input.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @return array{0: callable(InterpretationCorpusCase): list<PromptExemplar>, 1: callable(): list<string>}
     */
    private function selectedResolver(string $locale, array $cases, ?int $fewShotLimit): array
    {
        $corpusTexts = array_map(static fn (InterpretationCorpusCase $c): string => $c->text, $cases);
        $usedIds = [];

        $resolver = function (InterpretationCorpusCase $case) use ($locale, $fewShotLimit, $corpusTexts, &$usedIds): array {
            $exemplars = $this->selectedExemplarsFor($locale, $case, $fewShotLimit, $corpusTexts);

            foreach ($exemplars as $exemplar) {
                if ($exemplar->sourceId !== null && ! in_array($exemplar->sourceId, $usedIds, true)) {
                    $usedIds[] = $exemplar->sourceId;
                }
            }

            return $exemplars;
        };

        // Regular closure with a by-reference capture so it reflects ids accumulated during the run
        // (an arrow fn would capture the empty array by value at creation time).
        $ids = function () use (&$usedIds): array {
            return $usedIds;
        };

        return [$resolver, $ids];
    }

    /**
     * SELECTED exemplars for one case: rank the locale's IntentExamples against the case's known
     * intent + asserted entity keys, render the ranked candidates into contract-shaped exemplars
     * (literals/unrenderables dropped), then cap at the few-shot limit. Driven only by structured
     * metadata — never by re-inferring intent from the case text.
     *
     * @param  list<string>  $excludeTexts
     * @return list<PromptExemplar>
     */
    public function selectedExemplarsFor(string $locale, InterpretationCorpusCase $case, ?int $fewShotLimit, array $excludeTexts = []): array
    {
        $limit = $fewShotLimit ?? IntentExampleObservationService::DEFAULT_FEW_SHOT_LIMIT;

        // Rank without a limit so unrenderable (literal) candidates do not consume a slot before
        // rendering; the cap is applied after rendering.
        $ranked = $this->selection->selectForLocale($locale, $case->expectedIntent, array_keys($case->expectedEntities));

        $rendered = $this->examples->renderExemplars($ranked, $excludeTexts);

        return array_slice($rendered, 0, max(0, $limit));
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
     * Run the corpus, resolving the exemplars to use PER CASE via $exemplarsFor. A constant
     * resolver (baseline = [] / blind = the shared set) reproduces the A4/A5 behavior; a per-case
     * resolver implements the SELECTED strategy (A3.1) where each case sees its own relevance-ranked
     * exemplars.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  callable(InterpretationCorpusCase): list<PromptExemplar>  $exemplarsFor
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     * @return list<LlmObservationResult>
     */
    private function run(array $cases, callable $exemplarsFor, ?callable $onProgress, ?string $model = null, ?ObservationOptions $options = null): array
    {
        $results = [];
        $total = count($cases);

        foreach ($cases as $index => $case) {
            $results[] = $this->observation->observe($case, $exemplarsFor($case), $model, $options);

            if ($onProgress !== null) {
                $onProgress($index + 1, $total);
            }
        }

        return $results;
    }

    /**
     * A constant exemplar resolver — every case sees the same set (baseline = [], blind = shared).
     *
     * @param  list<PromptExemplar>  $exemplars
     * @return callable(InterpretationCorpusCase): list<PromptExemplar>
     */
    private function constantResolver(array $exemplars): callable
    {
        return static fn (InterpretationCorpusCase $case): array => $exemplars;
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
