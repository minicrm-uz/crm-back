<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_soft_delete_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->actingAsUser($user)->deleteJson("/api/leads/{$lead->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);

        $this->actingAsUser($other)->deleteJson("/api/leads/{$lead->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);
    }

    public function test_soft_deleted_lead_hidden_from_index(): void
    {
        $user = User::factory()->create();
        $visible = Lead::factory()->create(['owner_id' => $user->id]);
        $deleted = Lead::factory()->create(['owner_id' => $user->id]);
        $deleted->delete();

        $this->actingAsUser($user)->getJson('/api/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }
}
