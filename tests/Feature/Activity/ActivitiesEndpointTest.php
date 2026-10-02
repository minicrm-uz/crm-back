<?php

namespace Tests\Feature\Activity;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitiesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_activities(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $lead->update(['name' => 'A']);
        $lead->update(['name' => 'B']);

        $this->actingAsUser($user)->getJson("/api/leads/{$lead->id}/activities")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(3, 'data'); // created + 2 updates
    }

    public function test_newest_activity_appears_first(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $lead->update(['name' => 'latest']);

        $response = $this->actingAsUser($user)->getJson("/api/leads/{$lead->id}/activities");

        $this->assertSame('updated', $response->json('data.0.action'));
        $this->assertSame('created', $response->json('data.1.action'));
    }

    public function test_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $owner->id]);

        $this->actingAsUser($other)->getJson("/api/leads/{$lead->id}/activities")
            ->assertStatus(403);
    }

    public function test_requires_authentication(): void
    {
        $lead = Lead::factory()->create();
        $this->getJson("/api/leads/{$lead->id}/activities")->assertStatus(401);
    }
}
