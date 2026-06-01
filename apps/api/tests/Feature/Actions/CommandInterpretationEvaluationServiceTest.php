<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Diagnostics\CommandInterpretation\CommandInterpretationCorpusLoader;
use Fluxio\Actions\Diagnostics\CommandInterpretation\CommandInterpretationEvaluationService;
use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationCorpusCase;
use Tests\TestCase;

/**
 * Phase 9D.2 — the evaluator replays the real deterministic interpretation
 * pipeline and reports proposal-level fidelity. Diagnostics-only; DB-free.
 */
class CommandInterpretationEvaluationServiceTest extends TestCase
{
    private function service(): CommandInterpretationEvaluationService
    {
        return $this->app->make(CommandInterpretationEvaluationService::class);
    }

    public function test_shipped_corpus_fully_passes_against_deterministic_baseline(): void
    {
        $cases = (new CommandInterpretationCorpusLoader)->load(CommandInterpretationCorpusLoader::defaultCorpusPath());

        $summary = $this->service()->evaluate($cases);

        $this->assertGreaterThan(0, $summary->total);
        $this->assertSame($summary->total, $summary->passedCount, $this->describeFailures($summary));
        $this->assertSame(0, $summary->failedCount);
        $this->assertTrue($summary->allPassed());
    }

    public function test_metrics_track_per_dimension_accuracy(): void
    {
        $cases = (new CommandInterpretationCorpusLoader)->load(CommandInterpretationCorpusLoader::defaultCorpusPath());

        $summary = $this->service()->evaluate($cases);

        foreach (['intent', 'status', 'entity', 'ambiguity'] as $dimension) {
            $this->assertGreaterThan(0, $summary->metrics[$dimension]['checked'], "dimension [{$dimension}] should be exercised");
            $this->assertEquals(1.0, $summary->accuracy($dimension));
        }
    }

    public function test_full_person_span_auto_resolves_without_ambiguity(): void
    {
        $case = new CommandInterpretationCorpusCase(
            id: 'mario',
            description: '',
            text: 'Create a high priority follow-up task for Mario Rossi tomorrow at 10',
            expectedIntent: 'create_task',
            expectedStatus: 'ready',
            expectedLead: 'Mario Rossi',
            expectedEntities: ['time' => '10:00'],
            expectedEntitiesPresent: ['date'],
            expectNoLeadAmbiguity: true,
        );

        $summary = $this->service()->evaluate([$case]);

        $this->assertTrue($summary->allPassed(), $this->describeFailures($summary));
    }

    public function test_short_lead_query_still_reports_blocking_ambiguity(): void
    {
        $case = new CommandInterpretationCorpusCase(
            id: 'rossi',
            description: '',
            text: 'Schedule a call with Rossi tomorrow at 10',
            expectedIntent: 'schedule_call',
            expectedStatus: 'draft',
            expectedAmbiguity: [
                'key' => 'lead',
                'blocking' => true,
                'query' => 'Rossi',
                'candidate_labels' => ['Rossi SRL', 'Mario Rossi', 'Studio Rossi'],
            ],
        );

        $summary = $this->service()->evaluate([$case]);

        $this->assertTrue($summary->allPassed(), $this->describeFailures($summary));
    }

    public function test_mismatched_expectation_is_reported_as_failure(): void
    {
        // Deliberately wrong: the full "Mario Rossi" span auto-resolves, so asserting
        // a blocking ambiguity must fail (and intent is wrong too).
        $case = new CommandInterpretationCorpusCase(
            id: 'bad',
            description: '',
            text: 'Create a follow-up task for Mario Rossi tomorrow',
            expectedIntent: 'schedule_call',
            expectedAmbiguity: ['key' => 'lead', 'blocking' => true],
        );

        $summary = $this->service()->evaluate([$case]);

        $this->assertFalse($summary->allPassed());
        $this->assertSame(1, $summary->failedCount);
        $this->assertNotEmpty($summary->cases[0]->failures);
        $this->assertFalse($summary->metrics['ambiguity']['matched'] === $summary->metrics['ambiguity']['checked']);
    }

    private function describeFailures($summary): string
    {
        $lines = [];
        foreach ($summary->cases as $case) {
            if (! $case->passed) {
                $lines[] = $case->id.': '.implode('; ', $case->failures);
            }
        }

        return implode("\n", $lines);
    }
}
