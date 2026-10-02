<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'carol@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'carol@example.com',
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access' => ['token'], 'refresh' => ['token']]);

        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'dave@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'dave@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);

        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'anything',
        ])->assertStatus(401);
    }
}
