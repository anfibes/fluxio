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

    // ── Phase 9F.2A: exportable grammar artifact ──────────────────────────────

    public function test_export_is_deterministic(): void
    {
        $this->assertSame($this->grammar->export(), $this->grammar->export());

        // A freshly built grammar over the same registry exports identically.
        $other = new InterpretationGrammar($this->app->make(IntentRegistry::class));
        $this->assertSame($this->grammar->export(), $other->export());
    }

    public function test_export_lists_registered_intents_in_registration_order(): void
    {
        $export = $this->grammar->export();

        $this->assertSame($this->grammar->intentNames(), $export['intents']);
        $this->assertNotContains('unknown', $export['intents']);
    }

    public function test_export_entity_keys_match_allowed_entity_keys(): void
    {
        $export = $this->grammar->export();

        foreach ($this->grammar->intentNames() as $intent) {
            $this->assertSame(
                $this->grammar->allowedEntityKeys($intent),
                $export['entity_keys_by_intent'][$intent],
                "Exported keys for [{$intent}] diverge from allowedEntityKeys().",
            );
        }
    }

    public function test_export_entity_keys_contain_only_sandbox_legal_query_keys(): void
    {
        $export = $this->grammar->export();
        $allKeys = array_merge(...array_values($export['entity_keys_by_intent']));

        foreach ($allKeys as $key) {
            if (str_ends_with($key, '_query')) {
                $this->assertTrue(
                    ProviderSandboxContract::allowsReferenceKey($key),
                    "Exported reference key [{$key}] is not sandbox-legal.",
                );
            }
        }

        $this->assertContains('lead_query', $export['entity_keys_by_intent']['schedule_call']);
        $this->assertNotContains('participant_query', $allKeys);
        $this->assertNotContains('user_query', $allKeys);
        $this->assertNotContains('scalar', $allKeys);
    }

    public function test_export_includes_universal_parser_and_allowed_reference_keys(): void
    {
        $export = $this->grammar->export();

        $this->assertSame(['date', 'time'], $export['universal_parser_keys']);
        $this->assertSame(['lead_query'], $export['allowed_reference_keys']);
        $this->assertSame(['intent', 'confidence', 'entities', 'notes'], $export['root_contract_fields']);
    }

    public function test_export_carries_a_stable_contract_identity(): void
    {
        $export = $this->grammar->export();

        $this->assertSame('interpretation.provider-grammar', $export['contract']);
        $this->assertSame(1, $export['version']);
    }

    public function test_export_contains_no_runtime_authority_surface(): void
    {
        $flat = json_encode($this->grammar->export());

        foreach ([
            'status', 'readiness', 'ambiguit', 'missing', 'execution', 'executed',
            'failure', 'selected_candidate', 'candidate', '_id',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                (string) $flat,
                "Exported artifact must not expose runtime authority surface [{$forbidden}].",
            );
        }
    }
}
