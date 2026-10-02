<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/leads')->assertStatus(401);
    }

    public function test_returns_only_the_authenticated_users_leads(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Lead::factory(3)->create(['owner_id' => $owner->id]);
        Lead::factory(2)->create(['owner_id' => $other->id]);

        $response = $this->actingAsUser($owner)->getJson('/api/leads')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertSame($owner->id, $row['owner_id']);
        }
    }

    public function test_paginates_results(): void
    {
        $user = User::factory()->create();
        Lead::factory(45)->create(['owner_id' => $user->id]);

        $this->actingAsUser($user)->getJson('/api/leads?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 45)
            ->assertJsonPath('meta.last_page', 5);
    }

    public function test_search_filters_by_name_substring(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Alice Johnson']);
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Bob Smith']);
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Alicia Keys']);

        $this->actingAsUser($user)->getJson('/api/leads?q=Ali')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_status(): void
    {
        $user = User::factory()->create();
        Lead::factory(2)->create(['owner_id' => $user->id, 'status' => LeadStatus::Won]);
        Lead::factory(3)->create(['owner_id' => $user->id, 'status' => LeadStatus::New]);

        $this->actingAsUser($user)->getJson('/api/leads?status=Won')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_by_source(): void
    {
        $user = User::factory()->create();
        Lead::factory(4)->create(['owner_id' => $user->id, 'source' => LeadSource::Referral]);
        Lead::factory(1)->create(['owner_id' => $user->id, 'source' => LeadSource::Website]);

        $this->actingAsUser($user)->getJson('/api/leads?source=Referral')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_sorts_by_name_ascending_and_descending(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Charlie']);
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Alice']);
        Lead::factory()->create(['owner_id' => $user->id, 'name' => 'Bob']);

        $this->actingAsUser($user)->getJson('/api/leads?sort=name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonPath('data.2.name', 'Charlie');

        $this->actingAsUser($user)->getJson('/api/leads?sort=-name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Charlie')
            ->assertJsonPath('data.2.name', 'Alice');
    }

    public function test_unknown_sort_field_falls_back_to_created_at_desc(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['owner_id' => $user->id, 'created_at' => now()->subDays(2)]);
        $newest = Lead::factory()->create(['owner_id' => $user->id, 'created_at' => now()]);

        $this->actingAsUser($user)->getJson('/api/leads?sort=injection--')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id);
    }
}
