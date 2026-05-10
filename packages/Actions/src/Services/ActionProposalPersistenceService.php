<?php

namespace Fluxio\Actions\Services;

use App\Models\User;
use Fluxio\Actions\DTO\ActionProposalData;
use Fluxio\Actions\DTO\EditableField;
use Fluxio\Actions\DTO\MissingField;
use Fluxio\Actions\DTO\ProposedChange;
use Fluxio\Actions\Models\ActionProposal;

class ActionProposalPersistenceService
{
    public function store(ActionProposalData $proposalData, User $user): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => $proposalData->intent,
            'status' => $proposalData->status,
            'confidence' => $proposalData->confidence,
            'source_text' => $proposalData->source_text,
            'entities' => $proposalData->entities,
            'missing' => array_map(fn (MissingField $f) => $f->toArray(), $proposalData->missing),
            'warnings' => $proposalData->warnings,
            'editable_fields' => array_map(fn (EditableField $f) => $f->toArray(), $proposalData->editable_fields),
            'changes' => array_map(fn (ProposedChange $c) => $c->toArray(), $proposalData->changes),
            'needs_confirmation' => $proposalData->needs_confirmation,
            'ambiguities' => $proposalData->ambiguities,
        ]);
    }
}
