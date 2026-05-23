<?php

namespace Fluxio\Actions\Http\Resources;

use Fluxio\Actions\DTO\IntentCapability;
use Fluxio\Actions\DTO\MutationCapability;
use Fluxio\Actions\Enums\RefinementCapabilityType;

/**
 * Serializes an IntentCapability into the localized capability payload
 * exposed by the proposal API. Technical keys are preserved; human-readable
 * labels are added via the actions:: translation namespace.
 */
final class IntentCapabilityResource
{
    public function __construct(
        private readonly IntentCapability $capability,
    ) {}

    /**
     * Localized payload for an existing capability.
     */
    public function resolve(): array
    {
        return [
            'supports_contextual_references' => $this->capability->supportsContextualReferences,
            'supports_ambiguity_resolution'  => $this->capability->supportsAmbiguityResolution,
            'supports_collection_mutations'  => $this->capability->supportsCollectionMutations,
            'mutations'                      => array_map(
                fn (MutationCapability $m) => $this->mutationToArray($m),
                $this->capability->mutations,
            ),
            'refinements' => array_map(
                fn (RefinementCapabilityType $r) => [
                    'key'   => $r->value,
                    'label' => $this->refinementLabel($r->value),
                ],
                $this->capability->refinements,
            ),
        ];
    }

    /**
     * Fallback payload used when no capability is registered for the intent.
     */
    public static function empty(): array
    {
        return [
            'supports_contextual_references' => false,
            'supports_ambiguity_resolution'  => false,
            'supports_collection_mutations'  => false,
            'mutations'                      => [],
            'refinements'                    => [],
        ];
    }

    private function mutationToArray(MutationCapability $m): array
    {
        return [
            'operation'       => $m->operation,
            'operation_label' => $this->operationLabel($m->operation),
            'fields'          => array_map(fn (string $field) => [
                'key'   => $field,
                'label' => $this->fieldLabel($field),
            ], $m->fields),
            'collection'      => $m->collection,
        ];
    }

    private function refinementLabel(string $key): string
    {
        $intentKey = "actions::actions.capabilities.intents.{$this->capability->intent}.refinements.{$key}";
        $intentLabel = $this->translate($intentKey);
        if ($intentLabel !== null) {
            return $intentLabel;
        }

        $generic = $this->translate("actions::actions.capabilities.refinements.{$key}");
        if ($generic !== null) {
            return $generic;
        }

        return $this->humanize($key);
    }

    private function fieldLabel(string $key): string
    {
        return $this->translate("actions::actions.capabilities.fields.{$key}")
            ?? $this->humanize($key);
    }

    private function operationLabel(string $key): string
    {
        return $this->translate("actions::actions.capabilities.operations.{$key}")
            ?? $this->humanize($key);
    }

    /**
     * Return a translated string or null if the key is missing.
     */
    private function translate(string $key): ?string
    {
        $translated = trans($key);

        return $translated === $key ? null : (is_string($translated) ? $translated : null);
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
