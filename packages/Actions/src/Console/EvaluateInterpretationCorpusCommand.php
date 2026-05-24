<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationEvaluationService;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command. Evaluates the interpretation corpus by
 * running every case through the Phase 5A comparison service and reporting how
 * the deterministic and Ollama sandbox providers match the expected results.
 *
 * It builds no proposals, calls no ActionInterpreterService, persists nothing,
 * and does not change the configured runtime interpretation provider. It is
 * blocked in production. A captured Ollama failure is reported per case, not a
 * crash; only an invalid corpus file aborts the run.
 */
class EvaluateInterpretationCorpusCommand extends Command
{
    protected $signature = 'actions:evaluate-interpretation-corpus
                            {--path= : Path to a custom corpus JSON file (defaults to the shipped corpus)}
                            {--json : Emit the full evaluation as pretty JSON instead of the summary table}';

    protected $description = 'Diagnostics: evaluate the interpretation corpus (deterministic vs. Ollama sandbox) against expected results (non-production).';

    public function handle(InterpretationCorpusLoader $loader, InterpretationEvaluationService $service): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:evaluate-interpretation-corpus is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: InterpretationCorpusLoader::defaultCorpusPath());
        $cases = $loader->load($path);

        $summary = $service->evaluate($cases);

        if ($this->option('json')) {
            $this->line(json_encode($summary->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderSummary($summary->toArray());

        return self::SUCCESS;
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
