<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\EntityReference;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\DTO\ParsedIntent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Phase 9E.1 — command-path boundary guardrail.
 *
 * The interpretation-side DTOs (ParsedIntent, NormalizedCommand) describe WHAT the
 * user said, never the runtime CONSEQUENCES. They must therefore expose no
 * lifecycle/authority surface: no resolved entity ids / selected candidate, no
 * proposal status, missing fields, ambiguities, readiness, or execution data. Those
 * are produced downstream by EntityResolverRegistry (identity) and
 * ActionInterpreterService (status/missing/ambiguity/lifecycle).
 *
 * This keeps the boundary from drifting as future providers evolve: a provider that
 * tried to emit a resolved id or a proposal status would have to add such a surface
 * to these DTOs, which this test forbids. (Interpretation-side metadata — confidence,
 * warnings, locale, source — is allowed and is intentionally NOT in the list.)
 */
class InterpretationContractGuardrailTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN_SURFACE = [
        'status',
        'ambiguit',
        'missing',
        'selected_candidate',
        'candidate',
        'lead_id',
        'readiness',
        'execution',
        'executed',
        'failure',
    ];

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

    private function assertNoForbiddenSurface(string $class): void
    {
        foreach ($this->surfaceNames($class) as $name) {
            foreach (self::FORBIDDEN_SURFACE as $needle) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $needle,
                    $name,
                    "{$class} exposes a [{$name}] surface containing forbidden token [{$needle}]; interpretation-side DTOs must carry no lifecycle/authority surface.",
                );
            }
        }
    }

    public function test_parsed_intent_has_no_lifecycle_surface(): void
    {
        $this->assertNoForbiddenSurface(ParsedIntent::class);
    }

    public function test_normalized_command_has_no_lifecycle_surface(): void
    {
        $this->assertNoForbiddenSurface(NormalizedCommand::class);
    }

    public function test_parsed_intent_surface_is_interpretation_side_only(): void
    {
        $reflection = new ReflectionClass(ParsedIntent::class);
        $params = array_map(
            static fn ($p) => $p->getName(),
            $reflection->getConstructor()->getParameters(),
        );

        // Exactly the interpretation-side fields: meaning + raw references + notes.
        $this->assertSame(['intent', 'entities', 'warnings'], $params);
    }

    public function test_entity_reference_has_no_lifecycle_or_identity_surface(): void
    {
        $this->assertNoForbiddenSurface(EntityReference::class);
    }

    public function test_entity_reference_is_exactly_key_and_value(): void
    {
        // An EntityReference is the reference span only — no id, no candidates, no
        // selected_candidate_id, no resolved-label authority, no ambiguity/lifecycle.
        $reflection = new ReflectionClass(EntityReference::class);
        $params = array_map(
            static fn ($p) => $p->getName(),
            $reflection->getConstructor()->getParameters(),
        );

        $this->assertSame(['key', 'value'], $params);

        $properties = array_map(
            static fn ($p) => $p->getName(),
            $reflection->getProperties(),
        );
        sort($properties);
        $this->assertSame(['key', 'value'], $properties);
    }
}
