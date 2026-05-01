<?php

namespace Fluxio\Core\Exceptions;

use Fluxio\Core\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        // Laravel's prepareException() converts AuthorizationException →
        // AccessDeniedHttpException and ModelNotFoundException →
        // NotFoundHttpException before render callbacks run, so we match
        // those prepared types here.

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::validationError($e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::unauthorized();
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::forbidden();
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::notFound();
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                $message = app()->isProduction() ? 'core::api.server_error' : $e->getMessage();

                return ApiResponse::error($message, 500);
            }
        });
    }
}
