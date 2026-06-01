<?php

namespace Fluxio\Actions\Interpretation;

use Fluxio\Actions\DTO\NormalizedCommand;

/**
 * Phase 9F.0 — the FROZEN provider sandbox surface.
 *
 * This is the explicit, enforceable contract that locks what ANY interpretation
 * provider (deterministic today, a constrained-decoding sandbox later) is allowed to
 * emit, narrowed to exactly the runtime surface Fluxio honors TODAY. It is checked at
 * the single boundary every provider crosses (InterpretationProviderAdapter), so the
 * runtime cannot silently accept an unsupported reference type or let a provider leak
 * a runtime/identity surface.
 *
 * It is deliberately UNDER-POWERED. It is NOT a generic NLP interface, NOT a general
 * entity-resolution protocol, and NOT an execution contract. The first provider
 * experiment is intentionally constrained to what the runtime already resolves.
 *
 * Allowed (a provider MAY emit):
 *  - registered intents only (enforced by NormalizedCommandValidator);
 *  - scalar parsed values already supported today (date, time, priority, due_at,
 *    assignee, location, quote, lead label, …);
 *  - `lead_query` as the ONLY resolver-backed entity reference — because
 *    ActionInterpreterService::resolveEntityQuery resolves lead_query only;
 *  - raw `date_expression` / `time_expression` (unconstrained, parser-normalized);
 *  - normalized `date`/`time` ONLY if shape-valid (enforced by NormalizedCommandValidator).
 *
 * Forbidden (this contract rejects):
 *  - any resolver-backed reference key other than `lead_query` (e.g. participant_query,
 *    user_query) — the runtime does not honor them yet;
 *  - identity / candidate-selection surface (ids, selected_candidate_id, candidates);
 *  - lifecycle / status / ambiguity / readiness / execution / failure surface.
 *
 * The runtime remains the ONLY authority for identity, ambiguity, readiness,
 * lifecycle, execution, and normalization semantics. A provider emits spans,
 * selectors, raw expressions, and normalized primitive values — never authority.
 *
 * This contract is intentionally additive to NormalizedCommandValidator: the
 * validator owns structural / value-shape / key-compatibility validation; this owns
 * the narrowed *sandbox surface lock* (and applies even to the `unknown` intent, for
 * which the validator skips key-compatibility).
 */
final class ProviderSandboxContract
{
    /**
     * The ONLY resolver-backed entity reference key a provider may emit today.
     * Frozen: expanding this set requires generalizing
     * ActionInterpreterService::resolveEntityQuery first (a later phase). A guardrail
     * test pins this to exactly ['lead_query'].
     *
     * @var list<string>
     */
    public const ALLOWED_REFERENCE_KEYS = ['lead_query'];

    /**
     * Forbidden entity-key surface tokens (matched case-insensitively as substrings):
     * identity, candidate selection, and lifecycle/runtime outputs the provider must
     * never emit. None of the legitimately-allowed keys contain any of these tokens.
     *
     * @var list<string>
     */
    public const FORBIDDEN_KEY_TOKENS = [
        '_id',
        'selected_candidate',
        'candidate',
        'status',
        'ambiguit',
        'missing',
        'readiness',
        'execution',
        'executed',
        'failure',
    ];

    /**
     * The sandbox violations for a provider command (empty = sandbox-legal). Keys are
     * inspected; values are left to NormalizedCommandValidator (shape/non-empty).
     *
     * @return list<string>
     */
    public function violations(NormalizedCommand $command): array
    {
        $violations = [];

        foreach (array_keys($command->entities) as $key) {
            if (! is_string($key)) {
                continue;
            }

            $lower = mb_strtolower($key);

            // Resolver-backed references are restricted to lead_query.
            if (str_ends_with($lower, '_query') && ! in_array($lower, self::ALLOWED_REFERENCE_KEYS, true)) {
                $violations[] = "entity reference key [{$key}] is not provider-legal in the 9F.0 sandbox; "
                    .'only lead_query is resolved by the runtime today';

                continue;
            }

            // Identity / lifecycle / runtime surface is runtime-owned, never provider-emitted.
            foreach (self::FORBIDDEN_KEY_TOKENS as $token) {
                if (str_contains($lower, $token)) {
                    $violations[] = "entity key [{$key}] exposes a forbidden runtime/identity surface [{$token}]; "
                        .'the runtime owns identity, ambiguity, lifecycle and execution';

                    break;
                }
            }
        }

        return $violations;
    }
}
