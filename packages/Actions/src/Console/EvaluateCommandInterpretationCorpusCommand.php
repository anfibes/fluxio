<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\CommandInterpretation\CommandInterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\CommandInterpretation\CommandInterpretationEvaluationService;
use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationEvaluationSummary;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command. Evaluates the command-interpretation corpus
 * by replaying the real deterministic interpretation pipeline for each case
 * (ActionInterpreterService::interpret → ActionProposalData) and reporting whether
 * the observed proposal-level interpretation matches the case expectations.
 *
 * Distinct from `actions:evaluate-interpretation-corpus`, which measures the
 * provider's NormalizedCommand (intent/entities) and compares deterministic vs.
 * the Ollama sandbox. This command measures PROPOSAL-LEVEL fidelity (resolved lead,
 * status, ambiguity) for the deterministic baseline only.
 *
 * It changes no runtime behavior, calls no LLM, and persists nothing (the pipeline
 * is DB-free). It is blocked in production. Only an invalid corpus aborts the run;
 * case mismatches are reported (and optionally gated with --fail-on-mismatch).
 *
 * CI boundary: with --fail-on-mismatch this is one of the two deterministic,
 * network-free corpora that back the mandatory `composer test:actions-corpora`
 * gate. The provider-level interpretation diagnostics (deterministic vs. Ollama)
 * are a separate, opt-in tool and are NOT part of that gate.
 */
class EvaluateCommandInterpretationCorpusCommand extends Command
{
    protected $signature = 'actions:evaluate-command-interpretation-corpus
                            {--path= : Path to a custom corpus JSON file (defaults to the shipped corpus)}
                            {--json : Emit the full evaluation as pretty JSON instead of the summary table}
                            {--fail-on-mismatch : Exit non-zero when any corpus case does not match its expectations (opt-in, for CI)}';

    protected $description = 'Deterministic CI gate: evaluate proposal-level command interpretation against the baseline corpus. Network-free; with --fail-on-mismatch it backs composer test:actions-corpora. Non-production.';

    public function handle(
        CommandInterpretationCorpusLoader $loader,
        CommandInterpretationEvaluationService $service,
    ): int {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:evaluate-command-interpretation-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: CommandInterpretationCorpusLoader::defaultCorpusPath());
        $cases = $loader->load($path);

        $summary = $service->evaluate($cases);

        if ($this->option('json')) {
            $this->line(json_encode($summary->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderSummary($summary);
        }

        if ($this->option('fail-on-mismatch') && ! $summary->allPassed()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function renderSummary(CommandInterpretationEvaluationSummary $summary): void
    {
        $rows = [];
        foreach ($summary->cases as $case) {
            $rows[] = [
                $case->id,
                $case->passed ? '✓' : '✗',
                $case->actual['intent'],
                $case->actual['status'],
                $case->actual['lead'] ?? '—',
                $case->failures === [] ? '' : implode('; ', $case->failures),
            ];
        }

        $this->table(
            ['id', 'ok', 'intent', 'status', 'lead', 'failures'],
            $rows,
        );

        $this->newLine();
        $this->line('<info>Totals</info>');
        $this->line(sprintf('  %-16s %d', 'total cases:', $summary->total));
        $this->line(sprintf('  %-16s %d', 'passed:', $summary->passedCount));
        $this->line(sprintf('  %-16s %d', 'failed:', $summary->failedCount));

        $this->newLine();
        $this->line('<info>Accuracy by dimension</info>');
        foreach ($summary->metrics as $dimension => $metric) {
            $this->line(sprintf(
                '  %-10s %5.1f%%  (%d/%d)',
                $dimension.':',
                $summary->accuracy($dimension) * 100,
                $metric['matched'],
                $metric['checked'],
            ));
        }
    }
}
