<?php

namespace Fluxio\Actions\DTO;

/**
 * Narration metadata for a single intent.
 *
 * Slice 1 — canonical phrase only. This DTO deliberately owns *only* narration
 * presentation metadata; it never re-declares the intent's slot/placeholder map.
 * The authoritative placeholder vocabulary lives in IntentDefinition /
 * EntityRequirement (requirement keys); narration templates reference those keys
 * by name and are validated against them by the coverage tests.
 *
 * This is an in-memory declaration — never persisted, never authoritative runtime
 * state — mirroring IntentCapability.
 */
class IntentNarrationDefinition
{
    public function __construct(
        public readonly string $intent,
        /**
         * Fully-qualified translation key for the canonical command phrase,
         * e.g. "actions::actions.narration.schedule_meeting.canonical".
         * The localized text (with :placeholder tokens) lives in the translation
         * files, never here.
         */
        public readonly string $canonicalTemplateKey,
    ) {}
}
