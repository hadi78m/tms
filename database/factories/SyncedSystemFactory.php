<?php

namespace Database\Factories;

use App\Models\SyncedSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncedSystem>
 */
class SyncedSystemFactory extends Factory
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
            'name' => $this->faker->word(),
            'code' => $this->faker->unique()->word(),
            'status' => 'active',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'success',
        ];
    }
}
