<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_api_email_login_returns_user_session_and_token(): void
    {
        $user = User::factory()->create([
            'email' => 'alex@example.com',
            'name' => 'Alex Puncak',
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'alex@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->id)
            ->assertJsonPath('user.email', 'alex@example.com')
            ->assertJsonPath('message', 'Signed in successfully.')
            ->assertJsonStructure(['token', 'tokenType']);

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson(route('api.v1.auth.user'))
            ->assertOk()
            ->assertJsonPath('data.email', 'alex@example.com');
    }

    public function test_api_login_rejects_wrong_credentials_with_json(): void
    {
        User::factory()->create(['email' => 'alex@example.com']);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'alex@example.com',
            'password' => 'wrong-password',
            'remember' => true,
        ])
            ->assertUnauthorized()
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('token');
    }
}
