<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command. Compares the deterministic provider with
 * the Ollama sandbox provider for one input and prints the structured result.
 *
 * It builds no proposals, calls no ActionInterpreterService, persists nothing,
 * and does not change the configured runtime interpretation provider. It is
 * blocked in production.
 */
class CompareInterpretationCommand extends Command
{
    protected $signature = 'actions:compare-interpretation
                            {text : Natural-language input to interpret}
                            {--locale=en : Locale passed to the interpretation context}
                            {--json : Emit raw pretty JSON instead of the summary table}';

    protected $description = 'Diagnostics: compare deterministic vs. Ollama sandbox interpretation for one input (non-production).';

    public function handle(InterpretationComparisonService $service): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:compare-interpretation is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $text = (string) $this->argument('text');
        $result = $service->compare($text, new InterpretationContext(locale: (string) $this->option('locale')));

        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderSummary($result->toArray());

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderSummary(array $data): void
    {
        $this->line("<info>Input:</info> {$data['text']}");
        $this->newLine();

        $this->table(
            ['provider', 'success', 'intent', 'confidence', 'error'],
            [
                $this->providerRow($data['deterministic']),
                $this->providerRow($data['ollama']),
            ],
        );

        $sameIntent = $data['same_intent'];
        $this->line('<info>same_intent:</info> '.($sameIntent === null ? 'n/a' : ($sameIntent ? 'true' : 'false')));
        $this->line('<info>confidence_delta:</info> '.($data['confidence_delta'] ?? 'n/a'));

        if ($data['notes'] !== []) {
            $this->newLine();
            $this->line('<info>notes:</info>');
            foreach ($data['notes'] as $note) {
                $this->line("  - {$note}");
            }
        }

        $this->newLine();
        $this->line('<info>entity_diff:</info>');
        $this->line('  '.json_encode($data['entity_diff'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('<info>warning_diff:</info>');
        $this->line('  '.json_encode($data['warning_diff'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return list<string>
     */
    private function providerRow(array $provider): array
    {
        return [
            (string) $provider['provider'],
            $provider['success'] ? 'yes' : 'no',
            $provider['intent'] ?? '—',
            $provider['confidence'] === null ? '—' : (string) $provider['confidence'],
            $provider['error_class'] ?? '—',
        ];
    }
}
