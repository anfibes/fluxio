<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\IntentNarrationDefinition;

/**
 * Canonical, static list of intent narration metadata shipped with the Actions
 * module (slice 1: canonical phrase only).
 *
 * These are in-memory declarations — never persisted. ActionsServiceProvider
 * iterates this list and registers each entry into IntentNarrationRegistry.
 *
 * The localized phrase text (with :placeholder tokens) lives in the translation
 * files (actions::actions.narration.*), not here. The placeholder names must be
 * requirement keys of the corresponding IntentDefinition — this is enforced by
 * the narration coverage tests.
 *
 * To add or change narration for an intent, edit the array returned by all() and
 * the matching translation keys.
 */
final class DefaultIntentNarration
{
    /**
     * @return list<IntentNarrationDefinition>
     */
    public static function all(): array
    {
        return [
            new IntentNarrationDefinition(
                intent: 'create_task',
                canonicalTemplateKey: 'actions::actions.narration.create_task.canonical',
            ),
            new IntentNarrationDefinition(
                intent: 'schedule_call',
                canonicalTemplateKey: 'actions::actions.narration.schedule_call.canonical',
            ),
            new IntentNarrationDefinition(
                intent: 'schedule_meeting',
                canonicalTemplateKey: 'actions::actions.narration.schedule_meeting.canonical',
            ),
            new IntentNarrationDefinition(
                intent: 'assign_lead',
                canonicalTemplateKey: 'actions::actions.narration.assign_lead.canonical',
            ),
            new IntentNarrationDefinition(
                intent: 'prepare_contract_from_quote',
                canonicalTemplateKey: 'actions::actions.narration.prepare_contract_from_quote.canonical',
            ),
        ];
    }
}
