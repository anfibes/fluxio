<?php

namespace Tests\Feature\Actions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 9E.4 — the deterministic interpretation fidelity baseline gate.
 *
 * Mirrors the `composer test:actions-corpora` script: the proposal-level command
 * interpretation corpus and the refinement corpus must both pass with
 * --fail-on-mismatch. Both are deterministic-only (no model, no network), so they
 * are CI-safe. The provider-level interpretation corpus (deterministic vs. Ollama)
 * is deliberately NOT part of the mandatory gate — it depends on Ollama.
 */
class ActionsCorpusGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_interpretation_corpus_gate_passes(): void
    {
        $this->assertSame(
            0,
            Artisan::call('actions:evaluate-command-interpretation-corpus', ['--fail-on-mismatch' => true]),
        );
    }

    public function test_refinement_corpus_gate_passes(): void
    {
        $this->assertSame(
            0,
            Artisan::call('actions:evaluate-refinement-corpus', ['--fail-on-mismatch' => true]),
        );
    }
}
