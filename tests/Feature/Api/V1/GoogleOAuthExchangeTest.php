<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleOAuthExchangeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_google_exchange_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-test-'.Str::uuid()->toString(),
        ]);
        $code = Str::random(64);
        Cache::put("oauth:google:exchange:{$code}", [
            'token' => 'plain-token',
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('token', 'plain-token')
            ->assertJsonPath('user.email', $user->email);

        $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonMissing(['token' => 'plain-token']);
    }
}
