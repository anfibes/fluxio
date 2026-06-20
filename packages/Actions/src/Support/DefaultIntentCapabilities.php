<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\IntentCapability;
use Fluxio\Actions\DTO\MutationCapability;
use Fluxio\Actions\Enums\RefinementCapabilityType;

/**
 * Canonical, static list of intent capabilities shipped with the Actions module.
 *
 * These are in-memory declarations — never persisted — and are consulted by
 * ActionProposalRefinementService before applying any mutation.
 *
 * To add or change a capability, edit the array returned by all().
 * ActionsServiceProvider iterates this list and registers each entry.
 */
final class DefaultIntentCapabilities
{
    /**
     * @return list<IntentCapability>
     */
    public static function all(): array
    {
        return [
            // create_task: scalar fields only — no collection mutations.
            // Lead is optional, but when supplied it goes through entity resolution and
            // can produce a blocking lead ambiguity. Any intent that can generate a
            // blocking ambiguity must be able to resolve it via refinement, otherwise the
            // proposal becomes permanently unconfirmable — so ambiguity resolution is
            // supported here (lead replace already covers promoting the resolved candidate).
            new IntentCapability(
                intent: 'create_task',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['date', 'time', 'priority', 'lead'], collection: false),
                    new MutationCapability(operation: 'clear', fields: ['priority'], collection: false),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ClearField,
                    RefinementCapabilityType::ResolveAmbiguity,
                ],
                supportsContextualReferences: false,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: false,
            ),

            // schedule_call: scalar fields + collection participants.
            // Ambiguity resolution is supported (lead requires entity resolution).
            new IntentCapability(
                intent: 'schedule_call',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['date', 'time', 'lead', 'priority'], collection: false),
                    new MutationCapability(operation: 'clear', fields: ['priority'], collection: false),
                    new MutationCapability(operation: 'append', fields: ['participants'], collection: true),
                    new MutationCapability(operation: 'remove', fields: ['participants'], collection: true),
                    new MutationCapability(operation: 'replace', fields: ['participants'], collection: true),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ClearField,
                    RefinementCapabilityType::AppendCollectionItem,
                    RefinementCapabilityType::RemoveCollectionItem,
                    RefinementCapabilityType::ReplaceCollectionItem,
                    RefinementCapabilityType::ResolveAmbiguity,
                ],
                supportsContextualReferences: false,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: true,
            ),

            // schedule_meeting: same as schedule_call plus location and contextual references.
            // Full collection mutation support + ambiguity resolution.
            new IntentCapability(
                intent: 'schedule_meeting',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['date', 'time', 'lead', 'location'], collection: false),
                    new MutationCapability(operation: 'clear', fields: ['location'], collection: false),
                    new MutationCapability(operation: 'append', fields: ['participants'], collection: true),
                    new MutationCapability(operation: 'remove', fields: ['participants'], collection: true),
                    new MutationCapability(operation: 'replace', fields: ['participants'], collection: true),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ClearField,
                    RefinementCapabilityType::AppendCollectionItem,
                    RefinementCapabilityType::RemoveCollectionItem,
                    RefinementCapabilityType::ReplaceCollectionItem,
                    RefinementCapabilityType::ResolveAmbiguity,
                    RefinementCapabilityType::ContextualReference,
                ],
                supportsContextualReferences: true,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: true,
            ),

            // assign_lead: scalar field replacements only.
            // Ambiguity resolution is supported (both lead and assignee may require it in future).
            // No collection mutations.
            new IntentCapability(
                intent: 'assign_lead',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['lead', 'assignee'], collection: false),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ResolveAmbiguity,
                ],
                supportsContextualReferences: false,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: false,
            ),

            // update_task_status: scalar field replacements only (target task + status).
            // Ambiguity resolution is supported because `task` goes through entity
            // resolution and can become a blocking ambiguity — required by the
            // AmbiguityCapabilityInvariant. No collection mutations.
            new IntentCapability(
                intent: 'update_task_status',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['task', 'state'], collection: false),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ResolveAmbiguity,
                ],
                supportsContextualReferences: false,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: false,
            ),

            // prepare_contract_from_quote: lead and quote replacement.
            // Ambiguity resolution is supported because lead requires entity resolution.
            // Collection mutations and contextual references remain disabled.
            new IntentCapability(
                intent: 'prepare_contract_from_quote',
                mutations: [
                    new MutationCapability(operation: 'replace', fields: ['lead', 'quote'], collection: false),
                ],
                refinements: [
                    RefinementCapabilityType::ReplaceField,
                    RefinementCapabilityType::ResolveAmbiguity,
                ],
                supportsContextualReferences: false,
                supportsAmbiguityResolution: true,
                supportsCollectionMutations: false,
            ),
        ];
    }
}
