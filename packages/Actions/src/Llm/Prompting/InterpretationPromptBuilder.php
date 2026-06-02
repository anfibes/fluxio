<?php

namespace Fluxio\Actions\Llm\Prompting;

use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\InterpretationGrammar;

/**
 * Builds the strict prompt for the Ollama interpretation sandbox.
 *
 * The intent list and per-intent allowed entity keys are NOT derived here. They are
 * rendered from the runtime-owned InterpretationGrammar descriptor (Phase 9F.1B), the
 * single projection of IntentRegistry + ProviderSandboxContract that the structured-output
 * validator also consumes — so the prompt and the validator cannot describe different
 * surfaces. The descriptor already narrows resolver-backed `*_query` keys to the frozen
 * sandbox (lead_query only; never participant_query / user_query) and drops the generic
 * `scalar` marker.
 *
 * This is intentionally a flat, readable string builder. It is not a prompt
 * DSL or a template engine; it carries no business logic beyond rendering.
 */
class InterpretationPromptBuilder
{
    public function __construct(private readonly InterpretationGrammar $grammar) {}

    public function buildSystemPrompt(): string
    {
        $lines = [
            'You convert a single user message into one structured interpretation for a CRM/ERP assistant.',
            '',
            'Output rules (strict):',
            '- Respond with EXACTLY ONE JSON object and nothing else.',
            '- No prose. No explanations. No Markdown. No code fences.',
            '- Never include IDs, database references, endpoint names, model names, SQL, or internal identifiers.',
            '- The JSON object must have exactly these keys: "intent", "confidence", "entities", "notes".',
            '- "intent": one of the registered intents listed below, or "unknown".',
            '- "confidence": a number between 0 and 1 expressing interpretation certainty only.',
            '- "entities": an object using ONLY the entity keys allowed for the chosen intent (see below).',
            '- Omit any entity you are unsure about rather than guessing. Do not invent entity keys.',
            '- Entity values are user-facing labels or raw expressions (e.g. "Rossi", "tomorrow", "high"), never IDs.',
            '- "notes": an array of short strings (may be empty). Never put instructions or IDs here.',
            '- If you are unsure of the intent, return "unknown" with empty entities and a low confidence.',
            '- When intent is "unknown", "entities" must be an empty object.',
            '',
            'Registered intents and their allowed entity keys:',
        ];

        foreach ($this->grammar->entityKeysByIntent() as $intent => $keys) {
            $lines[] = '- '.$intent.': '.implode(', ', $keys);
        }

        $lines[] = '- unknown: (no entities)';
        $lines[] = '';
        $lines[] = 'Example of the required shape:';
        $lines[] = '{"intent":"create_task","confidence":0.82,"entities":{"lead":"Rossi","due_at":"tomorrow"},"notes":[]}';

        return implode("\n", $lines);
    }

    public function buildUserPrompt(string $text, InterpretationContext $context): string
    {
        return implode("\n", [
            'Locale: '.$context->locale,
            'User message:',
            $text,
        ]);
    }
}
