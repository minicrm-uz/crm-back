<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeAndLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_user_for_valid_bearer(): void
    {
        $user = User::factory()->create();
        $access = app(JwtTokenService::class)->issueAccessToken($user);

        $this->withHeader('Authorization', 'Bearer '.$access['token'])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_me_returns_401_without_bearer(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_me_returns_401_for_malformed_bearer(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-jwt')
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_me_returns_401_for_expired_bearer(): void
    {
        $user = User::factory()->create();
        $access = app(JwtTokenService::class)->issueAccessToken($user);

        $this->travel((int) config('jwt.access_ttl') + 60)->seconds();

        $this->withHeader('Authorization', 'Bearer '.$access['token'])
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_logout_revokes_all_active_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $service = app(JwtTokenService::class);
        $service->issueRefreshToken($user);
        $service->issueRefreshToken($user);
        $access = $service->issueAccessToken($user);

        $this->withHeader('Authorization', 'Bearer '.$access['token'])
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(
            0,
            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count()
        );
    }
}
