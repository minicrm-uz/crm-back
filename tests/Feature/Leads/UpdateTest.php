<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Old Name',
            'phone' => '111',
        ]);

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '111');
    }

    public function test_update_cannot_change_status(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'status' => LeadStatus::New,
        ]);

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}", [
            'status' => 'Won',
            'name' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('data.status', LeadStatus::New->value)
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);

        $this->actingAsUser($other)->patchJson("/api/leads/{$lead->id}", [
            'name' => 'Hijack',
        ])->assertStatus(403);
    }

    public function test_status_endpoint_updates_status(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'status' => LeadStatus::New,
        ]);

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}/status", [
            'status' => 'Won',
        ])->assertOk()
            ->assertJsonPath('data.status', 'Won');

        $this->assertSame(LeadStatus::Won, $lead->fresh()->status);
    }

    public function test_status_endpoint_rejects_invalid_status(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}/status", [
            'status' => 'Nope',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_status_endpoint_enforces_authz(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);

        $this->actingAsUser($other)->patchJson("/api/leads/{$lead->id}/status", [
            'status' => 'Won',
        ])->assertStatus(403);
    }
}
