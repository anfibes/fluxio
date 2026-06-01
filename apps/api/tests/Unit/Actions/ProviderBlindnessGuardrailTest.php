<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\Ambiguity\AmbiguityDirective;
use Fluxio\Actions\DTO\Execution\ExecutionFailure;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\RefinementAmbiguityOutcome;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Phase 8D.4 provider-blindness invariant (hardened).
 *
 * After lowering, the runtime authorities consume only structural inputs —
 * NormalizedMutation (field state) and AmbiguityDirective (ambiguity state). Those
 * inputs must carry NO provider-identifying surface (provider, provenance,
 * confidence, trust, model, prompt) so that no probabilistic provider can ever leak
 * provider identity, provenance, or provider trust across the lowering boundary.
 *
 * Two guarantees are checked: the DTO surfaces carry no such fields/accessors, and
 * the lowerer never copies the semantic mutation's opaque `metadata` into the
 * NormalizedMutation (it builds only deterministic runtime metadata).
 */
class ProviderBlindnessGuardrailTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN_SURFACE = ['provider', 'provenance', 'confidence', 'trust', 'model', 'prompt'];

    /**
     * @return list<string>
     */
    private function surfaceNames(string $class): array
    {
        $reflection = new ReflectionClass($class);

        $names = [];
        foreach ($reflection->getProperties() as $property) {
            $names[] = $property->getName();
        }
        foreach ($reflection->getMethods() as $method) {
            $names[] = $method->getName();
        }
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $names[] = $parameter->getName();
            }
        }

        return array_values(array_unique($names));
    }

    private function assertNoSurfaceMatching(string $class, array $forbidden): void
    {
        foreach ($this->surfaceNames($class) as $name) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $needle,
                    $name,
                    "{$class} exposes a [{$name}] surface containing forbidden token [{$needle}]; authorities must stay provider-blind.",
                );
            }
        }
    }

    public function test_normalized_mutation_has_no_provider_surface(): void
    {
        $this->assertNoSurfaceMatching(NormalizedMutation::class, self::FORBIDDEN_SURFACE);
    }

    public function test_ambiguity_directive_has_no_provider_surface(): void
    {
        $this->assertNoSurfaceMatching(AmbiguityDirective::class, self::FORBIDDEN_SURFACE);
    }

    public function test_refinement_ambiguity_outcome_has_no_provider_surface(): void
    {
        // Phase 8E.1: the ambiguity outcome facet is response-bound reporting metadata;
        // it names a deterministic state transition, never who produced it.
        $this->assertNoSurfaceMatching(RefinementAmbiguityOutcome::class, self::FORBIDDEN_SURFACE);
    }

    public function test_refinement_ambiguity_outcome_payload_carries_no_candidate_identity(): void
    {
        // The narrowed facet reports counts only — never the candidate array nor any
        // selected candidate id (identity stays authoritative under proposal->ambiguities).
        $payload = RefinementAmbiguityOutcome::narrowed('lead', 'Lead', 3, 2)->toArray();

        $this->assertSame(
            ['kind', 'ambiguity_key', 'label', 'from_count', 'to_count'],
            array_keys($payload),
        );
        $this->assertArrayNotHasKey('candidates', $payload);
        $this->assertArrayNotHasKey('selected_candidate_id', $payload);
    }

    public function test_execution_result_has_no_provider_surface(): void
    {
        // Execution Runtime v1: the execution authority's result is inert,
        // response-bound display data — it names what happened, never who produced
        // the interpretation.
        $this->assertNoSurfaceMatching(ExecutionResult::class, self::FORBIDDEN_SURFACE);
    }

    public function test_execution_failure_has_no_provider_surface(): void
    {
        $this->assertNoSurfaceMatching(ExecutionFailure::class, self::FORBIDDEN_SURFACE);
    }

    public function test_ambiguity_directive_surface_is_only_key_and_selector(): void
    {
        $reflection = new ReflectionClass(AmbiguityDirective::class);
        $constructor = $reflection->getConstructor();
        $params = array_map(fn ($p) => $p->getName(), $constructor->getParameters());

        $this->assertSame(['ambiguityKey', 'selector'], $params);
    }

    // ── Lowering does not carry provider metadata across the boundary ────────

    public function test_scalar_replace_lowering_drops_provider_metadata(): void
    {
        $lowered = (new SemanticRefinementLowerer)->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceTime,
            payload: ['value' => '11:00'],
            metadata: ['provider' => 'llm', 'confidence' => 0.7, 'provenance' => ['x' => 'y']],
        ));

        // Scalar replace needs no runtime metadata at all.
        $this->assertSame([], $lowered->metadata);
    }

    public function test_shift_time_lowering_keeps_only_deterministic_metadata(): void
    {
        $lowered = (new SemanticRefinementLowerer)->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later'],
            metadata: ['provider' => 'llm', 'confidence' => 0.7, 'model' => 'x', 'prompt_version' => '3'],
        ));

        // Only the deterministic runtime keys the lowerer builds — nothing provider.
        $this->assertSame(
            ['contextual_operation', 'unit', 'amount', 'direction'],
            array_keys($lowered->metadata),
        );
        foreach (self::FORBIDDEN_SURFACE as $needle) {
            $this->assertArrayNotHasKey($needle, $lowered->metadata);
        }
    }

    public function test_participant_lowering_drops_provider_metadata(): void
    {
        $lowered = (new SemanticRefinementLowerer)->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::AddParticipant,
            payload: ['value' => 'Marco'],
            metadata: ['provider' => 'llm', 'trust' => 'low'],
        ));

        $this->assertSame([], $lowered->metadata);
    }
}
