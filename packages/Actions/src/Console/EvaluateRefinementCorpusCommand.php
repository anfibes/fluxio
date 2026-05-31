<?php

namespace Fluxio\Actions\Console;

use App\Models\User;
use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementEvaluationSummary;
use Fluxio\Actions\Diagnostics\Refinement\RefinementCorpusLoader;
use Fluxio\Actions\Diagnostics\Refinement\RefinementEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Development-only diagnostic command. Evaluates the refinement corpus by
 * replaying the real proposal pipeline for each case (interpret → persist →
 * setup refinements → measured refinement) and reporting whether the observed
 * structured refinement behavior matches the case expectations.
 *
 * It changes no runtime behavior, calls no LLM, and persists nothing: the whole
 * run executes inside a transaction that is always rolled back, so the throwaway
 * proposals and evaluation user never reach the database. It is blocked in
 * production. Only an invalid corpus file aborts the run; case mismatches are
 * reported (and optionally gated with --fail-on-mismatch).
 */
class EvaluateRefinementCorpusCommand extends Command
{
    protected $signature = 'actions:evaluate-refinement-corpus
                            {--path= : Path to a custom corpus JSON file (defaults to the shipped corpus)}
                            {--json : Emit the full evaluation as pretty JSON instead of the summary table}
                            {--fail-on-mismatch : Exit non-zero when any corpus case does not match its expectations (opt-in, for CI)}';

    protected $description = 'Diagnostics: evaluate the refinement corpus against the rule-based refinement interpreter (non-production).';

    public function handle(
        RefinementCorpusLoader $loader,
        RefinementEvaluationService $service,
    ): int {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:evaluate-refinement-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: RefinementCorpusLoader::defaultCorpusPath());
        $cases = $loader->load($path);

        // Replay the pipeline inside a transaction that is always rolled back:
        // the evaluation creates a throwaway user and throwaway proposals, none
        // of which should survive the run.
        $summary = $this->inRolledBackTransaction(
            fn (): RefinementEvaluationSummary => $service->evaluate($cases, $this->evaluationUser()),
        );

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

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inRolledBackTransaction(callable $callback)
    {
        DB::beginTransaction();

        try {
            return $callback();
        } finally {
            DB::rollBack();
        }
    }

    private function evaluationUser(): User
    {
        return User::factory()->create();
    }

    private function renderSummary(RefinementEvaluationSummary $summary): void
    {
        $rows = [];
        foreach ($summary->cases as $case) {
            $rows[] = [
                $case->id,
                $this->flag($case->passed),
                implode(', ', $case->actual['semantic_types']) ?: '—',
                implode(', ', $case->actual['changed_fields']) ?: '—',
                $case->actual['status'],
                $case->failures === [] ? '' : implode('; ', $case->failures),
            ];
        }

        $this->table(
            ['id', 'ok', 'semantic types', 'changed fields', 'status', 'failures'],
            $rows,
        );

        $this->newLine();
        $this->line('<info>Totals</info>');
        $this->line(sprintf('  %-16s %d', 'total cases:', $summary->total));
        $this->line(sprintf('  %-16s %d', 'passed:', $summary->passedCount));
        $this->line(sprintf('  %-16s %d', 'failed:', $summary->failedCount));
    }

    private function flag(bool $value): string
    {
        return $value ? '✓' : '✗';
    }
}
