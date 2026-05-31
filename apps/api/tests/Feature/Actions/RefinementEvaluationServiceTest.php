<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementCorpusCase;
use Fluxio\Actions\Diagnostics\Refinement\RefinementCorpusLoader;
use Fluxio\Actions\Diagnostics\Refinement\RefinementEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8A: the refinement evaluator replays the real proposal pipeline and
 * compares structured refinement behavior against expectations. The shipped
 * corpus is an executable spec of current behavior and must pass; a deliberately
 * wrong expectation must be detected as a mismatch.
 */
class RefinementEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Stable run-relative dates; "tomorrow"/"Friday" resolve deterministically.
        Carbon::setTestNow('2026-05-30 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function service(): RefinementEvaluationService
    {
        return app(RefinementEvaluationService::class);
    }

    public function test_shipped_corpus_all_pass(): void
    {
        $user = User::factory()->create();
        $cases = (new RefinementCorpusLoader)->load(RefinementCorpusLoader::defaultCorpusPath());

        $summary = $this->service()->evaluate($cases, $user);

        // Build a readable failure message if anything regressed.
        $failed = array_filter($summary->cases, fn ($c) => ! $c->passed);
        $detail = implode("\n", array_map(
            fn ($c) => "{$c->id}: ".implode('; ', $c->failures),
            $failed,
        ));

        $this->assertTrue($summary->allPassed(), "Corpus cases failed:\n{$detail}");
        $this->assertSame($summary->total, $summary->passedCount);
    }

    public function test_evaluator_detects_a_mismatch(): void
    {
        $user = User::factory()->create();

        // "At 11:00" is a replace_time, but we deliberately expect shift_time.
        $case = new RefinementCorpusCase(
            id: 'wrong-expectation',
            description: 'deliberately wrong',
            initialCommand: 'Schedule a meeting with Rossini tomorrow at 10',
            refinementText: 'At 11:00',
            expectedSemanticTypes: ['shift_time'],
            expectedChangedFields: ['time'],
        );

        $summary = $this->service()->evaluate([$case], $user);

        $this->assertFalse($summary->allPassed());
        $this->assertSame(1, $summary->failedCount);

        $result = $summary->cases[0];
        $this->assertFalse($result->passed);
        $this->assertNotEmpty($result->failures);
        // The actual snapshot shows the real semantic type for debugging.
        $this->assertContains('replace_time', $result->actual['semantic_types']);
    }

    public function test_evaluator_detects_passing_case(): void
    {
        $user = User::factory()->create();

        $case = new RefinementCorpusCase(
            id: 'correct-expectation',
            description: 'replace time',
            initialCommand: 'Schedule a meeting with Rossini tomorrow at 10',
            refinementText: 'At 11:00',
            expectedSemanticTypes: ['replace_time'],
            expectedChangedFields: ['time'],
            expectedStatus: 'ready',
        );

        $summary = $this->service()->evaluate([$case], $user);

        $this->assertTrue($summary->allPassed());
        $this->assertSame(1, $summary->passedCount);
        $this->assertSame([], $summary->cases[0]->failures);
    }
}
