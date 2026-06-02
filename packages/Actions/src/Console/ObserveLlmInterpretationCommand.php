<?php

namespace Fluxio\Actions\Console;

use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Observation\LlmObservationService;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Illuminate\Console\Command;

/**
 * Development-only LLM observation command (Phase 9F.3B). Runs the configured LLM
 * provider transport over the observation corpus and dumps, per case, how the real
 * model responded: raw text, parsed JSON, structured-output validation outcome, the
 * candidate NormalizedCommand, and the adapter-equivalent sandbox / runtime-validation
 * outcome — plus a partial match against expectations.
 *
 * Its purpose is to inspect raw model behavior and failure modes BEFORE tuning prompts.
 * It builds no proposals, calls no ActionInterpreterService, executes nothing, resolves
 * no entities, and persists nothing. It is blocked in production.
 *
 * CI boundary: this is OPT-IN observation diagnostics (contacts the configured model),
 * NOT a CI gate, and is intentionally excluded from `composer test:actions-corpora`. It
 * is not deterministic testing and introduces no constrained decoding / schema / scoring.
 */
class ObserveLlmInterpretationCommand extends Command
{
    protected $signature = 'actions:observe-llm-interpretation
                            {--case= : Observe only the case with this id}
                            {--limit= : Observe at most N cases}
                            {--json : Emit JSON (the default output)}
                            {--compact : Emit compact single-line JSON instead of pretty}';

    protected $description = 'Opt-in LLM observation: run the configured provider over the observation corpus and dump raw/parsed/validated model behavior as JSON. Real model, no proposals, NOT a CI gate. Non-production.';

    public function handle(
        InterpretationCorpusLoader $loader,
        LlmObservationService $service,
        InterpretationGrammar $grammar,
    ): int {
        if ($this->getLaravel()->environment('production')) {
            $this->error('actions:observe-llm-interpretation is a diagnostics command and is disabled in production.');

            return self::FAILURE;
        }

        $cases = $loader->load(LlmObservationService::defaultCasesPath());

        if (($caseId = $this->option('case')) !== null) {
            $cases = array_values(array_filter($cases, fn ($case) => $case->id === $caseId));

            if ($cases === []) {
                $this->error("No observation case with id [{$caseId}].");

                return self::FAILURE;
            }
        }

        if (($limit = $this->option('limit')) !== null) {
            $cases = array_slice($cases, 0, max(0, (int) $limit));
        }

        $observations = array_map(static fn ($case) => $service->observe($case)->toArray(), $cases);

        // `grammar` records the provider-facing surface (InterpretationGrammar::export())
        // the observation ran against — the same surface the prompt/validator use.
        $payload = [
            'grammar' => $grammar->export(),
            'cases' => $observations,
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (! $this->option('compact')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($payload, $flags));

        return self::SUCCESS;
    }
}
