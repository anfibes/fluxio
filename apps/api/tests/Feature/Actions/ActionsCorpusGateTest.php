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

    /**
     * Phase 9F.1E — the CI/diagnostics boundary guard. The mandatory gate
     * (composer test:actions-corpora) must run ONLY the deterministic, network-free
     * corpora. The opt-in provider diagnostics (deterministic vs. Ollama, which can
     * contact a model) must never be wired into the gate. This fails loudly if a
     * contributor adds them or removes a deterministic corpus.
     */
    public function test_gate_script_contains_only_deterministic_corpora(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $gate = implode("\n", (array) ($composer['scripts']['test:actions-corpora'] ?? []));

        // Mandatory deterministic, network-free gates.
        $this->assertStringContainsString('actions:evaluate-command-interpretation-corpus', $gate);
        $this->assertStringContainsString('actions:evaluate-refinement-corpus', $gate);
        $this->assertStringContainsString('--fail-on-mismatch', $gate);

        // Opt-in provider diagnostics must stay OUT of the mandatory gate.
        $this->assertStringNotContainsString('actions:evaluate-interpretation-corpus', $gate);
        $this->assertStringNotContainsString('actions:compare-interpretation', $gate);
    }
}
