<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $others = User::factory(9)->create();
        $owners = $others->push($demo);

        Lead::factory(100)
            ->recycle($owners)
            ->create();
    }
}
