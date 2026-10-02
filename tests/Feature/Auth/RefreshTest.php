<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_rotates_tokens_and_revokes_the_old_one(): void
    {
        $user = User::factory()->create();
        $service = app(JwtTokenService::class);
        $original = $service->issueRefreshToken($user);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh' => $original['token'],
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access' => ['token'], 'refresh' => ['token']]);

        $oldHash = hash('sha256', $original['token']);
        $this->assertNotNull(RefreshToken::where('token_hash', $oldHash)->first()->revoked_at);

        $newToken = $response->json('refresh.token');
        $this->assertNotSame($original['token'], $newToken);
        $this->assertDatabaseCount('refresh_tokens', 2);
    }

    public function test_refresh_rejects_already_revoked_token(): void
    {
        $user = User::factory()->create();
        $service = app(JwtTokenService::class);
        $pair = $service->issueRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh' => $pair['token']])->assertOk();

        $this->postJson('/api/auth/refresh', ['refresh' => $pair['token']])
            ->assertStatus(401);
    }

    public function test_refresh_rejects_unknown_token(): void
    {
        $this->postJson('/api/auth/refresh', ['refresh' => 'this-is-not-a-real-token'])
            ->assertStatus(401);
    }

    public function test_refresh_requires_the_refresh_field(): void
    {
        $this->postJson('/api/auth/refresh', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refresh');
    }
}
