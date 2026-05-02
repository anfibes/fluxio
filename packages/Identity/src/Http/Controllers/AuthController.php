<?php

namespace Fluxio\Identity\Http\Controllers;

use Fluxio\Core\Http\Controllers\BaseApiController;
use Fluxio\Core\Http\Responses\ApiResponse;
use Fluxio\Identity\Http\Requests\LoginRequest;
use Fluxio\Identity\Http\Resources\UserResource;
use Fluxio\Identity\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attempt(
            $request->input('email'),
            $request->input('password'),
        );

        if ($result === null) {
            return ApiResponse::error('identity::auth.login_failed', Response::HTTP_UNAUTHORIZED);
        }

        return ApiResponse::success([
            'user' => (new UserResource($result['user']))->resolve(),
            'token' => $result['token'],
        ], 'identity::auth.login_success');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        Auth::forgetGuards();

        return ApiResponse::success(null, 'identity::auth.logout_success');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            (new UserResource($request->user()))->resolve(),
            'identity::auth.me_success',
        );
    }
}
