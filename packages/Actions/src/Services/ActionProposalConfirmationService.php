<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Validation\ValidationException;

class ActionProposalConfirmationService
{
    public function confirm(ActionProposal $proposal): ActionProposal
    {
        if ($proposal->status === 'confirmed') {
            return $proposal;
        }

        if ($proposal->status !== 'ready') {
            throw ValidationException::withMessages([
                'proposal' => [__('actions::actions.cannot_confirm')],
            ]);
        }

        $proposal->status = 'confirmed';
        $proposal->confirmed_at = now();
        $proposal->save();

        return $proposal;
    }
}
