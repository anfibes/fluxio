<?php

namespace Fluxio\Core\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => self::translate($message),
            'data' => self::normalize($data),
        ], $status, $headers);
    }

    public static function error(string $message, int $status = Response::HTTP_BAD_REQUEST, array $errors = [], array $headers = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => self::translate($message),
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse($payload, $status, $headers);
    }

    public static function validationError(array $errors, string $message = 'core::api.validation_failed'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    public static function notFound(string $message = 'core::api.not_found'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    public static function unauthorized(string $message = 'core::api.unauthorized'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'core::api.forbidden'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    public static function paginated(LengthAwarePaginator $paginator, ?string $message = null, array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => self::translate($message),
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], Response::HTTP_OK, $headers);
    }

    private static function translate(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        return Lang::has($message) ? __($message) : $message;
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        return $data;
    }
}
