<?php

namespace Fluxio\Tasks\Http\Controllers;

use Fluxio\Core\Http\Controllers\BaseApiController;
use Fluxio\Core\Http\Responses\ApiResponse;
use Fluxio\Tasks\Http\Requests\StoreTaskRequest;
use Fluxio\Tasks\Http\Requests\UpdateTaskRequest;
use Fluxio\Tasks\Http\Resources\TaskResource;
use Fluxio\Tasks\Models\Task;
use Fluxio\Tasks\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends BaseApiController
{
    public function __construct(private readonly TaskService $taskService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->taskService->paginate($request)->through(
            fn ($task) => (new TaskResource($task))->resolve()
        );

        return ApiResponse::paginated($paginator, 'tasks::tasks.index_success');
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->create($request->validated());

        return ApiResponse::success(
            (new TaskResource($task))->resolve(),
            'tasks::tasks.created',
            Response::HTTP_CREATED
        );
    }

    public function show(Task $task): JsonResponse
    {
        return ApiResponse::success(
            (new TaskResource($task))->resolve(),
            'tasks::tasks.shown'
        );
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task = $this->taskService->update($task, $request->validated());

        return ApiResponse::success(
            (new TaskResource($task))->resolve(),
            'tasks::tasks.updated'
        );
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->taskService->delete($task);

        return ApiResponse::success(null, 'tasks::tasks.deleted');
    }
}
