<?php

namespace Fluxio\Leads\Http\Controllers;

use Fluxio\Core\Http\Controllers\BaseApiController;
use Fluxio\Core\Http\Responses\ApiResponse;
use Fluxio\Leads\Http\Requests\StoreLeadRequest;
use Fluxio\Leads\Http\Requests\UpdateLeadRequest;
use Fluxio\Leads\Http\Resources\LeadResource;
use Fluxio\Leads\Models\Lead;
use Fluxio\Leads\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LeadController extends BaseApiController
{
    public function __construct(private readonly LeadService $leadService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->leadService->paginate($request)->through(
            fn ($lead) => (new LeadResource($lead))->resolve()
        );

        return ApiResponse::paginated($paginator, 'leads::leads.index_success');
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leadService->create($request->validated());

        return ApiResponse::success(
            (new LeadResource($lead))->resolve(),
            'leads::leads.created',
            Response::HTTP_CREATED,
        );
    }

    public function show(Lead $lead): JsonResponse
    {
        return ApiResponse::success(
            (new LeadResource($lead))->resolve(),
            'leads::leads.shown',
        );
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead = $this->leadService->update($lead, $request->validated());

        return ApiResponse::success(
            (new LeadResource($lead))->resolve(),
            'leads::leads.updated',
        );
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->leadService->delete($lead);

        return ApiResponse::success(null, 'leads::leads.deleted');
    }
}
