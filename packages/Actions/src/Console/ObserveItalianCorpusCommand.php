<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Evaluation\Exceptions\InvalidInterpretationCorpusException;
use Fluxio\Actions\Diagnostics\Examples\ItalianCorpusObservationService;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command (Slice A4). Runs the held-out Italian interpretation corpus
 * (packages/Actions/evaluation/it-interpretation-corpus.json) through the existing LLM sandbox and
 * reports produced / contract-valid / intent-match / entity-agreement, optionally with few-shot
 * exemplars sourced from the IntentExamples template library, and optionally as an A1-vs-A2
 * per-case delta (`--compare`).
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
                            {--case= : Observe only the corpus case with this id}
                            {--limit= : Observe at most N corpus cases}
                            {--json : Emit JSON (the default output)}
                            {--compact : Emit compact single-line JSON instead of pretty}
                            {--progress : Print progress to stderr}';

    protected $description = 'Opt-in diagnostics: run the held-out Italian interpretation corpus through the LLM sandbox and report metrics as JSON. Add --few-shot for IntentExamples exemplars, or --compare for an A1-vs-A2 per-case delta. Real model, no proposals, NOT a CI gate. Non-production.';

    public function handle(ItalianCorpusObservationService $service): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:observe-italian-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $path = ($rawPath = $this->option('path')) !== null && trim((string) $rawPath) !== '' ? (string) $rawPath : null;

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
        $showProgress = (bool) $this->option('progress');
        $stderr = $this->output->getErrorStyle();
        $onProgress = $showProgress
            ? static fn (int $done, int $total): mixed => $stderr->writeln(sprintf('[%d/%d] …', $done, $total))
            : null;

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (! $this->option('compact')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if ($this->option('compare')) {
            $report = $service->compare($cases, $locale, $fewShotLimit, $onProgress);
            $this->line((string) json_encode($report->toArray(), $flags));

            return self::SUCCESS;
        }

        $fewShot = (bool) $this->option('few-shot');
        $exemplars = $fewShot ? $service->exemplarsFor($locale, $cases, $fewShotLimit) : [];

        if ($fewShot && $showProgress) {
            $stderr->writeln($exemplars === []
                ? "few-shot requested but no suitable exemplars for locale [{$locale}] — running without few-shot."
                : sprintf('few-shot: %d exemplar(s).', count($exemplars)));
        }

        $report = $service->observe($cases, $exemplars, $locale, $fewShot, $onProgress);
        $this->line((string) json_encode($report->toArray(), $flags));

        return self::SUCCESS;
    }
}
