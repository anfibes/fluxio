<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Diagnostics\Examples\ItalianCorpusObservationService;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Slice A4 — held-out Italian corpus observation. No real Ollama: LlmClientInterface is faked so
 * the observation pipeline (the same LlmObservationService A1/A2 use) runs deterministically. The
 * command must build no proposals, stay production-blocked, and remain outside the deterministic
 * gate. The corpus stays held-out; IntentExamples stay non-authoritative.
 */
class ObserveItalianCorpusCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Constant fake: always returns the same contract-valid create_task candidate. */
    private function fakeConstant(): void
    {
        $payload = ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => ['lead' => 'Bianchi'], 'notes' => []];

        $client = new class($payload) implements LlmClientInterface
        {
            public function __construct(private readonly array $payload) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(rawText: (string) json_encode($this->payload), parsedJson: $this->payload);
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);
    }

    /**
     * Few-shot-aware fake: returns `unknown` for the baseline prompt and `schedule_call` once the
     * few-shot "Worked examples" block is present — so improved/regressed outcomes are deterministic.
     */
    private function fakeFewShotAware(): void
    {
        $client = new class implements LlmClientInterface
        {
            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $isFewShot = str_contains((string) $request->systemPrompt, 'Worked examples');

                $payload = $isFewShot
                    ? ['intent' => 'schedule_call', 'confidence' => 0.9, 'entities' => ['lead' => 'Tizio'], 'notes' => []]
                    : ['intent' => 'unknown', 'confidence' => 0.0, 'entities' => [], 'notes' => []];

                return new LlmResponse(rawText: (string) json_encode($payload), parsedJson: $payload);
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);
    }

    /** @return array<string, mixed> */
    private function runJson(array $options = []): array
    {
        Artisan::call('actions:observe-italian-corpus', $options + ['--json' => true]);

        return json_decode(Artisan::output(), true);
    }

    // ── Diagnostics-only guarantees ───────────────────────────────────────────

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:observe-italian-corpus')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_command_creates_no_action_proposal_records(): void
    {
        $this->fakeConstant();

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:observe-italian-corpus', ['--case' => 'it-create-task-followup-ferrari'])->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_not_part_of_the_deterministic_ci_gate(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $gate = implode("\n", (array) ($composer['scripts']['test:actions-corpora'] ?? []));

        $this->assertStringNotContainsString('actions:observe-italian-corpus', $gate);
    }

    // ── Baseline observation (no few-shot) ────────────────────────────────────

    public function test_baseline_run_reports_metrics_with_measurable_entity_agreement(): void
    {
        $this->fakeConstant();

        // Single create_task case: constant fake matches the intent but not the lead value.
        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari']);

        $this->assertSame('it', $decoded['locale']);
        $this->assertSame(1, $decoded['evaluated']);
        $this->assertFalse($decoded['few_shot']['enabled']);

        $metrics = $decoded['metrics'];
        $this->assertSame(1, $metrics['produced']['count']);
        $this->assertSame(1, $metrics['contract_valid']['count']);
        $this->assertSame(1, $metrics['intent_match']['count']);
        // Entity-agreement is now measurable (the corpus declares expected entities): comparable=1,
        // but the constant fake emitted lead "Bianchi" ≠ expected "Ferrari" → no agreement.
        $this->assertSame(1, $metrics['entity_agreement']['comparable']);
        $this->assertSame(0, $metrics['entity_agreement']['count']);
    }

    public function test_full_corpus_runs_all_cases(): void
    {
        $this->fakeConstant();

        $decoded = $this->runJson();

        $this->assertGreaterThanOrEqual(45, $decoded['evaluated']);
        // create_task is the only intent the constant fake matches.
        $this->assertSame(9, $decoded['metrics']['intent_match']['count']);
    }

    // ── Few-shot observation ──────────────────────────────────────────────────

    public function test_few_shot_run_enables_template_exemplars(): void
    {
        $this->fakeFewShotAware();

        $decoded = $this->runJson(['--case' => 'it-schedule-call-domani-ferrari', '--few-shot' => true]);

        $this->assertTrue($decoded['few_shot']['enabled']);
        $this->assertGreaterThan(0, $decoded['few_shot']['count']);
        foreach ($decoded['few_shot']['example_ids'] as $id) {
            $this->assertStringContainsString('-template', $id);
        }
        // With few-shot present the aware fake returns schedule_call, matching the case.
        $this->assertSame(1, $decoded['metrics']['intent_match']['count']);
    }

    // ── A5 comparison (improved / regressed explicit) ─────────────────────────

    public function test_compare_reports_improved_and_regressed_outcomes(): void
    {
        $this->fakeFewShotAware();

        $decoded = $this->runJson(['--compare' => true]);

        $this->assertSame('it', $decoded['locale']);
        $this->assertGreaterThan(0, $decoded['few_shot']['count']);

        // Baseline → unknown everywhere: only the unknown cases pass.
        // Few-shot → schedule_call everywhere: only the schedule_call cases pass.
        $this->assertSame(6, $decoded['baseline']['intent_match']['count']);
        $this->assertSame(9, $decoded['few_shot_result']['intent_match']['count']);

        $delta = $decoded['delta'];
        $this->assertSame(9, $delta['improved']);   // 9 schedule_call cases now pass
        $this->assertSame(6, $delta['regressed']);  // 6 unknown cases now fail
        $this->assertSame(0, $delta['unchanged_pass']);
        $this->assertSame($decoded['total'] - 15, $delta['unchanged_fail']);

        // Ids are surfaced for quick scanning.
        $this->assertContains('it-schedule-call-domani-ferrari', $delta['improved_ids']);
        $this->assertContains('it-unknown-meteo', $delta['regressed_ids']);

        // Per-case rows carry the outcome taxonomy.
        $row = collect($decoded['cases'])->firstWhere('id', 'it-schedule-call-domani-ferrari');
        $this->assertSame('schedule_call', $row['expected_intent']);
        $this->assertSame('improved', $row['outcome']);
        $this->assertFalse($row['baseline_pass']);
        $this->assertTrue($row['fewshot_pass']);
    }

    // ── Filters + leakage ─────────────────────────────────────────────────────

    public function test_unknown_case_id_fails_clearly(): void
    {
        $this->fakeConstant();

        Artisan::call('actions:observe-italian-corpus', ['--case' => 'does-not-exist']);

        $this->assertStringContainsString('No Italian corpus case with id [does-not-exist]', Artisan::output());
    }

    public function test_few_shot_exemplars_never_duplicate_a_corpus_input(): void
    {
        $service = $this->app->make(ItalianCorpusObservationService::class);
        $cases = $service->load();

        $corpusTexts = array_map(static fn ($c): string => $c->text, $cases);
        $exemplars = $service->exemplarsFor('it', $cases);

        $this->assertNotEmpty($exemplars);
        foreach ($exemplars as $exemplar) {
            $this->assertNotContains($exemplar->inputText, $corpusTexts, 'A few-shot exemplar duplicated a held-out corpus input.');
        }
    }
}
