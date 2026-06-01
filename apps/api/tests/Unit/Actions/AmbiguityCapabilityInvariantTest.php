<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\EntityRequirement;
use Fluxio\Actions\DTO\IntentCapability;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\DTO\MutationCapability;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\Enums\RefinementCapabilityType;
use Fluxio\Actions\Registry\IntentCapabilityRegistry;
use Fluxio\Actions\Registry\IntentRegistry;
use Tests\TestCase;

/**
 * Phase 9C.2 — Runtime capability guardrail.
 *
 * Architectural invariant (the lesson from Phase 9C.1, generalized):
 *
 *   If an intent can generate a blocking ambiguity through a resolver-backed
 *   entity, that intent MUST support ambiguity resolution AND expose the
 *   ResolveAmbiguity refinement capability.
 *
 * Otherwise a proposal can become structurally blocked yet operationally
 * unresolvable (draft → blocking ambiguity → no resolution capability → never
 * confirmable).
 *
 * The "can emit a blocking ambiguity" signal is derived from the REAL runtime path,
 * not a hand-maintained allowlist:
 *   - the intent declares an EntityRequirement with resolverRequired === true
 *     (ActionInterpreterService pre-resolves such entities and emits a blocking
 *     ambiguity on multi-match), AND
 *   - the live EntityResolverRegistry actually has a resolver for that entityType
 *     (so resolution can run and produce candidates).
 *
 * Both registries are the ones wired by ActionsServiceProvider, so this fails
 * automatically if a future intent introduces resolver-backed ambiguity generation
 * but forgets the resolution capability.
 */
class AmbiguityCapabilityInvariantTest extends TestCase
{
    private IntentRegistry $intents;

    private IntentCapabilityRegistry $capabilities;

    private EntityResolverRegistry $resolvers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->intents = $this->app->make(IntentRegistry::class);
        $this->capabilities = $this->app->make(IntentCapabilityRegistry::class);
        $this->resolvers = $this->app->make(EntityResolverRegistry::class);
    }

    /**
     * Real runtime signal: does this intent declare a resolver-backed entity for
     * which a resolver is actually registered (and can therefore emit a blocking
     * ambiguity on a multi-candidate match)?
     */
    private function canEmitResolverBackedBlockingAmbiguity(IntentDefinition $definition): bool
    {
        foreach ($definition->requirements as $req) {
            if ($req->resolverRequired && $this->resolvers->supports($req->entityType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pure predicate: the invariant violations for one intent (empty = compliant).
     * Intents that cannot emit a resolver-backed blocking ambiguity are never forced
     * to support resolution, so they have no violations.
     *
     * @return list<string>
     */
    private function invariantViolations(IntentDefinition $definition, ?IntentCapability $capability): array
    {
        if (! $this->canEmitResolverBackedBlockingAmbiguity($definition)) {
            return [];
        }

        if ($capability === null) {
            return ["intent [{$definition->intent}] can emit a blocking ambiguity but has no capability declaration"];
        }

        $violations = [];

        if ($capability->supportsAmbiguityResolution !== true) {
            $violations[] = "intent [{$definition->intent}] can emit a blocking ambiguity but supportsAmbiguityResolution is not true";
        }

        if (! in_array(RefinementCapabilityType::ResolveAmbiguity, $capability->refinements, true)) {
            $violations[] = "intent [{$definition->intent}] can emit a blocking ambiguity but does not expose the ResolveAmbiguity refinement";
        }

        return $violations;
    }

    // ── 1. Real intents satisfy the invariant ─────────────────────────────────

    public function test_every_ambiguity_generating_intent_supports_resolution(): void
    {
        $checked = [];

        foreach ($this->intents->all() as $definition) {
            if (! $this->canEmitResolverBackedBlockingAmbiguity($definition)) {
                continue;
            }

            $checked[] = $definition->intent;

            $this->assertSame(
                [],
                $this->invariantViolations($definition, $this->capabilities->find($definition->intent)),
                "Ambiguity/resolution capability invariant violated for intent [{$definition->intent}].",
            );
        }

        // The guardrail must not be vacuous: at least one real intent emits resolver-backed
        // ambiguities, and create_task (the Phase 9C.1 lesson) is one of them.
        $this->assertNotEmpty($checked, 'Expected at least one resolver-backed ambiguity-generating intent.');
        $this->assertContains('create_task', $checked);
    }

    // ── 2. The guardrail actually fires (non-vacuous) ─────────────────────────

    public function test_guardrail_detects_missing_ambiguity_resolution_support(): void
    {
        // A future intent: resolver-backed lead, but capability forgets resolution support.
        $definition = $this->resolverBackedDefinition('archive_lead');
        $capability = new IntentCapability(
            intent: 'archive_lead',
            mutations: [new MutationCapability(operation: 'replace', fields: ['lead'], collection: false)],
            refinements: [RefinementCapabilityType::ReplaceField],
            supportsContextualReferences: false,
            supportsAmbiguityResolution: false,
            supportsCollectionMutations: false,
        );

        $violations = $this->invariantViolations($definition, $capability);

        $this->assertNotEmpty($violations, 'Guardrail must flag a resolver-backed intent that lacks ambiguity resolution.');
        $this->assertStringContainsString('supportsAmbiguityResolution', implode(' | ', $violations));
        $this->assertStringContainsString('ResolveAmbiguity', implode(' | ', $violations));
    }

    public function test_guardrail_detects_missing_resolve_ambiguity_refinement(): void
    {
        // Supports the flag but forgets the refinement capability entry.
        $definition = $this->resolverBackedDefinition('archive_lead');
        $capability = new IntentCapability(
            intent: 'archive_lead',
            mutations: [new MutationCapability(operation: 'replace', fields: ['lead'], collection: false)],
            refinements: [RefinementCapabilityType::ReplaceField],
            supportsContextualReferences: false,
            supportsAmbiguityResolution: true,
            supportsCollectionMutations: false,
        );

        $violations = $this->invariantViolations($definition, $capability);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('ResolveAmbiguity', $violations[0]);
    }

    public function test_guardrail_detects_missing_capability_declaration(): void
    {
        $definition = $this->resolverBackedDefinition('archive_lead');

        $violations = $this->invariantViolations($definition, null);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('no capability declaration', $violations[0]);
    }

    // ── 3. Intents without resolver-backed entities are not forced ────────────

    public function test_intent_without_resolver_backed_entity_is_not_required_to_support_resolution(): void
    {
        // Only scalar requirements → no resolver → cannot emit a blocking ambiguity.
        $definition = new IntentDefinition(
            intent: 'set_reminder_note',
            label: 'Set reminder note',
            module: 'tasks',
            operation: 'create',
            requirements: [
                new EntityRequirement(key: 'note', entityType: 'scalar', label: 'Note', required: true),
            ],
            executorClass: 'Stub',
            confidence: 0.9,
        );

        $this->assertFalse($this->canEmitResolverBackedBlockingAmbiguity($definition));

        // A capability that does NOT support ambiguity resolution is perfectly valid here.
        $capability = new IntentCapability(
            intent: 'set_reminder_note',
            mutations: [new MutationCapability(operation: 'replace', fields: ['note'], collection: false)],
            refinements: [RefinementCapabilityType::ReplaceField],
            supportsContextualReferences: false,
            supportsAmbiguityResolution: false,
            supportsCollectionMutations: false,
        );

        $this->assertSame([], $this->invariantViolations($definition, $capability));
    }

    // ── 4. No unrelated capabilities implicitly broadened ─────────────────────

    public function test_ambiguity_capable_intents_do_not_implicitly_broaden_capabilities(): void
    {
        // create_task supports ambiguity resolution (Phase 9C.1) but must NOT have had
        // collection mutations or contextual references switched on as a side effect.
        $createTask = $this->capabilities->find('create_task');
        $this->assertNotNull($createTask);
        $this->assertTrue($createTask->supportsAmbiguityResolution);
        $this->assertFalse($createTask->supportsCollectionMutations);
        $this->assertFalse($createTask->supportsContextualReferences);
    }

    private function resolverBackedDefinition(string $intent): IntentDefinition
    {
        return new IntentDefinition(
            intent: $intent,
            label: 'Archive lead',
            module: 'leads',
            operation: 'archive',
            requirements: [
                // resolver-backed (the live registry supports 'lead_query')
                new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true),
            ],
            executorClass: 'Stub',
            confidence: 0.9,
        );
    }
}
