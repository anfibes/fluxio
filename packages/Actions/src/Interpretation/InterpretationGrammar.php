<?php

namespace Fluxio\Actions\Interpretation;

use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\Registry\IntentRegistry;

/**
 * Phase 9F.1B — the runtime-owned interpretation grammar descriptor.
 *
 * A small, read-only PROJECTION of facts the runtime already owns: the registered
 * intents (IntentRegistry) and the per-intent entity-key vocabulary the provider
 * surface may use, narrowed to the frozen ProviderSandboxContract. It is the single
 * source the LLM-facing surface derives from — InterpretationPromptBuilder renders it
 * as provider instructions and LlmStructuredOutputValidator enforces it as the accepted
 * structured-output entity-key set. Before 9F.1B this derivation was duplicated in both
 * (with similar logic in NormalizedCommandValidator) — the drift seam this removes.
 *
 * Boundaries — a projection, NOT an authority:
 *  - ProviderSandboxContract remains the sandbox authority (consulted here).
 *  - IntentRegistry remains the intent-vocabulary authority (consulted here).
 *  - NormalizedCommandValidator remains the runtime command-validation authority.
 *
 * It decides nothing about readiness, lifecycle, ambiguity, entity resolution, or
 * execution. Required-ness is deliberately NOT encoded — missing fields stay a downstream
 * decision, so this never advertises a key as mandatory. It carries no provider/model/prompt
 * metadata and is fully deterministic and provider-blind.
 */
final class InterpretationGrammar
{
    /**
     * Entity keys DateTimeExpressionParser may append to any intent, independent of that
     * intent's requirements. Mirrors NormalizedCommandValidator's transitional allowlist.
     *
     * @var list<string>
     */
    public const UNIVERSAL_PARSER_KEYS = ['date', 'time'];

    public function __construct(private readonly IntentRegistry $registry) {}

    /**
     * The registered intent names, in registration order. The `unknown` sentinel is
     * intentionally excluded — it is not a registered intent.
     *
     * @return list<string>
     */
    public function intentNames(): array
    {
        return array_keys($this->registry->all());
    }

    /**
     * Whether an intent is registered. The `unknown` sentinel is handled by callers
     * separately and is not registered.
     */
    public function hasIntent(string $intent): bool
    {
        return $this->registry->find($intent) !== null;
    }

    /**
     * The entity keys a provider may emit for an intent: the canonical requirement keys,
     * the universal parser keys, and only the sandbox-legal entityType markers. Returns
     * an empty list for an unregistered intent.
     *
     * @return list<string>
     */
    public function allowedEntityKeys(string $intent): array
    {
        $definition = $this->registry->find($intent);

        return $definition === null ? [] : $this->deriveAllowedEntityKeys($definition);
    }

    /**
     * Allowed entity keys for every registered intent, keyed by intent name, in
     * registration order.
     *
     * @return array<string, list<string>>
     */
    public function entityKeysByIntent(): array
    {
        $byIntent = [];

        foreach ($this->registry->all() as $intent => $definition) {
            $byIntent[$intent] = $this->deriveAllowedEntityKeys($definition);
        }

        return $byIntent;
    }

    /**
     * @return list<string>
     */
    public function universalParserKeys(): array
    {
        return self::UNIVERSAL_PARSER_KEYS;
    }

    /**
     * Derive the allowed entity-key set for one intent (Phase 9F.1A rules, now owned
     * here): canonical requirement keys + universal parser keys + only sandbox-legal
     * entityType markers. `scalar` is a generic marker, not a real key, and is dropped;
     * resolver-backed `*_query` keys are kept only when ProviderSandboxContract allows
     * them (participant_query / user_query are not honored yet, lead_query is).
     *
     * @return list<string>
     */
    private function deriveAllowedEntityKeys(IntentDefinition $definition): array
    {
        $keys = self::UNIVERSAL_PARSER_KEYS;

        foreach ($definition->requirements as $req) {
            $keys[] = $req->key;

            $type = $req->entityType;

            if ($type === 'scalar') {
                continue;
            }

            if (str_ends_with($type, '_query') && ! ProviderSandboxContract::allowsReferenceKey($type)) {
                continue;
            }

            $keys[] = $type;
        }

        return array_values(array_unique($keys));
    }
}
