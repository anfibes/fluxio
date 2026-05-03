<?php

namespace Fluxio\Actions\Http\Controllers;

use Fluxio\Actions\Http\Requests\ConfirmActionProposalRequest;
use Fluxio\Actions\Http\Requests\InterpretActionRequest;
use Fluxio\Actions\Http\Resources\ActionProposalResource;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Services\ActionInterpreterService;
use Fluxio\Actions\Services\ActionProposalConfirmationService;
use Fluxio\Actions\Services\ActionProposalPersistenceService;
use Fluxio\Core\Http\Controllers\BaseApiController;
use Fluxio\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActionController extends BaseApiController
{
    public function __construct(
        private readonly ActionInterpreterService $interpreterService,
        private readonly ActionProposalPersistenceService $persistenceService,
        private readonly ActionProposalConfirmationService $confirmationService,
    ) {}

    public function interpret(InterpretActionRequest $request): JsonResponse
    {
        $proposalData = $this->interpreterService->interpret($request->validated('text'));

        $proposal = $this->persistenceService->store($proposalData, $request->user());

        return ApiResponse::success(
            (new ActionProposalResource($proposal))->resolve(),
            'actions::actions.interpreted'
        );
    }

    public function confirm(ConfirmActionProposalRequest $request, ActionProposal $proposal): JsonResponse
    {
        if ($proposal->user_id !== $request->user()->id) {
            abort(404);
        }

        $confirmed = $this->confirmationService->confirm($proposal);

        return ApiResponse::success(
            (new ActionProposalResource($confirmed))->resolve(),
            'actions::actions.confirmed'
        );
    }
}
