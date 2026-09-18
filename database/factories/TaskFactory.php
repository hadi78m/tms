<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'contract_id' => SyncedContract::factory(),
            'contractor_id' => SyncedContractor::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'priority' => 'medium',
            'weight' => 10,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
