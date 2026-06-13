<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\Exceptions\InvalidInterpretationCorpusException;
use Fluxio\Actions\Diagnostics\Examples\ItalianCorpusObservationService;
use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;
use Fluxio\Actions\Diagnostics\Profiles\ObservationProfile;
use Fluxio\Actions\Diagnostics\Profiles\ObservationProfileResolver;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command (Slices A4/A7). Runs the held-out Italian interpretation
 * corpus (packages/Actions/evaluation/it-interpretation-corpus.json) through the existing LLM
 * sandbox and reports produced / contract-valid / intent-match / entity-agreement, optionally with
 * few-shot exemplars sourced from the IntentExamples template library, optionally as an A1-vs-A2
 * per-case delta (`--compare`), and optionally across multiple local models (`--model` / `--models`).
 *
 * Model override (A7) is a diagnostics-only, per-request override threaded into the LlmRequest; it
 * never mutates config and never affects the runtime/default path. With no `--model`/`--models` the
 * configured model is used and behavior is unchanged.
 *
 * Capability-class profiles (A7.2): each model auto-selects a diagnostics profile via
 * ObservationProfileResolver (model → capability class → transport strategy), so a reasoning model
 * (e.g. qwen3:1.7b) is driven think=false by default while an instruction-following model
 * (e.g. qwen3:0.6b) keeps the byte-identical forced-JSON default. CLI flags (--think / --no-think,
 * --free-text, --num-predict) still override the profile: CLI > profile > hardcoded. Thinking and
 * format are independent knobs — `--free-text --think` reproduces A7.1 E2 (thinking on + free-text +
 * extract); `--free-text` alone composes with the profile's thinking choice.
 *
 * The corpus is the held-out measurement set; the IntentExamples library is the few-shot source.
 * They never overlap (leakage guard). It builds no proposals, calls no ActionInterpreterService,
 * executes nothing, resolves no entities, and persists nothing. It is blocked in production.
 *
 * CI boundary: OPT-IN observation diagnostics (contacts the configured model), NOT a CI gate, and
 * excluded from `composer test:actions-corpora`. The deterministic provider remains the runtime
 * authority; IntentExamples and the corpus remain non-authoritative.
 */
class ObserveItalianCorpusCommand extends Command
{
    protected $signature = 'actions:observe-italian-corpus
                            {--path= : Path to a custom IT corpus JSON (defaults to the shipped held-out corpus)}
                            {--few-shot : Prepend IntentExamples template exemplars (held out from the corpus; default off)}
                            {--few-shot-limit= : Max few-shot exemplars (default 5, one per intent)}
                            {--compare : Run baseline vs few-shot over the corpus and emit a per-case delta (A5)}
                            {--model= : Diagnostics-only model override for this run (e.g. qwen3:1.7b). Default = configured model}
                            {--models= : Comma-separated models to compare side by side (A7); takes precedence over --model}
                            {--no-think : Force thinking OFF for this diagnostic run (overrides the profile)}
                            {--think : Force thinking ON for this diagnostic run (overrides the profile); cannot combine with --no-think}
                            {--free-text : Do not force JSON; extract a JSON object from free text, then validate with the same validator (composes with the profile/think; add --think to reproduce A7.1 E2)}
                            {--num-predict= : A7.1/E3 — raise the generation budget (Ollama num_predict) for this run}
                            {--case= : Observe only the corpus case with this id}
                            {--limit= : Observe at most N corpus cases}
                            {--json : Emit JSON (the default output)}
                            {--compact : Emit compact single-line JSON instead of pretty}
                            {--progress : Print progress to stderr}';

    protected $description = 'Opt-in diagnostics: run the held-out Italian interpretation corpus through the LLM sandbox and report metrics as JSON. Add --few-shot for IntentExamples exemplars, --compare for an A1-vs-A2 delta, or --model/--models to compare local models (A7). Real model, no proposals, NOT a CI gate. Non-production.';

    public function handle(ItalianCorpusObservationService $service, ObservationProfileResolver $resolver): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:observe-italian-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        if ($this->option('think') && $this->option('no-think')) {
            $this->error('--think and --no-think cannot be used together.');

            return self::FAILURE;
        }

        $path = ($rawPath = $this->option('path')) !== null && trim((string) $rawPath) !== '' ? (string) $rawPath : null;
        $corpusPath = $path ?? ItalianCorpusObservationService::defaultCorpusPath();

        try {
            $cases = $service->load($path);
        } catch (InvalidInterpretationCorpusException $e) {
            $this->error('Could not load the Italian corpus: '.$e->getMessage());

            return self::FAILURE;
        }

        $caseId = $this->option('case');
        $limit = ($rawLimit = $this->option('limit')) !== null ? max(0, (int) $rawLimit) : null;
        $cases = $service->filter($cases, $caseId, $limit);

        if ($cases === []) {
            $this->error($caseId !== null
                ? "No Italian corpus case with id [{$caseId}]."
                : 'No Italian corpus cases to evaluate.');

            return self::FAILURE;
        }

        $locale = 'it';
        $fewShotLimit = ($rawFewShotLimit = $this->option('few-shot-limit')) !== null ? max(0, (int) $rawFewShotLimit) : null;
        $fewShot = (bool) $this->option('few-shot');
        $compare = (bool) $this->option('compare');
        $showProgress = (bool) $this->option('progress');
        $stderr = $this->output->getErrorStyle();
        $onProgress = $showProgress
            ? static function (int $done, int $total) use ($stderr): void {
                $stderr->writeln(sprintf('[%d/%d] …', $done, $total));
            }
        : null;

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (! $this->option('compact')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $models = $this->parseModels();

        // ── Multi-model comparison (A7) ───────────────────────────────────────
        if ($models !== []) {
            $perModel = [];

            foreach ($models as $model) {
                if ($showProgress) {
                    $stderr->writeln("model: {$model}");
                }

                $check = $service->checkModel($cases[0], $model);
                if (! $check['available']) {
                    $perModel[] = ['model' => $model, 'available' => false, 'reason' => $check['reason']];

                    continue;
                }

                // A7.2: each model auto-selects its capability-class profile; CLI flags still override.
                $profile = $resolver->resolve($model);
                $options = $this->effectiveOptions($profile);

                $perModel[] = array_merge(
                    [
                        'model' => $model,
                        'available' => true,
                        'profile' => $profile->toArray(),
                        'effective' => $this->effectiveMeta($options),
                    ],
                    $this->bodyForModel($service, $cases, $locale, $fewShotLimit, $fewShot, $compare, $onProgress, $model, $options),
                );
            }

            $this->line((string) json_encode([
                'corpus' => $corpusPath,
                'few_shot' => $fewShot,
                'compare' => $compare,
                'models' => $perModel,
            ], $flags));

            return self::SUCCESS;
        }

        // ── Single model (configured default, or explicit --model override) ───
        $model = ($rawModel = $this->option('model')) !== null && trim((string) $rawModel) !== '' ? trim((string) $rawModel) : null;
        $effectiveModel = $model ?? (string) config('actions.llm.model');

        // A7.2: resolve the profile from the EFFECTIVE model (configured default when no override).
        // For the default model (instruction-following) the profile is think=null + format=json, so
        // the default run stays byte-identical to before; CLI flags still override.
        $profile = $resolver->resolve($effectiveModel);
        $options = $this->effectiveOptions($profile);

        // Only probe when a model is explicitly overridden — the default path stays untouched.
        if ($model !== null) {
            $check = $service->checkModel($cases[0], $model);
            if (! $check['available']) {
                $this->error("Model [{$model}] is not available: ".($check['reason'] ?? 'unknown transport failure.'));

                return self::FAILURE;
            }
        }

        if ($fewShot && $showProgress && ! $compare) {
            $exemplarCount = count($service->exemplarsFor($locale, $cases, $fewShotLimit));
            $stderr->writeln($exemplarCount === 0
                ? "few-shot requested but no suitable exemplars for locale [{$locale}] — running without few-shot."
                : sprintf('few-shot: %d exemplar(s).', $exemplarCount));
        }

        $body = $this->bodyForModel($service, $cases, $locale, $fewShotLimit, $fewShot, $compare, $onProgress, $model, $options);

        $this->line((string) json_encode(
            array_merge([
                'model' => $effectiveModel,
                'corpus' => $corpusPath,
                'profile' => $profile->toArray(),
                'effective' => $this->effectiveMeta($options),
            ], $body),
            $flags,
        ));

        return self::SUCCESS;
    }

    /**
     * Build the effective transport knobs: start from the resolved profile, then apply any explicit
     * CLI overrides. Precedence: CLI flags > profile defaults > hardcoded defaults.
     *
     * Thinking: profile default, unless forced by --no-think (false) or --think (true). The two
     * cannot be combined (rejected in handle()). --free-text only controls forceJson; it never
     * changes thinking.
     */
    private function effectiveOptions(ObservationProfile $profile): ObservationOptions
    {
        $think = $profile->think;
        if ($this->option('no-think')) {
            $think = false;
        }
        if ($this->option('think')) {
            $think = true;
        }

        return new ObservationOptions(
            think: $think,
            forceJson: $this->option('free-text') ? false : $profile->forceJson,
            // Budget is a pure CLI concern; profiles do not own it.
            maxTokens: ($rawNumPredict = $this->option('num-predict')) !== null ? max(1, (int) $rawNumPredict) : null,
        );
    }

    /**
     * Effective-options metadata for the report, so a reader can distinguish profile defaults from
     * explicit overrides (profile / cli:--think / cli:--no-think / cli:--free-text).
     *
     * @return array<string, mixed>
     */
    private function effectiveMeta(ObservationOptions $options): array
    {
        $thinkSource = match (true) {
            (bool) $this->option('think') => 'cli:--think',
            (bool) $this->option('no-think') => 'cli:--no-think',
            default => 'profile',
        };

        return [
            'think' => $options->think,
            'think_source' => $thinkSource,
            'force_json' => $options->forceJson,
            'force_json_source' => $this->option('free-text') ? 'cli:--free-text' : 'profile',
            'max_tokens' => $options->maxTokens,
        ];
    }

    /**
     * Run one model's observation (compare or single-mode) and return the report body. The same
     * effective $options drive both paths — so --compare honors the resolved profile / CLI overrides.
     *
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  (callable(int $completed, int $total): void)|null  $onProgress
     * @return array<string, mixed>
     */
    private function bodyForModel(
        ItalianCorpusObservationService $service,
        array $cases,
        string $locale,
        ?int $fewShotLimit,
        bool $fewShot,
        bool $compare,
        ?callable $onProgress,
        ?string $model,
        ObservationOptions $options,
    ): array {
        if ($compare) {
            return $service->compare($cases, $locale, $fewShotLimit, $onProgress, $model, $options)->toArray();
        }

        $exemplars = $fewShot ? $service->exemplarsFor($locale, $cases, $fewShotLimit) : [];

        return $service->observe($cases, $exemplars, $locale, $fewShot, $onProgress, $model, $options)->toArray();
    }

    /**
     * Parse --models into a de-duplicated, ordered list. Empty when --models is absent/blank.
     *
     * @return list<string>
     */
    private function parseModels(): array
    {
        $raw = $this->option('models');
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $models = array_filter(array_map('trim', explode(',', (string) $raw)), static fn (string $m): bool => $m !== '');

        return array_values(array_unique($models));
    }
}
