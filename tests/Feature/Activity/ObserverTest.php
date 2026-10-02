<?php

namespace Tests\Feature\Activity;

use App\Enums\LeadAction;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_lead_via_api_logs_created_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->postJson('/api/leads', [
            'name' => 'Prospect',
            'source' => 'Website',
        ])->assertCreated();

        $activity = LeadActivity::query()
            ->where('action', LeadAction::Created->value)
            ->firstOrFail();

        $this->assertSame($user->id, $activity->actor_id);
        $this->assertSame('Prospect', $activity->changes['after']['name']);
        $this->assertSame('Website', $activity->changes['after']['source']);
        $this->assertSame('New', $activity->changes['after']['status']);
    }

    public function test_updating_non_status_fields_logs_updated_only(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Old',
            'phone' => '111',
        ]);
        LeadActivity::query()->delete();

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}", [
            'name' => 'New',
            'phone' => '222',
        ])->assertOk();

        $activities = LeadActivity::query()->get();
        $this->assertCount(1, $activities);
        $this->assertSame(LeadAction::Updated, $activities[0]->action);
        $this->assertSame(['name' => 'Old', 'phone' => '111'], $activities[0]->changes['before']);
        $this->assertSame(['name' => 'New', 'phone' => '222'], $activities[0]->changes['after']);
    }

    public function test_status_only_change_logs_status_changed_only(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'status' => LeadStatus::New,
        ]);
        LeadActivity::query()->delete();

        $this->actingAsUser($user)->patchJson("/api/leads/{$lead->id}/status", [
            'status' => 'Won',
        ])->assertOk();

        $activities = LeadActivity::query()->get();
        $this->assertCount(1, $activities);
        $this->assertSame(LeadAction::StatusChanged, $activities[0]->action);
        $this->assertSame(['status' => 'New'], $activities[0]->changes['before']);
        $this->assertSame(['status' => 'Won'], $activities[0]->changes['after']);
    }

    public function test_simultaneous_field_and_status_change_logs_both(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Old',
            'status' => LeadStatus::New,
        ]);
        LeadActivity::query()->delete();

        // Direct model save bypasses the FormRequest filter and lets us
        // simulate a path where both fields are dirty in one save, which
        // is what we want the observer to split into two activities.
        $lead->update(['name' => 'New', 'status' => LeadStatus::Won]);

        $actions = LeadActivity::query()->pluck('action')->map->value->sort()->values()->all();
        $this->assertSame(['status_changed', 'updated'], $actions);
    }

    public function test_deleting_a_lead_logs_deleted(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        LeadActivity::query()->delete();

        $this->actingAsUser($user)->deleteJson("/api/leads/{$lead->id}")
            ->assertNoContent();

        $activity = LeadActivity::query()->firstOrFail();
        $this->assertSame(LeadAction::Deleted, $activity->action);
        $this->assertNull($activity->changes);
        $this->assertSame($user->id, $activity->actor_id);
    }
}
