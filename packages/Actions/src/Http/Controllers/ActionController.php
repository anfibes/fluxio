<?php

namespace Fluxio\Actions\Http\Controllers;

use Fluxio\Actions\Http\Requests\InterpretActionRequest;
use Fluxio\Actions\Http\Resources\ActionProposalResource;
use Fluxio\Actions\Services\ActionInterpreterService;
use Fluxio\Core\Http\Controllers\BaseApiController;
use Fluxio\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActionController extends BaseApiController
{
    public function __construct(private readonly ActionInterpreterService $interpreterService) {}

    public function interpret(InterpretActionRequest $request): JsonResponse
    {
        $proposal = $this->interpreterService->interpret($request->validated('text'));

        return ApiResponse::success(
            (new ActionProposalResource($proposal))->resolve(),
            'actions::actions.interpreted'
        );
    }
}
