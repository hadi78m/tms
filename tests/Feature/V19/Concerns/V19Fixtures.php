<?php

namespace Tests\Feature\V19\Concerns;

use App\Domain\Services\ModuleService;
use App\Domain\Services\WbsPhaseService;
use App\Models\Module;
use App\Models\ModuleStage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WbsPhase;
use Spatie\Permission\Models\Role;
use Tests\Feature\V18\Concerns\V18Fixtures;

use function app;

/**
 * Shared fixtures for the V1.9 suite — built on the proven V1.8 patterns.
 *
 * All fixture text is deliberately ASCII where it does not assert Persian
 * output, so the suite never depends on database encoding.
 */
trait V19Fixtures
{
    use V18Fixtures;

    /**
     * Defines a complete single-module structure (weight 100) on the project
     * and returns the fresh module with its nine stages.
     *
     * @return Module
     */
    protected function makeSingleModule(Project $project, User $actor)
    {
        $modules = app(ModuleService::class)->createModules($project, [
            ['name' => 'Module A', 'code' => 'A', 'weight' => 100],
        ], $actor);

        return $modules->first()->fresh();
    }

    protected function makeWbsPhase(Project $project, User $actor, string $name = 'Phase One'): WbsPhase
    {
        return app(WbsPhaseService::class)->createPhase($project, [
            'name' => $name,
            'expected_output' => 'checklist placeholder',
            'weight' => 50,
            'planned_duration' => 30,
            'duration_unit' => 'day',
            'status' => 'active',
        ], $actor);
    }

    protected function makeStagedTask(Project $project, User $actor, int $stageId, string $title = 'staged task')
    {
        $stage = ModuleStage::findOrFail($stageId);

        return Task::create([
            'project_id' => $project->id,
            'contract_id' => $project->id,
            'contractor_id' => $project->id,
            'module_stage_id' => $stage->id,
            // chk_tasks_module_consistency: module_id must match the stage's
            // module when module_stage_id is set (DEC-021).
            'module_id' => $stage->module_id,
            'title' => $title,
            'weight' => 10,
            'priority' => 'normal',
            'status' => 'approved',
            'created_by' => $actor->id,
        ]);
    }

    protected function makeStagelessTask(Project $project, User $actor, string $title = 'stageless task')
    {
        return Task::create([
            'project_id' => $project->id,
            'contract_id' => $project->id,
            'contractor_id' => $project->id,
            'module_stage_id' => null,
            'title' => $title,
            'weight' => 99,
            'priority' => 'normal',
            'status' => 'approved',
            'created_by' => $actor->id,
        ]);
    }

    protected function role(string $name): void
    {
        Role::findOrCreate($name, 'web');
    }
}
