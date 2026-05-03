<?php

namespace Fluxio\Actions\Http\Resources;

use Fluxio\Actions\Models\ActionProposal;
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
            'execution_result' => $proposal->execution_result,
        ];
    }
}