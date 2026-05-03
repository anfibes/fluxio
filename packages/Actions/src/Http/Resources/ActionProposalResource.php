<?php

namespace Fluxio\Actions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'intent' => $this->resource->intent,
            'status' => $this->resource->status,
            'confidence' => $this->resource->confidence,
            'source_text' => $this->resource->source_text,
            'entities' => $this->resource->entities ?? [],
            'missing' => $this->resource->missing ?? [],
            'warnings' => $this->resource->warnings ?? [],
            'editable_fields' => $this->resource->editable_fields ?? [],
            'changes' => $this->resource->changes ?? [],
            'needs_confirmation' => $this->resource->needs_confirmation,
            'confirmed_at' => $this->resource->confirmed_at?->toIso8601String(),
            'executed_at' => $this->resource->executed_at?->toIso8601String(),
            'failed_at' => $this->resource->failed_at?->toIso8601String(),
            'failure_reason' => $this->resource->failure_reason,
        ];
    }
}
