<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_on_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@example.com',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.email', 'demo@example.com');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_token_can_access_me_and_then_logout(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => bcrypt('secret'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo@example.com',
            'password' => 'secret',
        ]);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'demo@example.com');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_login_returns_401_on_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => bcrypt('secret')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_returns_422_on_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_me_returns_user_when_authenticated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Authenticated user retrieved successfully.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
