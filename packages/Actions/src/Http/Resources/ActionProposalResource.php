<?php

namespace Fluxio\Actions\Http\Resources;

use Fluxio\Actions\DTO\ActionProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionProposalResource extends JsonResource
{
    public function __construct(private readonly ActionProposal $proposal)
    {
        parent::__construct($proposal);
    }

    public function toArray(Request $request): array
    {
        return $this->proposal->toArray();
    }
}
