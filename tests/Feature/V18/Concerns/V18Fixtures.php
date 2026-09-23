<?php

namespace Tests\Feature\V18\Concerns;

use App\Domain\Services\ModuleService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Shared fixtures for the V1.8 suite.
 *
 * All fixture text is deliberately ASCII: a non-UTF8 test database cannot store
 * Persian at all, and the tests must not depend on that fix to run.
 */
trait V18Fixtures
{
    protected function makeProject(int $id = 1): Project
    {
        DB::table('synced_systems')->insertOrIgnore([
            'id' => $id, 'external_id' => 'sys_'.$id, 'source_system' => 'sys_a',
            'name' => 'System', 'code' => 'SYS'.$id, 'status' => 'active',
            'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('synced_contractors')->insertOrIgnore([
            'id' => $id, 'external_id' => 'con_'.$id, 'source_system' => 'sys_a',
            'name' => 'Contractor', 'code' => 'CON'.$id, 'status' => 'active',
            'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('synced_contracts')->insertOrIgnore([
            'id' => $id, 'system_id' => $id, 'contractor_id' => $id,
            'external_id' => 'ctr_'.$id, 'source_system' => 'sys_a',
            'contract_number' => 'C'.$id, 'title' => 'Contract '.$id,
            'start_date' => now(), 'end_date' => now()->addYear(), 'amount' => 1000,
            'status' => 'active', 'sync_status' => 'synced',
            'source_updated_at' => now(), 'last_synced_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('projects')->insertOrIgnore([
            'id' => $id, 'contract_id' => $id, 'contractor_id' => $id,
            'name' => 'Project '.$id, 'status' => 'active', 'start_date' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Project::findOrFail($id);
    }

    protected function makeUserWithRole(string $role = 'supervisor'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * @param  array<int, float>  $weights
     * @return Collection<int, \App\Models\Module>
     */
    protected function makeModules(Project $project, User $actor, array $weights = [40, 35, 25]): Collection
    {
        $definitions = [];

        foreach ($weights as $index => $weight) {
            $definitions[] = [
                'name' => 'Module '.chr(65 + $index),
                'code' => chr(65 + $index),
                'weight' => $weight,
            ];
        }

        return app(ModuleService::class)->createModules($project, $definitions, $actor);
    }
}
