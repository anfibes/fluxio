<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionHandlerTest extends TestCase
{
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    public function test_validation_exception_returns_422(): void
    {
        Route::get('/_test/validation', function () {
            throw ValidationException::withMessages(['email' => ['required']]);
        });

        $response = $this->getJson('/_test/validation', $this->jsonHeaders());

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => ['email' => ['required']],
            ]);
    }

    public function test_authentication_exception_returns_401(): void
    {
        Route::get('/_test/auth', function () {
            throw new AuthenticationException();
        });

        $response = $this->getJson('/_test/auth', $this->jsonHeaders());

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_authorization_exception_returns_403(): void
    {
        Route::get('/_test/forbidden', function () {
            throw new AuthorizationException();
        });

        $response = $this->getJson('/_test/forbidden', $this->jsonHeaders());

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'This action is not allowed.']);
    }

    public function test_model_not_found_returns_404(): void
    {
        Route::get('/_test/model', function () {
            throw new ModelNotFoundException();
        });

        $response = $this->getJson('/_test/model', $this->jsonHeaders());

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_generic_throwable_returns_500_with_message_in_non_production(): void
    {
        // Expected negative path: a generic throwable renders a 500. Fake reporting for
        // ONLY RuntimeException so its expected message does not pollute laravel.log;
        // rendering still delegates to the real handler (500 + message preserved).
        Exceptions::fake([RuntimeException::class]);

        Route::get('/_test/crash', function () {
            throw new RuntimeException('something went wrong internally');
        });

        $response = $this->getJson('/_test/crash', $this->jsonHeaders());

        $response->assertStatus(500)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'something went wrong internally');

        // Suppressed from the log, not swallowed — it was still reported through the handler.
        Exceptions::assertReported(RuntimeException::class);
    }
}
