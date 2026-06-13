<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Diagnostics\Examples\ItalianCorpusObservationService;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
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

    // ── Slice A7: model comparison ────────────────────────────────────────────

    /**
     * Records every request->model and returns a constant valid create_task. A non-null model not
     * in $available throws a transport failure (simulating an unknown local model / HTTP 404).
     */
    private function fakeModels(array $available): object
    {
        $client = new class($available) implements LlmClientInterface
        {
            /** @var list<?string> */
            public array $seen = [];

            public function __construct(private readonly array $available) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $this->seen[] = $request->model;

                if ($request->model !== null && ! in_array($request->model, $this->available, true)) {
                    throw new LlmTransportException('Ollama returned HTTP 404.');
                }

                $payload = ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => ['lead' => 'Bianchi'], 'notes' => []];

                return new LlmResponse(rawText: (string) json_encode($payload), parsedJson: $payload);
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    public function test_default_run_uses_configured_model_and_does_not_override_the_request(): void
    {
        $client = $this->fakeModels([]);

        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari']);

        // Reports the configured model; the request carries no override (null) and no probe runs.
        $this->assertSame((string) config('actions.llm.model'), $decoded['model']);
        $this->assertStringContainsString('it-interpretation-corpus.json', $decoded['corpus']);
        $this->assertSame([null], $client->seen);
    }

    public function test_model_override_is_passed_to_the_request_and_reported(): void
    {
        $client = $this->fakeModels(['qwen3:1.7b']);

        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:1.7b']);

        $this->assertSame('qwen3:1.7b', $decoded['model']);
        // Probe (1) + single-case run (1), both with the overridden model.
        $this->assertSame(['qwen3:1.7b', 'qwen3:1.7b'], $client->seen);
    }

    public function test_unavailable_single_model_fails_clearly(): void
    {
        $this->fakeModels(['qwen3:0.6b']);

        $this->artisan('actions:observe-italian-corpus', ['--model' => 'ghost-model'])
            ->assertFailed()
            ->expectsOutputToContain('Model [ghost-model] is not available');
    }

    public function test_multi_model_comparison_reports_per_model_with_availability(): void
    {
        $this->fakeModels(['model-good']);

        $decoded = $this->runJson(['--models' => 'model-good,model-missing', '--limit' => '3']);

        $this->assertStringContainsString('it-interpretation-corpus.json', $decoded['corpus']);
        $this->assertFalse($decoded['few_shot']);
        $this->assertFalse($decoded['compare']);
        $this->assertCount(2, $decoded['models']);

        $good = $decoded['models'][0];
        $this->assertSame('model-good', $good['model']);
        $this->assertTrue($good['available']);
        $this->assertArrayHasKey('metrics', $good);
        $this->assertSame(3, $good['evaluated']);

        $missing = $decoded['models'][1];
        $this->assertSame('model-missing', $missing['model']);
        $this->assertFalse($missing['available']);
        $this->assertStringContainsString('HTTP 404', $missing['reason']);
    }

    public function test_compare_works_with_a_model_override(): void
    {
        $client = $this->fakeModels(['m1']);

        $decoded = $this->runJson(['--compare' => true, '--model' => 'm1', '--limit' => '4']);

        $this->assertSame('m1', $decoded['model']);
        $this->assertArrayHasKey('baseline', $decoded);
        $this->assertArrayHasKey('few_shot_result', $decoded);
        $this->assertArrayHasKey('delta', $decoded);
        // Every request (probe + baseline pass + few-shot pass) used the override.
        $this->assertTrue(collect($client->seen)->every(fn ($m) => $m === 'm1'));
    }

    // ── Slice A7.1: diagnostics experiment flags ──────────────────────────────

    /** Records every LlmRequest and returns a constant valid create_task. */
    private function fakeRecordingRequests(): object
    {
        $client = new class implements LlmClientInterface
        {
            /** @var list<LlmRequest> */
            public array $requests = [];

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $this->requests[] = $request;

                $payload = ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => ['lead' => 'Bianchi'], 'notes' => []];

                return new LlmResponse(
                    rawText: (string) json_encode($payload),
                    parsedJson: $request->jsonSchema !== null ? $payload : null,
                    rawBody: ['response' => (string) json_encode($payload), 'done' => true],
                );
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    public function test_default_command_run_forces_json_and_sends_no_thinking_override(): void
    {
        $client = $this->fakeRecordingRequests();

        $this->runJson(['--case' => 'it-create-task-followup-ferrari']);

        $this->assertNotEmpty($client->requests);
        $request = $client->requests[0];
        $this->assertNull($request->think);
        $this->assertSame(['type' => 'object'], $request->jsonSchema);
        $this->assertNull($request->maxTokens);
    }

    public function test_no_think_flag_disables_thinking_on_the_request(): void
    {
        $client = $this->fakeRecordingRequests();

        $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--no-think' => true]);

        $this->assertFalse($client->requests[0]->think);
        $this->assertSame(['type' => 'object'], $client->requests[0]->jsonSchema);
    }

    public function test_free_text_flag_drops_forced_json_on_the_request(): void
    {
        $client = $this->fakeRecordingRequests();

        $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--free-text' => true]);

        $this->assertNull($client->requests[0]->jsonSchema);
    }

    public function test_num_predict_flag_sets_the_generation_budget(): void
    {
        $client = $this->fakeRecordingRequests();

        $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--num-predict' => '256']);

        $this->assertSame(256, $client->requests[0]->maxTokens);
    }

    public function test_raw_body_is_present_in_case_output(): void
    {
        $this->fakeRecordingRequests();

        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari']);

        $this->assertArrayHasKey('raw_body', $decoded['cases'][0]);
        $this->assertSame(['response' => '{"intent":"create_task","confidence":0.8,"entities":{"lead":"Bianchi"},"notes":[]}', 'done' => true], $decoded['cases'][0]['raw_body']);
    }

    // ── Slice A7.2: capability-class profiles auto-selected per model ──────────

    public function test_default_model_auto_selects_instruction_following_profile(): void
    {
        $client = $this->fakeRecordingRequests();

        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari']);

        // Configured default model (qwen3:0.6b) → instruction-following → byte-identical request.
        $this->assertSame('instruction-following', $decoded['profile']['id']);
        $this->assertNull($decoded['profile']['think']);
        $this->assertTrue($decoded['profile']['force_json']);
        $this->assertNull(end($client->requests)->think);
        $this->assertSame(['type' => 'object'], end($client->requests)->jsonSchema);
    }

    public function test_reasoning_model_auto_selects_reasoning_profile_and_disables_thinking(): void
    {
        $client = $this->fakeRecordingRequests();

        // No flags: qwen3:1.7b → reasoning profile → think=false automatically.
        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:1.7b']);

        $this->assertSame('reasoning', $decoded['profile']['id']);
        $this->assertFalse($decoded['profile']['think']);
        $this->assertTrue($decoded['profile']['force_json']);
        // The actual evaluation request (last; the probe ran first) was driven think=false.
        $this->assertFalse(end($client->requests)->think);
        $this->assertSame(['type' => 'object'], end($client->requests)->jsonSchema);
    }

    public function test_cli_no_think_overrides_an_instruction_following_profile(): void
    {
        $client = $this->fakeRecordingRequests();

        // qwen3:0.6b's profile is think=null; --no-think must override it to false.
        $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:0.6b', '--no-think' => true]);

        $this->assertFalse(end($client->requests)->think);
    }

    public function test_cli_free_text_only_controls_force_json_and_keeps_profile_thinking(): void
    {
        $client = $this->fakeRecordingRequests();

        // qwen3:1.7b reasoning profile = think=false + forceJson=true. --free-text drops forced JSON
        // ONLY; thinking must stay at the profile's choice (false) — this is NOT A7.1 E2.
        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:1.7b', '--free-text' => true]);

        $this->assertSame('reasoning', $decoded['profile']['id']);
        $this->assertNull(end($client->requests)->jsonSchema, 'free-text override must drop forced JSON.');
        $this->assertFalse(end($client->requests)->think, 'free-text must not change thinking.');

        $this->assertFalse($decoded['effective']['think']);
        $this->assertSame('profile', $decoded['effective']['think_source']);
        $this->assertFalse($decoded['effective']['force_json']);
        $this->assertSame('cli:--free-text', $decoded['effective']['force_json_source']);
    }

    public function test_think_plus_free_text_reproduces_a71_e2(): void
    {
        $client = $this->fakeRecordingRequests();

        // A7.1 E2 = thinking ON + free-text + extract. --think forces thinking back on over the
        // reasoning profile's think=false; --free-text drops forced JSON.
        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:1.7b', '--free-text' => true, '--think' => true]);

        $this->assertTrue(end($client->requests)->think, '--think must force thinking on.');
        $this->assertNull(end($client->requests)->jsonSchema, '--free-text must drop forced JSON.');

        $this->assertTrue($decoded['effective']['think']);
        $this->assertSame('cli:--think', $decoded['effective']['think_source']);
        $this->assertFalse($decoded['effective']['force_json']);
    }

    public function test_think_flag_overrides_a_reasoning_profile(): void
    {
        $client = $this->fakeRecordingRequests();

        // Reasoning profile defaults think=false; --think forces it back on (format still forced JSON).
        $decoded = $this->runJson(['--case' => 'it-create-task-followup-ferrari', '--model' => 'qwen3:1.7b', '--think' => true]);

        $this->assertTrue(end($client->requests)->think);
        $this->assertSame(['type' => 'object'], end($client->requests)->jsonSchema);
        $this->assertSame('cli:--think', $decoded['effective']['think_source']);
    }

    public function test_think_and_no_think_together_fail_clearly(): void
    {
        $this->fakeRecordingRequests();

        $this->artisan('actions:observe-italian-corpus', ['--think' => true, '--no-think' => true])
            ->assertFailed()
            ->expectsOutputToContain('--think and --no-think cannot be used together');
    }

    public function test_compare_uses_the_resolved_reasoning_profile(): void
    {
        $client = $this->fakeRecordingRequests();

        // --compare with qwen3:1.7b must drive every evaluation request think=false (reasoning profile),
        // not just the (default-options) availability probe.
        $decoded = $this->runJson(['--compare' => true, '--model' => 'qwen3:1.7b', '--limit' => '2']);

        $this->assertSame('reasoning', $decoded['profile']['id']);
        $this->assertFalse($decoded['effective']['think']);

        // First request is the availability probe (default options, think=null); every subsequent
        // baseline + few-shot request must carry the profile's think=false.
        $evaluationRequests = array_slice($client->requests, 1);
        $this->assertNotEmpty($evaluationRequests);
        $this->assertTrue(collect($evaluationRequests)->every(fn ($r) => $r->think === false));
    }

    public function test_compare_honors_cli_overrides_over_the_profile(): void
    {
        $client = $this->fakeRecordingRequests();

        // --compare + --think + --free-text must thread think=true / forceJson=false into both passes.
        $this->runJson(['--compare' => true, '--model' => 'qwen3:1.7b', '--think' => true, '--free-text' => true, '--limit' => '2']);

        $evaluationRequests = array_slice($client->requests, 1);
        $this->assertNotEmpty($evaluationRequests);
        $this->assertTrue(collect($evaluationRequests)->every(fn ($r) => $r->think === true && $r->jsonSchema === null));
    }

    public function test_multi_model_reports_a_resolved_profile_and_effective_per_model(): void
    {
        $this->fakeRecordingRequests();

        $decoded = $this->runJson(['--models' => 'qwen3:0.6b,qwen3:1.7b', '--limit' => '2']);

        $this->assertCount(2, $decoded['models']);
        $this->assertSame('instruction-following', $decoded['models'][0]['profile']['id']);
        $this->assertNull($decoded['models'][0]['effective']['think']);
        $this->assertSame('profile', $decoded['models'][0]['effective']['think_source']);

        $this->assertSame('reasoning', $decoded['models'][1]['profile']['id']);
        $this->assertFalse($decoded['models'][1]['profile']['think']);
        $this->assertFalse($decoded['models'][1]['effective']['think']);
    }
}
