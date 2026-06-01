<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\ProviderSandboxContract;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9F.0 — the frozen provider sandbox surface. These guardrails fail loudly if a
 * future provider experiment accidentally expands the allowed surface (e.g. adds a new
 * resolver-backed reference type the runtime does not yet honor, or lets a provider
 * leak a runtime/identity surface).
 */
class ProviderSandboxContractTest extends TestCase
{
    private ProviderSandboxContract $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = new ProviderSandboxContract;
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function command(array $entities, string $intent = 'schedule_meeting'): NormalizedCommand
    {
        return new NormalizedCommand(
            intent: $intent,
            confidence: 0.8,
            sourceText: 'test',
            locale: 'en',
            entities: $entities,
        );
    }

    // ── Allowed surface ───────────────────────────────────────────────────────

    public function test_lead_query_is_the_only_legal_reference(): void
    {
        $this->assertSame([], $this->sandbox->violations($this->command(['lead_query' => 'Mario Rossi'])));
    }

    public function test_allowed_scalar_values_and_raw_expressions_pass(): void
    {
        $violations = $this->sandbox->violations($this->command([
            'lead_query' => 'Rossi SRL',
            'lead' => 'Rossi SRL',
            'date' => '2026-06-02',
            'time' => '10:00',
            'date_expression' => 'tomorrow',
            'time_expression' => 'morning-ish',
            'priority' => 'high',
            'due_at' => '2026-06-02',
            'assignee' => 'Marco',
            'location' => 'HQ',
            'quote' => 'Q-1',
            'participants' => 'Marco',
        ]));

        $this->assertSame([], $violations);
    }

    // ── Forbidden resolver-backed references ──────────────────────────────────

    public function test_participant_query_is_rejected(): void
    {
        $violations = $this->sandbox->violations($this->command(['participant_query' => 'Marco']));

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('participant_query', $violations[0]);
        $this->assertStringContainsString('lead_query', $violations[0]);
    }

    public function test_user_query_is_rejected(): void
    {
        $this->assertNotEmpty($this->sandbox->violations($this->command(['user_query' => 'Marco'])));
    }

    public function test_arbitrary_non_lead_query_reference_is_rejected(): void
    {
        $this->assertNotEmpty($this->sandbox->violations($this->command(['product_query' => 'Widget'])));
    }

    // ── Forbidden identity / lifecycle / runtime surface ──────────────────────

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenKeyProvider(): iterable
    {
        yield 'resolved id' => ['lead_id'];
        yield 'selected candidate id' => ['selected_candidate_id'];
        yield 'candidates' => ['candidates'];
        yield 'status' => ['status'];
        yield 'ambiguities' => ['ambiguities'];
        yield 'missing' => ['missing'];
        yield 'readiness' => ['readiness'];
        yield 'execution result' => ['execution_result'];
        yield 'failure reason' => ['failure_reason'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenKeyProvider')]
    public function test_forbidden_runtime_identity_surface_is_rejected(string $key): void
    {
        $this->assertNotEmpty(
            $this->sandbox->violations($this->command([$key => 'x'])),
            "expected [{$key}] to be a forbidden provider surface",
        );
    }

    public function test_forbidden_surface_is_rejected_even_for_unknown_intent(): void
    {
        // NormalizedCommandValidator skips key-compatibility for `unknown`; the sandbox
        // must still reject an identity surface there — closing that gap.
        $violations = $this->sandbox->violations($this->command(['selected_candidate_id' => 7], 'unknown'));

        $this->assertNotEmpty($violations);
    }

    // ── Freeze guardrail ──────────────────────────────────────────────────────

    public function test_allowed_reference_keys_are_frozen_to_lead_query_only(): void
    {
        // Expanding this set requires generalizing resolveEntityQuery first.
        $this->assertSame(['lead_query'], ProviderSandboxContract::ALLOWED_REFERENCE_KEYS);
    }
}
