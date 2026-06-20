<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Phase 9D.2 — feature tests for the command-interpretation corpus evaluation
 * command. It replays the real (DB-free) interpretation pipeline, must persist
 * nothing, must be blocked in production, and reports a concise summary + metrics.
 * No LLM, no HTTP, deterministic baseline only.
 */
class EvaluateCommandInterpretationCorpusCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function writeCorpus(array $cases): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cmdcorpus_').'.json';
        file_put_contents($path, json_encode($cases));
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_command_outputs_json_and_passes_on_shipped_corpus(): void
    {
        $exitCode = Artisan::call('actions:evaluate-command-interpretation-corpus', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertGreaterThan(0, $decoded['total']);
        $this->assertSame($decoded['total'], $decoded['passed_count'], 'Shipped corpus should fully pass: '.$output);
        $this->assertSame(0, $decoded['failed_count']);
        $this->assertCount($decoded['total'], $decoded['cases']);

        // Per-dimension metrics are present.
        $this->assertArrayHasKey('intent', $decoded['metrics']);
        $this->assertArrayHasKey('ambiguity', $decoded['metrics']);
    }

    public function test_command_persists_nothing(): void
    {
        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:evaluate-command-interpretation-corpus', ['--json' => true])
            ->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:evaluate-command-interpretation-corpus')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_fail_on_mismatch_returns_nonzero(): void
    {
        $path = $this->writeCorpus([[
            'id' => 'wrong',
            'text' => 'Create a follow-up task for Mario Rossi tomorrow',
            'expected' => ['intent' => 'schedule_call'],
        ]]);

        $exitCode = Artisan::call('actions:evaluate-command-interpretation-corpus', [
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
            'text' => 'Create a follow-up task for Mario Rossi tomorrow',
            'expected' => ['intent' => 'schedule_call'],
        ]]);

        // Mismatch is diagnostic data, not a failure, unless gating is opted in.
        $exitCode = Artisan::call('actions:evaluate-command-interpretation-corpus', [
            '--path' => $path,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
