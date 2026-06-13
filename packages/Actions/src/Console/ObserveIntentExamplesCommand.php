<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Examples\IntentExampleObservationService;
use Illuminate\Console\Command;

/**
 * Development-only Intent-Examples observation command (Slices A1/A2). Runs the configured LLM
 * sandbox over the LITERAL Intent Examples of a locale (default `it`) and dumps, per example,
 * how the real model responded — raw text, parsed JSON, structured-output validation outcome,
 * the candidate NormalizedCommand, the adapter-equivalent sandbox/runtime-validation outcome,
 * and the intent match — plus aggregate metrics.
 *
 * Default (A1) measures the no-few-shot baseline. With `--few-shot` (A2) the locale's TEMPLATE
 * examples are rendered into contract-shaped exemplars and prepended to the prompt, while the
 * evaluated inputs stay the LITERAL examples — so the model is never scored on a string shown
 * verbatim. Few-shot is opt-in; without it the prompt is byte-identical to A1.
 *
 * It is a diagnostics consumer of the Intent Examples library; it builds no proposals, calls no
 * ActionInterpreterService, executes nothing, resolves no entities, and persists nothing. It is
 * blocked in production.
 *
 * CI boundary: this is OPT-IN observation diagnostics (contacts the configured model), NOT a CI
 * gate, and is excluded from `composer test:actions-corpora`. The deterministic provider remains
 * the runtime authority; IntentExamples remain non-authoritative.
 */
class ObserveIntentExamplesCommand extends Command
{
    protected $signature = 'actions:observe-intent-examples
                            {--locale=it : Observe literal examples for this locale}
                            {--case= : Observe only the literal example with this id}
                            {--limit= : Observe at most N examples}
                            {--few-shot : Prepend locale-aware template exemplars to the prompt (A2; default off)}
                            {--few-shot-limit= : Max few-shot exemplars (default 5, one per intent)}
                            {--json : Emit JSON (the default output)}
                            {--compact : Emit compact single-line JSON instead of pretty}
                            {--progress : Print per-example progress to stderr}';

    protected $description = 'Opt-in Intent-Examples observation: run the configured LLM sandbox over a locale\'s literal Intent Examples and dump per-example behavior + metrics as JSON. Add --few-shot to prepend template exemplars (A2). Real model, no proposals, NOT a CI gate. Non-production.';

    public function handle(IntentExampleObservationService $service): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:observe-intent-examples is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $locale = (string) $this->option('locale');
        $caseId = $this->option('case');
        $limit = ($rawLimit = $this->option('limit')) !== null ? max(0, (int) $rawLimit) : null;

        $examples = $service->select($locale, $caseId, $limit);

        if ($examples === []) {
            $this->error($caseId !== null
                ? "No literal intent example with id [{$caseId}] for locale [{$locale}]."
                : "No literal intent examples found for locale [{$locale}].");

            return self::FAILURE;
        }

        $showProgress = (bool) $this->option('progress');
        $stderr = $this->output->getErrorStyle();
        $total = count($examples);

        $fewShot = (bool) $this->option('few-shot');
        $fewShotLimit = ($rawFewShotLimit = $this->option('few-shot-limit')) !== null ? max(0, (int) $rawFewShotLimit) : null;

        $exemplars = [];
        if ($fewShot) {
            $exemplars = $service->fewShotExemplars(
                $locale,
                $fewShotLimit,
                array_map(static fn ($e): string => (string) $e->text, $examples),
            );

            if ($showProgress) {
                $stderr->writeln($exemplars === []
                    ? "few-shot requested but no suitable exemplars for locale [{$locale}] — running without few-shot."
                    : sprintf('few-shot: %d exemplar(s) — %s', count($exemplars), implode(', ', array_map(static fn ($x): string => (string) $x->sourceId, $exemplars))));
            }
        }

        $results = [];
        foreach ($examples as $index => $example) {
            if ($showProgress) {
                $stderr->writeln(sprintf('[%d/%d] %s (%s) …', $index + 1, $total, $example->id, $example->intent));
            }

            $results[] = $service->observeExample($example, $exemplars);
        }

        $report = $service->report(
            $locale,
            $results,
            $fewShot,
            array_values(array_filter(array_map(static fn ($x): ?string => $x->sourceId, $exemplars))),
        );

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (! $this->option('compact')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($report->toArray(), $flags));

        return self::SUCCESS;
    }
}
