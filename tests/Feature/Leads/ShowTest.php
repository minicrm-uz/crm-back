<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->actingAsUser($user)->getJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $lead->id);
    }

    public function test_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);

        $this->actingAsUser($other)->getJson("/api/leads/{$lead->id}")
            ->assertStatus(403);
    }

    public function test_missing_lead_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->getJson('/api/leads/999999')
            ->assertStatus(404);
    }

    public function test_requires_authentication(): void
    {
        $lead = Lead::factory()->create();
        $this->getJson("/api/leads/{$lead->id}")->assertStatus(401);
    }
}
