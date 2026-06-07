<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentNarrationRegistry;

/**
 * Pure, read-only projection of an ActionProposal into a localized canonical
 * command phrase (slice 1).
 *
 * The canonical phrase is a deterministic projection of proposal state — never
 * authoritative runtime state, never a source of truth, never a replay mechanism,
 * never an LLM summary. This service:
 *
 * - does NOT mutate anything;
 * - does NOT resolve entities or infer missing values;
 * - does NOT mark proposals ready or change any lifecycle state;
 * - does NOT read previous conversation turns;
 * - does NOT use an LLM.
 *
 * It reads resolved display values from the proposal's `editable_fields` (so a
 * resolved lead label like "Studio Rossi SRL" is used rather than the raw query),
 * never IDs. When the proposal lacks any value required by the canonical template,
 * it returns null rather than producing a false "complete" phrase. Draft /
 * missing-field narration is a future slice.
 */
class NarrationProjectionService
{
    public function __construct(
        private readonly IntentNarrationRegistry $registry,
    ) {}

    /**
     * Produce the localized canonical phrase for a proposal, or null when the
     * proposal does not (yet) carry every value the canonical template needs.
     *
     * @param  string|null  $locale  Locale to render in; falls back to the app locale.
     */
    public function canonicalPhrase(ActionProposal $proposal, ?string $locale = null): ?string
    {
        $intent = $proposal->intent;

        if (! is_string($intent) || $intent === '') {
            return null;
        }

        $definition = $this->registry->find($intent);

        if ($definition === null) {
            return null;
        }

        // Raw template (placeholders intact). A missing translation key makes
        // trans() echo the key back — treat that as "no narration available".
        $template = trans($definition->canonicalTemplateKey, [], $locale);

        if (! is_string($template) || $template === $definition->canonicalTemplateKey) {
            return null;
        }

        $placeholders = $this->placeholders($template);

        if ($placeholders === []) {
            return null;
        }

        $values = $this->resolvedDisplayValues($proposal);

        $replacements = [];

        foreach ($placeholders as $placeholder) {
            $value = $values[$placeholder] ?? null;

            // Conservative: every placeholder the template declares must be
            // present as a non-empty scalar display value. Otherwise we refuse
            // to produce a phrase rather than render a partial/false sentence.
            if (! $this->isUsableValue($value)) {
                return null;
            }

            $replacements[$placeholder] = (string) $value;
        }

        $phrase = trans($definition->canonicalTemplateKey, $replacements, $locale);

        return is_string($phrase) ? $phrase : null;
    }

    /**
     * Extract :placeholder tokens from a template, de-duplicated, in order.
     *
     * @return list<string>
     */
    private function placeholders(string $template): array
    {
        preg_match_all('/:(\w+)/', $template, $matches);

        /** @var list<string> $names */
        $names = array_values(array_unique($matches[1] ?? []));

        return $names;
    }

    /**
     * Map of field key → resolved display value, taken from the proposal's
     * editable_fields. These already hold resolved labels (e.g. the chosen lead
     * candidate's label) rather than raw queries or IDs.
     *
     * @return array<string, mixed>
     */
    private function resolvedDisplayValues(ActionProposal $proposal): array
    {
        $values = [];

        foreach ($proposal->editable_fields ?? [] as $field) {
            if (is_array($field) && isset($field['key'])) {
                $values[$field['key']] = $field['value'] ?? null;
            }
        }

        return $values;
    }

    /**
     * A value is usable for a canonical phrase only when it is a non-empty
     * scalar. Arrays (e.g. collection fields) and empty/whitespace strings are
     * treated as absent — slice 1 canonical templates use scalar slots only.
     */
    private function isUsableValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_int($value) || is_float($value);
    }
}
