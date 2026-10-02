<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->optional(0.85)->phoneNumber(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => fake()->randomElement(LeadStatus::cases()),
            'note' => fake()->optional(0.4)->paragraph(),
            'created_at' => fake()->dateTimeBetween('-60 days'),
        ];
    }
}
