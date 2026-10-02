<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_lead_owned_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->postJson('/api/leads', [
            'name' => 'Prospect A',
            'phone' => '+998 90 123 45 67',
            'email' => 'prospect@example.com',
            'source' => 'Referral',
            'note' => 'Met at conference',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Prospect A')
            ->assertJsonPath('data.owner_id', $user->id)
            ->assertJsonPath('data.status', LeadStatus::New->value);

        $this->assertDatabaseHas('leads', [
            'name' => 'Prospect A',
            'owner_id' => $user->id,
        ]);
    }

    public function test_cannot_set_owner_through_request_body(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAsUser($user)->postJson('/api/leads', [
            'name' => 'Hijack Attempt',
            'source' => 'Website',
            'owner_id' => $other->id,
        ])->assertCreated()
            ->assertJsonPath('data.owner_id', $user->id);
    }

    public function test_requires_name_and_source(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->postJson('/api/leads', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'source']);
    }

    public function test_rejects_invalid_source(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->postJson('/api/leads', [
            'name' => 'A',
            'source' => 'InvalidSource',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/leads', [])->assertStatus(401);
    }
}
