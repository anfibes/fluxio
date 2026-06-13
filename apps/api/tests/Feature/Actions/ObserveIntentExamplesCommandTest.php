<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Diagnostics\Examples\IntentExampleObservationService;
use Fluxio\Actions\Examples\IntentExample;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
use Fluxio\Actions\Llm\Prompting\PromptExemplar;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Slice A1 — Italian Intent Examples → LLM sandbox observation baseline.
 *
 * No real Ollama: LlmClientInterface is faked so the observation pipeline (the SAME
 * LlmObservationService the EN observation command uses) runs deterministically. The command
 * must build no proposals, stay production-blocked, and remain outside the deterministic gate.
 * IntentExamples stay non-authoritative; this only measures the current baseline.
 */
class ObserveIntentExamplesCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed>|null $parsedJson */
    private function fakeLlmReturning(?array $parsedJson): void
    {
        $client = new class($parsedJson) implements LlmClientInterface
        {
            public function __construct(private readonly ?array $parsedJson) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(
                    rawText: $this->parsedJson === null ? 'not json' : (string) json_encode($this->parsedJson),
                    parsedJson: $this->parsedJson,
                );
            }
        };

        $this->app->bind(LlmClientInterface::class, fn () => $client);
    }

    private function fakeLlmThrowing(\Throwable $e): void
    {
        $client = new class($e) implements LlmClientInterface
        {
            public function __construct(private readonly \Throwable $e) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                throw $this->e;
            }
        };

        $this->app->bind(LlmClientInterface::class, fn () => $client);
    }

    /** @return array<string, mixed> */
    private function validCreateTask(): array
    {
        return ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => ['lead' => 'Rossini'], 'notes' => []];
    }

    /** @return array<string, mixed> */
    private function runJson(array $options = []): array
    {
        Artisan::call('actions:observe-intent-examples', $options + ['--json' => true]);

        return json_decode(Artisan::output(), true);
    }

    // ── Service: selection of Italian literal examples ────────────────────────

    public function test_service_selects_italian_literal_examples_only(): void
    {
        $service = $this->app->make(IntentExampleObservationService::class);

        $examples = $service->select('it');

        $this->assertNotEmpty($examples);
        $this->assertContainsOnlyInstancesOf(IntentExample::class, $examples);

        foreach ($examples as $example) {
            $this->assertSame('it', $example->locale);
            $this->assertTrue($example->hasLiteral(), "Selected example [{$example->id}] must carry literal text.");
        }

        // The 5 MVP intents each have one Italian literal (the `*-basic` seed records).
        $ids = array_map(static fn (IntentExample $e): string => $e->id, $examples);
        $this->assertContains('it-create-task-basic', $ids);
        $this->assertCount(5, $examples);
    }

    // ── Service: runs against a faked LLM (no real Ollama) ────────────────────

    public function test_service_runs_through_the_sandbox_with_a_faked_client(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $report = $this->app->make(IntentExampleObservationService::class)->observe('it');

        $this->assertSame('it', $report->locale);
        $this->assertCount(5, $report->results);

        $metrics = $report->metrics;
        $this->assertSame(5, $metrics->total);
        // The fake yields a contract-valid create_task command for every input.
        $this->assertSame(5, $metrics->producedCount);
        $this->assertSame(5, $metrics->contractValidCount);
        $this->assertSame(0, $metrics->providerFailureCount);
        $this->assertSame(0, $metrics->validationFailureCount);
        // Only the create_task example matches the forced intent.
        $this->assertSame(1, $metrics->intentMatchCount);
        // Literal examples declare no expected entities → entity agreement is not comparable.
        $this->assertSame(0, $metrics->entityComparableCount);
    }

    // ── Diagnostics-only guarantees ───────────────────────────────────────────

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:observe-intent-examples')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_command_creates_no_action_proposal_records(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:observe-intent-examples')->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_not_part_of_the_deterministic_ci_gate(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $gate = implode("\n", (array) ($composer['scripts']['test:actions-corpora'] ?? []));

        $this->assertStringNotContainsString('actions:observe-intent-examples', $gate);
    }

    // ── JSON output + aggregate metrics ───────────────────────────────────────

    public function test_json_output_reports_metrics_and_cases(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $decoded = $this->runJson();

        $this->assertSame('it', $decoded['locale']);
        $this->assertSame(5, $decoded['evaluated']);

        $metrics = $decoded['metrics'];
        $this->assertSame(5, $metrics['total']);
        $this->assertSame(5, $metrics['produced']['count']);
        $this->assertSame(5, $metrics['contract_valid']['count']);
        $this->assertSame(1, $metrics['intent_match']['count']);
        $this->assertSame(0, $metrics['entity_agreement']['comparable']);
        $this->assertNull($metrics['entity_agreement']['rate']);

        $this->assertCount(5, $decoded['cases']);
        // Italian accented characters / apostrophes survive unescaped.
        $createTask = collect($decoded['cases'])->firstWhere('id', 'it-create-task-basic');
        $this->assertStringContainsString("Crea un'attività", $createTask['text']);
        $this->assertTrue($createTask['match']['intent']);
        // Entity agreement is not measurable for literal examples.
        $this->assertNull($createTask['match']['entities']);
        $this->assertFalse($createTask['match']['entities_comparable']);
    }

    // ── Filters: locale + case ────────────────────────────────────────────────

    public function test_case_filter_runs_a_single_example(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $decoded = $this->runJson(['--case' => 'it-create-task-basic']);

        $this->assertCount(1, $decoded['cases']);
        $this->assertSame('it-create-task-basic', $decoded['cases'][0]['id']);
        $this->assertSame(1, $decoded['metrics']['total']);
    }

    public function test_locale_filter_selects_english_examples(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $decoded = $this->runJson(['--locale' => 'en']);

        $this->assertSame('en', $decoded['locale']);
        $this->assertSame(5, $decoded['evaluated']);
    }

    public function test_unknown_case_id_fails_clearly(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        Artisan::call('actions:observe-intent-examples', ['--case' => 'does-not-exist']);

        $this->assertStringContainsString('No literal intent example with id [does-not-exist]', Artisan::output());
    }

    // ── Provider failures are captured, not thrown ────────────────────────────

    public function test_provider_transport_failure_is_reported_as_failure(): void
    {
        $this->fakeLlmThrowing(new LlmTransportException('Ollama returned HTTP 500.'));

        $decoded = $this->runJson(['--case' => 'it-create-task-basic']);

        $case = $decoded['cases'][0];
        $this->assertFalse($case['provider']['succeeded']);
        $this->assertSame(LlmTransportException::class, $case['provider']['failure_class']);
        $this->assertSame(1, $decoded['metrics']['provider_failures']);
        $this->assertSame(0, $decoded['metrics']['produced']['count']);
    }

    public function test_contract_violation_is_reported_as_validation_failure(): void
    {
        // Unregistered intent — fails the structured-output contract, no command produced.
        $this->fakeLlmReturning(['intent' => 'hallucinated_action', 'confidence' => 0.9, 'entities' => [], 'notes' => []]);

        $decoded = $this->runJson(['--case' => 'it-create-task-basic']);

        $case = $decoded['cases'][0];
        $this->assertTrue($case['provider']['succeeded']);
        $this->assertFalse($case['structured_output']['valid']);
        $this->assertNull($case['normalized_command']);
        $this->assertSame(1, $decoded['metrics']['validation_failures']);
        $this->assertSame(0, $decoded['metrics']['contract_valid']['count']);
    }

    // ── Slice A2: few-shot exemplars ──────────────────────────────────────────

    /** Captures the last system prompt the client received, so we can assert prompt content. */
    private function capturingFakeLlm(array $parsedJson): object
    {
        $client = new class($parsedJson) implements LlmClientInterface
        {
            public ?string $lastSystemPrompt = null;

            public function __construct(private readonly array $parsedJson) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $this->lastSystemPrompt = $request->systemPrompt;

                return new LlmResponse(rawText: (string) json_encode($this->parsedJson), parsedJson: $this->parsedJson);
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    public function test_default_run_does_not_enable_few_shot(): void
    {
        $client = $this->capturingFakeLlm($this->validCreateTask());

        $decoded = $this->runJson(['--case' => 'it-create-task-basic']);

        $this->assertFalse($decoded['few_shot']['enabled']);
        $this->assertSame(0, $decoded['few_shot']['count']);
        $this->assertSame([], $decoded['few_shot']['example_ids']);
        // Baseline prompt carries no worked-examples block.
        $this->assertStringNotContainsString('Worked examples', (string) $client->lastSystemPrompt);
    }

    public function test_few_shot_option_enables_template_exemplars_in_the_prompt(): void
    {
        $client = $this->capturingFakeLlm($this->validCreateTask());

        $decoded = $this->runJson(['--case' => 'it-schedule-call-basic', '--few-shot' => true]);

        $this->assertTrue($decoded['few_shot']['enabled']);
        $this->assertGreaterThan(0, $decoded['few_shot']['count']);
        // Exemplars are sourced from TEMPLATE examples, never the evaluated literals.
        foreach ($decoded['few_shot']['example_ids'] as $id) {
            $this->assertStringContainsString('-template', $id);
        }

        $prompt = (string) $client->lastSystemPrompt;
        $this->assertStringContainsString('Worked examples', $prompt);
        $this->assertStringContainsString('"intent":"schedule_call"', $prompt);
    }

    public function test_few_shot_does_not_leak_the_evaluated_literal_verbatim(): void
    {
        $client = $this->capturingFakeLlm($this->validCreateTask());

        // Evaluated input is the Italian schedule_call literal.
        $this->runJson(['--case' => 'it-schedule-call-basic', '--few-shot' => true]);

        $literal = 'Pianifica una chiamata con Rossini domani alle 10';
        $prompt = (string) $client->lastSystemPrompt;

        // The literal appears once — as the evaluated user message is NOT in the system prompt;
        // the few-shot block (system prompt) must not contain the evaluated literal at all.
        $this->assertStringNotContainsString($literal, $prompt);
    }

    public function test_few_shot_exemplars_are_provider_contract_safe(): void
    {
        $service = $this->app->make(IntentExampleObservationService::class);
        $validator = $this->app->make(LlmStructuredOutputValidator::class);

        foreach (['it', 'en'] as $locale) {
            $exemplars = $service->fewShotExemplars($locale);

            $this->assertNotEmpty($exemplars, "Expected few-shot exemplars for locale [{$locale}].");
            $this->assertLessThanOrEqual(5, count($exemplars));

            foreach ($exemplars as $exemplar) {
                $this->assertInstanceOf(PromptExemplar::class, $exemplar);
                // Each exemplar output must pass the SAME contract validator the sandbox uses.
                $this->assertTrue(
                    $validator->validate($exemplar->output)->valid,
                    "Exemplar [{$exemplar->sourceId}] output is not contract-safe.",
                );
                // No forbidden identity/lifecycle keys in the exemplar entities.
                $forbidden = ['id', 'lead_id', 'selected_candidate_id', 'status', 'executed'];
                foreach (array_keys($exemplar->output['entities']) as $key) {
                    $this->assertNotContains($key, $forbidden);
                }
            }
        }
    }

    public function test_few_shot_excludes_the_evaluated_literal_text_from_exemplars(): void
    {
        $service = $this->app->make(IntentExampleObservationService::class);

        $literals = array_map(
            static fn (IntentExample $e): string => (string) $e->text,
            $service->select('it'),
        );

        $exemplars = $service->fewShotExemplars('it', null, $literals);

        foreach ($exemplars as $exemplar) {
            $this->assertNotContains($exemplar->inputText, $literals, 'A few-shot exemplar duplicated an evaluated literal.');
        }
    }

    public function test_few_shot_limit_is_respected(): void
    {
        $service = $this->app->make(IntentExampleObservationService::class);

        $this->assertCount(2, $service->fewShotExemplars('it', 2));
    }

    public function test_few_shot_report_metadata_is_present_in_json(): void
    {
        $this->capturingFakeLlm($this->validCreateTask());

        $decoded = $this->runJson(['--locale' => 'it', '--few-shot' => true]);

        $this->assertArrayHasKey('few_shot', $decoded);
        $this->assertTrue($decoded['few_shot']['enabled']);
        $this->assertSame(count($decoded['few_shot']['example_ids']), $decoded['few_shot']['count']);
        $this->assertNotEmpty($decoded['few_shot']['example_ids']);
    }
}
