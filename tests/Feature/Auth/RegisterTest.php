<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token_pair(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'super-secret-pw',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at'],
                'access' => ['token', 'expires_at', 'expires_in'],
                'refresh' => ['token', 'expires_at', 'expires_in'],
            ])
            ->assertJsonPath('user.email', 'alice@example.com');

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Bob',
            'email' => 'taken@example.com',
            'password' => 'super-secret-pw',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_short_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_register_rejects_missing_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
