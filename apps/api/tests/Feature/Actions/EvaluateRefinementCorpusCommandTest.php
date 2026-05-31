<?php

namespace Tests\Feature\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 8A: feature tests for the refinement corpus evaluation command.
 *
 * The command replays the real pipeline but must persist nothing (the run is
 * wrapped in a rolled-back transaction), must be blocked in production, and must
 * report a concise summary. No LLM, no HTTP.
 */
class EvaluateRefinementCorpusCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-30 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function writeCorpus(array $cases): string
    {
        $path = tempnam(sys_get_temp_dir(), 'refcorpus_').'.json';
        file_put_contents($path, json_encode($cases));
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_command_outputs_json_and_passes_on_shipped_corpus(): void
    {
        $exitCode = Artisan::call('actions:evaluate-refinement-corpus', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertGreaterThan(0, $decoded['total']);
        $this->assertSame($decoded['total'], $decoded['passed_count'], 'Shipped corpus should fully pass: '.$output);
        $this->assertSame(0, $decoded['failed_count']);
        $this->assertCount($decoded['total'], $decoded['cases']);
    }

    public function test_command_persists_nothing(): void
    {
        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:evaluate-refinement-corpus', ['--json' => true])
            ->assertSuccessful();

        // The run is wrapped in a rolled-back transaction.
        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:evaluate-refinement-corpus')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_fail_on_mismatch_returns_nonzero(): void
    {
        // A single case with a deliberately wrong expectation.
        $path = $this->writeCorpus([[
            'id' => 'wrong',
            'initial_command' => 'Schedule a meeting with Rossini tomorrow at 10',
            'refinement_text' => 'At 11:00',
            'expected' => ['semantic_types' => ['shift_time']],
        ]]);

        $exitCode = Artisan::call('actions:evaluate-refinement-corpus', [
            '--path' => $path,
            '--json' => true,
            '--fail-on-mismatch' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $decoded = json_decode(Artisan::output(), true);
        $this->assertSame(1, $decoded['failed_count']);
    }

    public function test_without_fail_flag_mismatch_still_exits_zero(): void
    {
        $path = $this->writeCorpus([[
            'id' => 'wrong',
            'initial_command' => 'Schedule a meeting with Rossini tomorrow at 10',
            'refinement_text' => 'At 11:00',
            'expected' => ['semantic_types' => ['shift_time']],
        ]]);

        // Mismatch is diagnostic data, not a failure, unless gating is opted in.
        $exitCode = Artisan::call('actions:evaluate-refinement-corpus', [
            '--path' => $path,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
