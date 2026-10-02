<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }

    public function test_zero_filled_for_user_with_no_leads(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('by_status.New', 0)
            ->assertJsonPath('by_status.Won', 0)
            ->assertJsonPath('by_source.Website', 0)
            ->assertJsonPath('won_rate', 0)
            ->assertJsonPath('this_week', 0)
            ->assertJsonPath('this_month', 0);
    }

    public function test_counts_are_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Lead::factory(3)->create(['owner_id' => $user->id, 'status' => LeadStatus::Won]);
        Lead::factory(10)->create(['owner_id' => $other->id, 'status' => LeadStatus::Won]);

        $this->actingAsUser($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('by_status.Won', 3);
    }

    public function test_by_status_and_by_source_counts(): void
    {
        $user = User::factory()->create();
        Lead::factory(2)->create(['owner_id' => $user->id, 'status' => LeadStatus::New, 'source' => LeadSource::Website]);
        Lead::factory(1)->create(['owner_id' => $user->id, 'status' => LeadStatus::Won, 'source' => LeadSource::Referral]);
        Lead::factory(4)->create(['owner_id' => $user->id, 'status' => LeadStatus::Lost, 'source' => LeadSource::Referral]);

        $this->actingAsUser($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonPath('by_status.New', 2)
            ->assertJsonPath('by_status.Won', 1)
            ->assertJsonPath('by_status.Lost', 4)
            ->assertJsonPath('by_status.Contacted', 0)
            ->assertJsonPath('by_source.Website', 2)
            ->assertJsonPath('by_source.Referral', 5)
            ->assertJsonPath('by_source.Event', 0);
    }

    public function test_won_rate_is_percentage_rounded_to_two_decimals(): void
    {
        $user = User::factory()->create();
        Lead::factory(1)->create(['owner_id' => $user->id, 'status' => LeadStatus::Won]);
        Lead::factory(2)->create(['owner_id' => $user->id, 'status' => LeadStatus::Lost]);

        $this->actingAsUser($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('won_rate', 33.33);
    }

    public function test_this_week_and_this_month_counts(): void
    {
        // Freeze time to a mid-month Wednesday so startOfWeek() and
        // startOfMonth() can't cross a month boundary; otherwise there
        // is no sane "this month but before this week" window to assert.
        Carbon::setTestNow(Carbon::create(2026, 10, 14, 12));
        $user = User::factory()->create();

        Lead::factory(2)->create(['owner_id' => $user->id, 'created_at' => now()->startOfWeek()->addHours(2)]);
        Lead::factory(3)->create(['owner_id' => $user->id, 'created_at' => now()->startOfMonth()->addHour()]);
        Lead::factory(5)->create(['owner_id' => $user->id, 'created_at' => now()->subMonths(2)]);

        $this->actingAsUser($user)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('total', 10)
            ->assertJsonPath('this_week', 2)
            ->assertJsonPath('this_month', 5);
    }
}
