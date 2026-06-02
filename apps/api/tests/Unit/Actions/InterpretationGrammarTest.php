<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Interpretation\ProviderSandboxContract;
use Fluxio\Actions\Registry\IntentRegistry;
use Tests\TestCase;

/**
 * Phase 9F.1B — the runtime-owned interpretation grammar descriptor.
 *
 * Pins the descriptor as a faithful, sandbox-narrowed projection of IntentRegistry:
 * it preserves the Phase 9F.1A allowed-key rules and never advertises a key the
 * frozen ProviderSandboxContract would reject.
 */
class InterpretationGrammarTest extends TestCase
{
    private InterpretationGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new InterpretationGrammar($this->app->make(IntentRegistry::class));
    }

    // ── Intent vocabulary projection ──────────────────────────────────────────

    public function test_intent_names_match_the_registry_and_exclude_unknown(): void
    {
        $registry = $this->app->make(IntentRegistry::class);

        $this->assertSame(array_keys($registry->all()), $this->grammar->intentNames());
        $this->assertNotContains('unknown', $this->grammar->intentNames());
    }

    public function test_has_intent_tracks_registration(): void
    {
        $this->assertTrue($this->grammar->hasIntent('create_task'));
        $this->assertFalse($this->grammar->hasIntent('unknown'));
        $this->assertFalse($this->grammar->hasIntent('delete_everything'));
    }

    public function test_unknown_intent_has_no_allowed_keys(): void
    {
        $this->assertSame([], $this->grammar->allowedEntityKeys('unknown'));
        $this->assertSame([], $this->grammar->allowedEntityKeys('not_registered'));
    }

    public function test_universal_parser_keys_are_date_and_time(): void
    {
        $this->assertSame(['date', 'time'], $this->grammar->universalParserKeys());
    }

    // ── Allowed-key derivation rules (Phase 9F.1A, now owned here) ─────────────

    public function test_allowed_keys_include_canonical_keys_and_universal_parser_keys(): void
    {
        $keys = $this->grammar->allowedEntityKeys('create_task');

        // Canonical requirement keys + universal parser keys + sandbox-legal entityType.
        $this->assertContains('lead', $keys);
        $this->assertContains('priority', $keys);
        $this->assertContains('due_at', $keys);
        $this->assertContains('date', $keys);
        $this->assertContains('time', $keys);
        $this->assertContains('lead_query', $keys);
        $this->assertContains('date_expression', $keys);
    }

    public function test_lead_query_is_preserved_but_participant_and_user_query_are_dropped(): void
    {
        $scheduleCall = $this->grammar->allowedEntityKeys('schedule_call');
        $this->assertContains('lead_query', $scheduleCall);
        $this->assertNotContains('participant_query', $scheduleCall);

        $assignLead = $this->grammar->allowedEntityKeys('assign_lead');
        $this->assertNotContains('user_query', $assignLead);
    }

    public function test_scalar_marker_is_never_advertised(): void
    {
        foreach ($this->grammar->entityKeysByIntent() as $keys) {
            $this->assertNotContains('scalar', $keys);
        }
    }

    // ── Sandbox parity ────────────────────────────────────────────────────────

    public function test_descriptor_surface_is_sandbox_legal_for_every_intent(): void
    {
        $sandbox = new ProviderSandboxContract;

        foreach ($this->grammar->entityKeysByIntent() as $intent => $keys) {
            // The sandbox inspects keys only; any non-empty value suffices.
            $violations = $sandbox->violations(new NormalizedCommand(
                intent: $intent,
                confidence: 0.8,
                sourceText: 'test',
                locale: 'en',
                entities: array_fill_keys($keys, 'x'),
            ));

            $this->assertSame([], $violations, "Intent [{$intent}] advertises a non-sandbox-legal key.");
        }
    }

    public function test_no_intent_advertises_a_sandbox_forbidden_query_key(): void
    {
        foreach ($this->grammar->entityKeysByIntent() as $intent => $keys) {
            foreach ($keys as $key) {
                if (str_ends_with($key, '_query')) {
                    $this->assertTrue(
                        ProviderSandboxContract::allowsReferenceKey($key),
                        "Intent [{$intent}] advertises forbidden reference key [{$key}].",
                    );
                }
            }
        }
    }
}
