<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Diagnostics\Evaluation\InterpretationCorpusLoader;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Tests\TestCase;

/**
 * Deterministic, network-free integrity guard for the provider-level diagnostics
 * corpus (packages/Actions/evaluation/interpretation-corpus.json).
 *
 * The corpus itself may contact a model when run via
 * `actions:evaluate-interpretation-corpus`, but this test never does: it only
 * loads the JSON through InterpretationCorpusLoader and checks it stays
 * structurally honest against the runtime-owned InterpretationGrammar — so an
 * authoring mistake (typo'd intent, a `lead_query`/forbidden key, an out-of-grammar
 * key, a non-empty `unknown`) fails here rather than silently skewing diagnostics.
 * It guards the corpus the way ActionsCorpusGateTest guards the deterministic gate.
 */
class InterpretationCorpusIntegrityTest extends TestCase
{
    /** @var list<\Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase> */
    private array $cases;

    private InterpretationGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        // (1) The corpus loads successfully through the loader (invalid JSON/shape would throw).
        $this->cases = $this->app->make(InterpretationCorpusLoader::class)
            ->load(InterpretationCorpusLoader::defaultCorpusPath());

        $this->grammar = $this->app->make(InterpretationGrammar::class);
    }

    public function test_corpus_is_roughly_the_target_size(): void
    {
        // (6) Enough cases that each one weighs ~2%, not ~11%. Lower bound only — not a brittle exact count.
        $this->assertGreaterThanOrEqual(45, count($this->cases));
    }

    public function test_case_ids_are_unique_non_empty_and_kebab_case(): void
    {
        $ids = array_map(fn ($c) => $c->id, $this->cases);

        // (2) unique + non-empty.
        $this->assertSame(array_values(array_unique($ids)), array_values($ids), 'Duplicate case ids found.');

        foreach ($ids as $id) {
            $this->assertNotSame('', trim($id));
            // (8) kebab-case ids.
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id, "Case id [{$id}] is not kebab-case.");
        }
    }

    public function test_every_case_is_english(): void
    {
        // (9) English-only corpus for now (deterministic baseline is English-keyword-based).
        foreach ($this->cases as $case) {
            $this->assertSame('en', $case->locale, "Case [{$case->id}] is not locale 'en'.");
        }
    }

    public function test_expected_intents_are_registered_or_unknown(): void
    {
        $registered = $this->grammar->intentNames();

        foreach ($this->cases as $case) {
            // (3) expected intent is a registered intent or the `unknown` sentinel.
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
                // (4) unknown ⇒ empty expected entities.
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
                // (5) every expected entity key is allowed for that intent — catches lead_query,
                // forbidden runtime/identity keys, and typos at authoring time.
                $this->assertContains(
                    $key,
                    $allowed,
                    "Case [{$case->id}] uses entity key [{$key}] not allowed for intent [{$case->expectedIntent}].",
                );
            }
        }
    }

    public function test_corpus_covers_every_registered_intent_and_unknown(): void
    {
        $covered = array_unique(array_map(fn ($c) => $c->expectedIntent, $this->cases));

        // (7) at least one case per registered intent ...
        foreach ($this->grammar->intentNames() as $intent) {
            $this->assertContains($intent, $covered, "Corpus has no case for intent [{$intent}].");
        }

        // ... and at least one out-of-domain `unknown` case.
        $this->assertContains('unknown', $covered, 'Corpus has no unknown/out-of-domain case.');
    }
}
