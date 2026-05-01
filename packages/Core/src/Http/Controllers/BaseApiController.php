<?php

namespace Fluxio\Core\Http\Controllers;

use Fluxio\Core\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

abstract class BaseApiController extends Controller
{
    protected function sendResponse(mixed $data = null, ?string $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function sendError(string $message, int $status = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }

    protected function sendValidationError(array $errors, string $message = 'core::api.validation_failed'): JsonResponse
    {
        return ApiResponse::validationError($errors, $message);
    }

    protected function sendNotFound(string $message = 'core::api.not_found'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function sendUnauthorized(string $message = 'core::api.unauthorized'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    protected function sendForbidden(string $message = 'core::api.forbidden'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }
}