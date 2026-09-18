<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => SyncedContract::factory(),
            'contractor_id' => SyncedContractor::factory(),
            'name' => $this->faker->sentence(),
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => 'active',
        ];
    }
}
