<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationDriftAnalyzer;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationEvaluationService;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Development-only diagnostic command. Evaluates the interpretation corpus by
 * running every case through the Phase 5A comparison service and reporting how
 * the deterministic and Ollama sandbox providers match the expected results.
 *
 * It builds no proposals, calls no ActionInterpreterService, persists nothing,
 * and does not change the configured runtime interpretation provider. It is
 * blocked in production. A captured Ollama failure is reported per case, not a
 * crash; only an invalid corpus file aborts the run.
 *
 * CI boundary: this is OPT-IN provider diagnostics. It compares the deterministic
 * baseline against the Ollama sandbox and may contact a model when that provider is
 * selected, so it is intentionally NOT part of the mandatory `composer test:actions-corpora`
 * gate. Baseline-relative outcomes (--metrics) and --fail-on-drift are diagnostics
 * signals only: the deterministic provider stays the oracle and nothing here becomes
 * runtime authority or auto-selects a provider.
 */
class EvaluateInterpretationCorpusCommand extends Command
{
    protected $signature = 'actions:evaluate-interpretation-corpus
                            {--path= : Path to a custom corpus JSON file (defaults to the shipped corpus)}
                            {--json : Emit the full evaluation as pretty JSON instead of the summary table}
                            {--metrics : Print enriched drift metrics alongside the table summary}
                            {--fail-on-drift : Exit non-zero when provider intent agreement is below the threshold (opt-in, for CI experiments)}
                            {--agreement-threshold=0.8 : Minimum provider intent agreement rate; only applies with --fail-on-drift}
                            {--progress : Show a per-case progress bar on stderr (on by default unless --json; never written to stdout)}';

    protected $description = 'Opt-in provider diagnostics: compare the deterministic baseline vs. the Ollama sandbox over the interpretation corpus, with optional drift metrics. NOT part of composer test:actions-corpora; may contact a model. Non-production.';

    public function handle(
        InterpretationCorpusLoader $loader,
        InterpretationEvaluationService $service,
        InterpretationDriftAnalyzer $analyzer,
        InterpretationGrammar $grammar,
    ): int {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:evaluate-interpretation-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: InterpretationCorpusLoader::defaultCorpusPath());
        $cases = $loader->load($path);

        // Progress is a stderr-only UX aid: it must never reach stdout or it would
        // corrupt --json piped to jq. The bar is created only when a real stderr
        // stream exists (skipped under buffered output, e.g. tests).
        $progressBar = $this->makeProgressBar(count($cases));

        $summary = $service->evaluate(
            $cases,
            $progressBar === null
                ? null
                : static fn (int $completed): mixed => $progressBar->setProgress($completed),
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $progressBar->clear();
        }

        $metrics = $analyzer->analyze($summary);

        if ($this->option('json')) {
            // Backward-friendly: existing summary fields stay; metrics and grammar are added.
            // `grammar` records the provider-facing surface (InterpretationGrammar::export())
            // the comparison ran against — the same surface the prompt/validator use.
            $payload = $summary->toArray();
            $payload['metrics'] = $metrics->toArray();
            $payload['grammar'] = $grammar->export();
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderSummary($summary->toArray());
            $this->renderGrammar($grammar->export());

            if ($this->option('metrics')) {
                $this->renderMetrics($metrics->toArray());
            }
        }

        // Drift gating is strictly opt-in; provider disagreement is otherwise
        // diagnostic data, never a runtime/evaluation failure.
        if ($this->option('fail-on-drift')) {
            $threshold = (float) $this->option('agreement-threshold');
            $agreement = $metrics->providerIntentAgreementRate;

            if ($agreement < $threshold) {
                $this->newLine();
                $this->error(sprintf(
                    'Provider intent agreement %.4f is below the threshold %.4f.',
                    $agreement,
                    $threshold,
                ));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Build a progress bar on STDERR, or null when progress should not render.
     *
     * Progress shows by default unless --json (opt back in with --progress). It is
     * always written to the error stream so it can never corrupt --json on stdout.
     * When there is no real console error stream (buffered output under tests), it
     * returns null rather than risk leaking into captured stdout.
     */
    private function makeProgressBar(int $max): ?ProgressBar
    {
        $wantProgress = $this->option('progress') || ! $this->option('json');

        if (! $wantProgress || $max <= 0) {
            return null;
        }

        $output = $this->output->getOutput();

        if (! $output instanceof ConsoleOutputInterface) {
            return null;
        }

        $bar = new ProgressBar($output->getErrorOutput(), $max);
        $bar->start();

        return $bar;
    }

    /**
     * Concise grammar-awareness block: which provider-facing contract/version and
     * intent/key surface the corpus was evaluated against. Derived from
     * InterpretationGrammar::export(); observability only.
     *
     * @param  array<string, mixed>  $grammar
     */
    private function renderGrammar(array $grammar): void
    {
        $this->newLine();
        $this->line('<info>Grammar</info> '.$grammar['contract'].' v'.$grammar['version']);
        $this->line('  allowed_reference_keys: '.implode(', ', $grammar['allowed_reference_keys']));
        $this->line('  intents: '.implode(', ', $grammar['intents']));
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function renderMetrics(array $metrics): void
    {
        $this->newLine();
        $this->line('<info>Metrics</info>');
        foreach ([
            'deterministic_success_rate' => 'deterministic success rate',
            'ollama_success_rate' => 'ollama success rate',
            'deterministic_intent_match_rate' => 'deterministic intent match rate',
            'ollama_intent_match_rate' => 'ollama intent match rate',
            'deterministic_entity_match_rate' => 'deterministic entity match rate',
            'ollama_entity_match_rate' => 'ollama entity match rate',
            'provider_intent_agreement_rate' => 'provider intent agreement rate',
            'provider_entity_agreement_rate' => 'provider entity agreement rate',
            'provider_failure_rate' => 'provider failure rate',
            'average_confidence_delta' => 'average confidence delta',
        ] as $key => $label) {
            $this->line(sprintf('  %-34s %s', $label.':', $this->scalar($metrics[$key])));
        }

        $avg = $metrics['average_confidence_by_provider'];
        $this->line(sprintf('  %-34s det=%s ollama=%s', 'average confidence by provider:', $this->scalar($avg['deterministic']), $this->scalar($avg['ollama'])));

        $this->newLine();
        $this->line('<info>Divergent entity keys</info>');
        if ($metrics['divergent_entity_keys'] === []) {
            $this->line('  (none)');
        } else {
            foreach ($metrics['divergent_entity_keys'] as $key => $count) {
                $this->line(sprintf('  %-20s %d', $key.':', $count));
            }
        }

        $this->newLine();
        $this->line('<info>Weak expected intents</info>');
        if ($metrics['weak_expected_intents'] === []) {
            $this->line('  (none)');
        } else {
            foreach ($metrics['weak_expected_intents'] as $intent => $stats) {
                $this->line(sprintf(
                    '  %-30s det=%s ollama=%s (%d cases)',
                    $intent.':',
                    $this->scalar($stats['deterministic_intent_match_rate']),
                    $this->scalar($stats['ollama_intent_match_rate']),
                    $stats['cases'],
                ));
            }
        }

        $this->newLine();
        $this->line('<info>Baseline-relative outcomes</info> <comment>(provider vs deterministic oracle)</comment>');
        $outcomes = $metrics['baseline_relative_outcomes'];
        $this->line(sprintf(
            '  improved=%d  regressed=%d  parity_match=%d  parity_miss=%d',
            $outcomes['improved'],
            $outcomes['regressed'],
            $outcomes['parity_match'],
            $outcomes['parity_miss'],
        ));
        if ($metrics['regression_cases'] === []) {
            $this->line('  regression cases: (none)');
        } else {
            $this->line('  regression cases:');
            foreach ($metrics['regression_cases'] as $case) {
                $this->line(sprintf('    - %s (expected %s)', $case['id'], $case['expected_intent']));
            }
        }

        $this->renderTiming($metrics['timing']);
    }

    /**
     * Concise timing summary (diagnostics observability only).
     *
     * @param  array<string, mixed>  $timing
     */
    private function renderTiming(array $timing): void
    {
        $this->newLine();
        $this->line('<info>Timing</info> <comment>(diagnostics only)</comment>');
        foreach ([
            'total_duration_ms' => 'total duration ms',
            'average_deterministic_duration_ms' => 'avg deterministic ms',
            'average_ollama_duration_ms' => 'avg ollama ms',
            'max_ollama_duration_ms' => 'max ollama ms',
        ] as $key => $label) {
            $this->line(sprintf('  %-26s %s', $label.':', $this->scalar($timing[$key])));
        }

        if ($timing['slowest_ollama_cases'] === []) {
            $this->line('  slowest ollama cases: (none)');
        } else {
            $this->line('  slowest ollama cases:');
            foreach ($timing['slowest_ollama_cases'] as $case) {
                $this->line(sprintf('    - %s (%s ms)', $case['id'], $this->scalar($case['duration_ms'])));
            }
        }
    }

    private function scalar(mixed $value): string
    {
        return $value === null ? 'n/a' : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderSummary(array $summary): void
    {
        $rows = [];
        foreach ($summary['cases'] as $case) {
            $rows[] = [
                $case['id'],
                $case['expected_intent'],
                $this->flag($case['deterministic_intent_match']),
                $this->flag($case['ollama_intent_match']),
                $this->flag($case['deterministic_entity_match']),
                $this->flag($case['ollama_entity_match']),
                $case['comparison']['ollama']['error_class'] ?? '—',
            ];
        }

        $this->table(
            ['id', 'expected', 'det intent', 'llm intent', 'det entity', 'llm entity', 'ollama error'],
            $rows,
        );

        $this->newLine();
        $this->line('<info>Totals</info>');
        foreach ([
            'total' => 'total cases',
            'deterministic_success_count' => 'deterministic success',
            'ollama_success_count' => 'ollama success',
            'deterministic_intent_match_count' => 'deterministic intent match',
            'ollama_intent_match_count' => 'ollama intent match',
            'deterministic_entity_match_count' => 'deterministic entity match',
            'ollama_entity_match_count' => 'ollama entity match',
            'intent_mismatch_count_between_providers' => 'intent mismatch (providers)',
            'provider_failure_count' => 'provider failures',
        ] as $key => $label) {
            $this->line(sprintf('  %-30s %s', $label.':', $summary[$key]));
        }
    }

    private function flag(bool $value): string
    {
        return $value ? '✓' : '✗';
    }
}
