<?php

namespace Database\Factories;

use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncedContract>
 */
class SyncedContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->uuid(),
            'source_system' => 'TEST_SYSTEM',
            'contract_number' => $this->faker->numerify('C-#####'),
            'title' => $this->faker->sentence(),
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'amount' => 1000000,
            'contractor_id' => SyncedContractor::factory(),
            'system_id' => SyncedSystem::factory(),
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ];
    }
}
