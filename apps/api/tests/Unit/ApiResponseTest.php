<?php

namespace Tests\Unit;

use Fluxio\Core\Http\Responses\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_payload_shape(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Created');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Created',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_error_includes_errors_when_present(): void
    {
        $response = ApiResponse::error('boom', 400, ['field' => ['nope']]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'boom',
            'errors' => ['field' => ['nope']],
        ], $response->getData(true));
    }

    public function test_validation_error_uses_422_and_translation_key(): void
    {
        $response = ApiResponse::validationError(['email' => ['required']]);
        $body = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('The given data was invalid.', $body['message']);
        $this->assertSame(['email' => ['required']], $body['errors']);
    }

    public function test_not_found_unauthorized_forbidden_status_codes(): void
    {
        $this->assertSame(404, ApiResponse::notFound()->getStatusCode());
        $this->assertSame(401, ApiResponse::unauthorized()->getStatusCode());
        $this->assertSame(403, ApiResponse::forbidden()->getStatusCode());
    }

    public function test_unknown_message_string_passes_through_untranslated(): void
    {
        $response = ApiResponse::error('totally.unknown.key');

        $this->assertSame('totally.unknown.key', $response->getData(true)['message']);
    }
}
