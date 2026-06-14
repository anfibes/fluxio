<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\Examples\ItalianCorpusObservationService;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Tests\TestCase;

/**
 * Deterministic, network-free integrity guard for the held-out Italian interpretation corpus
 * (packages/Actions/evaluation/it-interpretation-corpus.json) introduced in Slice A4.
 *
 * Mirrors InterpretationCorpusIntegrityTest for the Italian eval set, and additionally enforces
 * the **leakage boundary**: no corpus text may equal any IntentExamples literal/template text in
 * the same locale. The corpus is the held-out measurement set; IntentExamples is the few-shot
 * source — they must never overlap. This test never contacts a model.
 */
class ItalianInterpretationCorpusIntegrityTest extends TestCase
{
    private const MVP_INTENTS = [
        'create_task',
        'schedule_call',
        'schedule_meeting',
        'assign_lead',
        'prepare_contract_from_quote',
    ];

    /** @var list<\Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase> */
    private array $cases;

    private InterpretationGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        // (1) The corpus loads successfully through the loader (invalid JSON/shape would throw).
        $this->cases = $this->app->make(InterpretationCorpusLoader::class)
            ->load(ItalianCorpusObservationService::defaultCorpusPath());

        $this->grammar = $this->app->make(InterpretationGrammar::class);
    }

    public function test_corpus_is_large_enough_to_be_useful(): void
    {
        // A4.2 expanded 51 → 90+ cases: each weighs ~1%, restoring measurement headroom after the
        // strong model saturated the 51-case set (qwen3:1.7b selected = 98% = 1 residual error).
        $this->assertGreaterThanOrEqual(90, count($this->cases));
    }

    public function test_every_case_is_italian(): void
    {
        foreach ($this->cases as $case) {
            $this->assertSame('it', $case->locale, "Case [{$case->id}] is not locale 'it'.");
        }
    }

    public function test_case_ids_are_unique_non_empty_and_kebab_case(): void
    {
        $ids = array_map(fn ($c) => $c->id, $this->cases);

        $this->assertSame(array_values(array_unique($ids)), array_values($ids), 'Duplicate case ids found.');

        foreach ($ids as $id) {
            $this->assertNotSame('', trim($id));
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id, "Case id [{$id}] is not kebab-case.");
        }
    }

    public function test_expected_intents_are_registered_or_unknown(): void
    {
        $registered = $this->grammar->intentNames();

        foreach ($this->cases as $case) {
            $this->assertTrue(
                $case->expectedIntent === 'unknown' || in_array($case->expectedIntent, $registered, true),
                "Case [{$case->id}] expects unregistered intent [{$case->expectedIntent}].",
            );
        }
    }

    public function test_unknown_cases_have_no_expected_entities(): void
    {
        foreach ($this->cases as $case) {
            if ($case->expectedIntent === 'unknown') {
                $this->assertSame([], $case->expectedEntities, "Unknown case [{$case->id}] must have no expected entities.");
            }
        }
    }

    public function test_expected_entity_keys_are_grammar_legal_for_their_intent(): void
    {
        foreach ($this->cases as $case) {
            if ($case->expectedIntent === 'unknown') {
                continue;
            }

            $allowed = $this->grammar->allowedEntityKeys($case->expectedIntent);

            foreach (array_keys($case->expectedEntities) as $key) {
                $this->assertContains(
                    $key,
                    $allowed,
                    "Case [{$case->id}] uses entity key [{$key}] not allowed for intent [{$case->expectedIntent}].",
                );
            }
        }
    }

    public function test_no_forbidden_runtime_or_identity_fields_appear(): void
    {
        // Raw scan: the loader ignores unknown keys, so guard against authoring runtime/identity
        // fields directly in the JSON (case objects, expected blocks, or expected entities).
        $forbidden = ['id_field', 'lead_id', 'selected_candidate_id', 'candidate_id', 'proposal_id', 'status', 'confidence', 'needs_confirmation', 'executed', 'executed_at', 'execution_result', 'execution_failure'];

        $raw = (string) file_get_contents(ItalianCorpusObservationService::defaultCorpusPath());
        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($raw, true);

        foreach ($decoded as $case) {
            $entities = $case['expected']['entities'] ?? [];
            $entityKeys = is_array($entities) ? array_keys($entities) : [];

            foreach ($forbidden as $bad) {
                $this->assertArrayNotHasKey($bad, $case, "Case [{$case['id']}] carries forbidden field [{$bad}].");
                $this->assertNotContains($bad, $entityKeys, "Case [{$case['id']}] asserts forbidden entity key [{$bad}].");
            }
        }
    }

    public function test_every_mvp_intent_has_at_least_fifteen_italian_cases(): void
    {
        $counts = array_count_values(array_map(fn ($c) => $c->expectedIntent, $this->cases));

        foreach (self::MVP_INTENTS as $intent) {
            $this->assertGreaterThanOrEqual(
                15,
                $counts[$intent] ?? 0,
                "Intent [{$intent}] has fewer than 15 Italian cases.",
            );
        }
    }

    public function test_corpus_has_enough_out_of_domain_unknown_cases(): void
    {
        $unknown = array_filter($this->cases, fn ($c) => $c->expectedIntent === 'unknown');

        $this->assertGreaterThanOrEqual(10, count($unknown), 'Expected at least 10 out-of-domain unknown cases.');
    }

    public function test_no_duplicate_corpus_text(): void
    {
        $texts = array_map(fn ($c) => $c->text, $this->cases);

        $this->assertSame(
            array_values(array_unique($texts)),
            array_values($texts),
            'Duplicate corpus text found — each case must be a distinct phrasing.',
        );
    }

    public function test_no_corpus_text_leaks_from_intent_examples(): void
    {
        // Leakage boundary: no Italian corpus text may equal any IntentExamples literal/template
        // text in the same locale. (Few-shot exemplars are rendered from those templates; an exact
        // overlap would mean scoring on a string the model could be shown.)
        $examplePath = dirname(ItalianCorpusObservationService::defaultCorpusPath(), 2)
            .'/resources/intent-examples/it.json';
        $this->assertFileExists($examplePath);

        /** @var list<array<string, mixed>> $examples */
        $examples = json_decode((string) file_get_contents($examplePath), true);

        $exampleTexts = [];
        foreach ($examples as $record) {
            if (isset($record['text'])) {
                $exampleTexts[] = $record['text'];
            }
            if (isset($record['template'])) {
                $exampleTexts[] = $record['template'];
            }
        }

        foreach ($this->cases as $case) {
            $this->assertNotContains(
                $case->text,
                $exampleTexts,
                "Corpus case [{$case->id}] text leaks from the IntentExamples library.",
            );
        }
    }
}
