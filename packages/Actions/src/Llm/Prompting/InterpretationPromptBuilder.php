<?php

namespace Fluxio\Actions\Llm\Prompting;

use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\ProviderSandboxContract;
use Fluxio\Actions\Registry\IntentRegistry;

/**
 * Builds the strict prompt for the Ollama interpretation sandbox.
 *
 * The intent list and per-intent allowed entity keys are derived dynamically
 * from IntentRegistry / IntentDefinition / EntityRequirement — never hardcoded.
 * The allowed-key set mirrors LlmStructuredOutputValidator (requirement key +
 * sandbox-legal entityType + the universal parser keys) so the prompt and the
 * validator stay in agreement.
 *
 * Phase 9F.1A: the advertised surface is narrowed to the frozen
 * ProviderSandboxContract. Resolver-backed reference keys (`*_query`) are
 * advertised only when ProviderSandboxContract::allowsReferenceKey() permits them
 * (today: lead_query only), so the prompt never invites a key the adapter sandbox
 * would reject (participant_query, user_query). The generic `scalar` entityType
 * marker is not a real entity key and is never advertised.
 *
 * This is intentionally a flat, readable string builder. It is not a prompt
 * DSL or a template engine; it carries no business logic beyond enumeration.
 */
class InterpretationPromptBuilder
{
    /**
     * Universal parser keys accepted for any intent, mirrored from
     * LlmStructuredOutputValidator / NormalizedCommandValidator.
     */
    private const UNIVERSAL_PARSER_KEYS = ['date', 'time'];

    public function __construct(private readonly IntentRegistry $registry) {}

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

        foreach ($this->registry->all() as $definition) {
            $lines[] = '- '.$definition->intent.': '.implode(', ', $this->allowedEntityKeys($definition));
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

    /**
     * The entity keys advertised to the model for an intent: the canonical
     * requirement keys, the universal parser keys, and only the sandbox-legal
     * entityType markers. Public so a guardrail test can assert the advertised
     * surface is a subset of the sandbox-legal provider surface.
     *
     * @return list<string>
     */
    public function allowedEntityKeys(IntentDefinition $definition): array
    {
        $keys = self::UNIVERSAL_PARSER_KEYS;

        foreach ($definition->requirements as $req) {
            $keys[] = $req->key;

            $type = $req->entityType;

            // `scalar` is a generic type marker, not a real entity key — never advertise it.
            if ($type === 'scalar') {
                continue;
            }

            // Resolver-backed references (`*_query`) are advertised only when the
            // sandbox allows them; participant_query / user_query are not honored yet.
            if (str_ends_with($type, '_query') && ! ProviderSandboxContract::allowsReferenceKey($type)) {
                continue;
            }

            $keys[] = $type;
        }

        return array_values(array_unique($keys));
    }
}
