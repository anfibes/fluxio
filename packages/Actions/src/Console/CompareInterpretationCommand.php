<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Illuminate\Console\Command;

/**
 * Development-only diagnostic command. Compares the deterministic provider with
 * the Ollama sandbox provider for one input and prints the structured result.
 *
 * It builds no proposals, calls no ActionInterpreterService, persists nothing,
 * and does not change the configured runtime interpretation provider. It is
 * blocked in production.
 *
 * CI boundary: this is OPT-IN provider diagnostics (may contact the Ollama sandbox),
 * not a CI gate, and is intentionally excluded from `composer test:actions-corpora`.
 *
 * Grammar awareness (Phase 9F.3A): the output also carries the read-only
 * InterpretationGrammar::export() artifact — the same provider-facing surface the
 * prompt builder renders and the structured-output validator enforces — so a reader
 * can see which contract/version and intent/key surface the comparison ran against.
 * Observability only; nothing here enforces the grammar or changes runtime.
 */
class CompareInterpretationCommand extends Command
{
    protected $signature = 'actions:compare-interpretation
                            {text : Natural-language input to interpret}
                            {--locale=en : Locale passed to the interpretation context}
                            {--json : Emit raw pretty JSON instead of the summary table}';

    protected $description = 'Opt-in provider diagnostics: compare deterministic vs. Ollama sandbox interpretation for one input. NOT a CI gate; excluded from composer test:actions-corpora. Non-production.';

    public function handle(InterpretationComparisonService $service, InterpretationGrammar $grammar): int
    {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:compare-interpretation is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $text = (string) $this->argument('text');
        $result = $service->compare($text, new InterpretationContext(locale: (string) $this->option('locale')));

        if ($this->option('json')) {
            // Additive: the existing comparison fields are unchanged; `grammar` records the
            // provider-facing surface (from InterpretationGrammar::export()) the run used.
            $payload = $result->toArray();
            $payload['grammar'] = $grammar->export();

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderSummary($result->toArray());
        $this->renderGrammar($grammar->export());

        return self::SUCCESS;
    }

    /**
     * Concise grammar-awareness block: which provider-facing contract/version and
     * intent/key surface the diagnostics ran against. Derived from
     * InterpretationGrammar::export(); observability only.
     *
     * @param  array<string, mixed>  $grammar
     */
    private function renderGrammar(array $grammar): void
    {
        $this->newLine();
        $this->line('<info>grammar:</info> '.$grammar['contract'].' v'.$grammar['version']);
        $this->line('  allowed_reference_keys: '.implode(', ', $grammar['allowed_reference_keys']));
        $this->line('  intents: '.implode(', ', $grammar['intents']));
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
