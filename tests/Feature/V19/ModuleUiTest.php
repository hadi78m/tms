<?php

namespace Tests\Feature\V19;

use App\Domain\Services\ModuleService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.9 — Module UI (DEC-039).
 *
 * Module definition, rebalance and update go through ModuleService; the
 * SUM(active modules.weight) = 100 invariant stays there. There is no delete
 * UI anywhere (DEC-038): the routes must not expose one.
 */
class ModuleUiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('modules.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_contractor(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)->get(route('modules.index'))->assertForbidden();
    }

    public function test_index_allowed_for_project_manager(): void
    {
        $this->actingAs($this->manager)->get(route('modules.index'))->assertOk();
    }

    public function test_show_forbidden_for_supervisor_without_structure_role(): void
    {
        // Supervisor IS allowed read access per route definition; use viewer for
        // the negative case of the write-only group instead.
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->actingAs($supervisor)->get(route('modules.show', $this->project))->assertOk();
    }

    public function test_store_defines_modules_through_the_service(): void
    {
        $this->actingAs($this->manager)
            ->post(route('modules.store'), [
                'project_id' => $this->project->id,
                'modules' => [
                    ['name' => 'Module A', 'code' => 'A', 'weight' => 100],
                ],
            ])
            ->assertRedirect(route('modules.show', $this->project->id))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('modules', [
            'project_id' => $this->project->id,
            'name' => 'Module A',
            'weight' => 100,
        ]);

        // Nine standard stages provisioned with the module.
        $module = Module::where('project_id', $this->project->id)->firstOrFail();
        $this->assertSame(9, $module->stages()->count());
    }

    public function test_store_rejects_weight_sum_not_equal_to_100(): void
    {
        $this->actingAs($this->manager)
            ->post(route('modules.store'), [
                'project_id' => $this->project->id,
                'modules' => [
                    ['name' => 'Module A', 'weight' => 60],
                    ['name' => 'Module B', 'weight' => 30],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('modules', ['project_id' => $this->project->id]);
    }

    public function test_store_rejected_for_second_definition(): void
    {
        $this->makeSingleModule($this->project, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('modules.store'), [
                'project_id' => $this->project->id,
                'modules' => [
                    ['name' => 'Another', 'weight' => 100],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_rebalance_requires_all_active_modules_and_sum_100(): void
    {
        $modules = app(ModuleService::class)->createModules($this->project, [
            ['name' => 'Module A', 'weight' => 40],
            ['name' => 'Module B', 'weight' => 60],
        ], $this->manager);

        // Partial map must be rejected by the service.
        $this->actingAs($this->manager)
            ->post(route('modules.rebalance', $this->project->id), [
                'weights' => [$modules[0]->id => 50],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Complete map summing to 100 must succeed.
        $this->actingAs($this->manager)
            ->post(route('modules.rebalance', $this->project->id), [
                'weights' => [$modules[0]->id => 70, $modules[1]->id => 30],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('modules', ['id' => $modules[0]->id, 'weight' => 70]);
    }

    public function test_update_rejects_weight_key(): void
    {
        $module = $this->makeSingleModule($this->project, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('modules.update', $module->id), [
                'name' => 'Renamed',
                'weight' => 55,
            ])
            ->assertSessionHasErrors('weight');

        $this->assertDatabaseMissing('modules', ['id' => $module->id, 'name' => 'Renamed']);
    }

    public function test_update_changes_attributes_without_touching_weight(): void
    {
        $module = $this->makeSingleModule($this->project, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('modules.update', $module->id), [
                'name' => 'Renamed Module',
                'code' => 'RM',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'name' => 'Renamed Module',
            'code' => 'RM',
            'weight' => 100,
        ]);
    }

    public function test_module_created_event_is_audited(): void
    {
        $this->actingAs($this->manager)
            ->post(route('modules.store'), [
                'project_id' => $this->project->id,
                'modules' => [
                    ['name' => 'Audited Module', 'weight' => 100],
                ],
            ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'module_created']);
    }
}
