<?php

namespace Fluxio\Actions\Http\Resources;

use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentCapabilityRegistry;
use Fluxio\Actions\Services\NarrationProjectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionProposalResource extends JsonResource
{
    public function __construct(ActionProposal $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var ActionProposal $proposal */
        $proposal = $this->resource;

        /** @var IntentCapabilityRegistry $registry */
        $registry = app(IntentCapabilityRegistry::class);
        $capability = $registry->find($proposal->intent);

        $capabilities = $capability !== null
            ? (new IntentCapabilityResource($capability))->resolve()
            : IntentCapabilityResource::empty();

        // Additive, read-only projection (Intent Narration — slice 2). Derived
        // purely from proposal state via NarrationProjectionService; null for
        // incomplete proposals and unknown/unsupported intents. Localized via the
        // app locale (set by SetApiLocale), the same mechanism as other atoms.
        // Never authoritative: it never feeds readiness, validation, or execution.
        $canonicalPhrase = app(NarrationProjectionService::class)->canonicalPhrase($proposal);

        return [
            'id' => $proposal->id,
            'intent' => $proposal->intent,
            'status' => $proposal->status,
            'confidence' => $proposal->confidence,
            'source_text' => $proposal->source_text,
            'entities' => $proposal->entities ?? [],
            'missing' => $proposal->missing ?? [],
            'warnings' => $proposal->warnings ?? [],
            'editable_fields' => $proposal->editable_fields ?? [],
            'changes' => $proposal->changes ?? [],
            'needs_confirmation' => $proposal->needs_confirmation,
            'confirmed_at' => $proposal->confirmed_at?->toIso8601String(),
            'executed_at' => $proposal->executed_at?->toIso8601String(),
            'failed_at' => $proposal->failed_at?->toIso8601String(),
            'failure_reason' => $proposal->failure_reason,
            'execution_failure' => $this->executionFailure($proposal),
            'execution_result' => $proposal->execution_result,
            'last_refinement' => $proposal->last_refinement,
            'ambiguities' => $proposal->ambiguities ?? [],
            'capabilities' => $capabilities,
            'canonical_phrase' => $canonicalPhrase,
        ];
    }

    /**
     * The typed execution failure surface (Phase 9B): a closed reason code plus the
     * same sanitized, localized message already in `failure_reason`. Null unless the
     * proposal carries a persisted failure code, so the frontend branches on a
     * closed taxonomy instead of parsing prose. Provider-blind: it names a terminal
     * outcome, never who produced the interpretation.
     *
     * @return array{reason: string, message: string}|null
     */
    private function executionFailure(ActionProposal $proposal): ?array
    {
        if ($proposal->status !== 'failed' || $proposal->failure_reason_code === null) {
            return null;
        }

        return [
            'reason' => $proposal->failure_reason_code,
            'message' => $proposal->failure_reason ?? '',
        ];
    }
}
