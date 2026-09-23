<?php

namespace Tests\Feature\V18;

use App\Domain\Enums\ModuleStageCode;
use App\Domain\Exceptions\ModuleWeightException;
use App\Domain\Exceptions\StageWeightException;
use App\Domain\Exceptions\StageWeightLockedException;
use App\Domain\Services\ModuleService;
use App\Domain\Services\ModuleStageService;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * The project-level and module-level weight invariants (DEC-022 · DEC-023 ·
 * DEC-024 · DEC-025). These rules are NOT enforced by the database — there is
 * no aggregate CHECK and no aggregate trigger — so these tests are the
 * executable proof that the Service layer holds the line.
 */
class ModuleDomainServiceTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private function moduleService(): ModuleService
    {
        return app(ModuleService::class);
    }

    public function test_creating_modules_40_35_25_yields_a_valid_project(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);

        $this->assertCount(3, $modules);
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
        $this->assertSame(3, $this->moduleService()->activeCount($project->id));
    }

    public function test_every_new_module_is_born_with_the_nine_weighted_stages(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();

        $this->assertCount(9, $module->stages);
        $this->assertSame(
            ['analysis', 'design', 'coding', 'functional_test', 'penetration_test', 'training', 'pilot', 'production', 'support'],
            $module->stages()->orderBy('sort_order')->pluck('stage_code')->all()
        );
        $this->assertEqualsWithDelta(100.0, $module->allocatedStageWeight(), 0.001);
        $this->assertEqualsWithDelta(100.0, app(ModuleStageService::class)->allocatedSum($module->id), 0.001);
    }

    public function test_a_partial_module_set_is_rejected_and_rolled_back(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        try {
            // 40 + 35 = 75 — an impossible intermediate state
            $this->makeModules($project, $actor, [40, 35]);
            $this->fail('A module set summing to 75 must be rejected.');
        } catch (ModuleWeightException $e) {
            $this->assertStringContainsString('75.00', $e->getMessage());
        }

        $this->assertSame(0, $this->moduleService()->activeCount($project->id));
        $this->assertSame(0, Module::count());
    }

    public function test_zero_active_modules_is_a_valid_state(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->moduleService()->createModules($project, [], $actor);

        $this->assertCount(0, $modules);
        $this->assertSame(0, $this->moduleService()->activeCount($project->id));
    }

    public function test_defining_modules_twice_is_rejected(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $this->makeModules($project, $actor, [40, 35, 25]);

        $this->expectException(ModuleWeightException::class);

        $this->makeModules($project, $actor, [100]);
    }

    public function test_rebalance_moves_weight_atomically(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);
        [$a, $b, $c] = [$modules[0], $modules[1], $modules[2]];

        $this->moduleService()->rebalance($project, [
            $a->id => 45,
            $b->id => 30,
            $c->id => 25,
        ], $actor);

        $this->assertEqualsWithDelta(45.0, (float) $a->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $b->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
    }

    public function test_rebalance_with_a_broken_sum_is_rejected_and_changes_nothing(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);

        try {
            $this->moduleService()->rebalance($project, [
                $modules[0]->id => 60,
                $modules[1]->id => 30,
                $modules[2]->id => 25,
            ], $actor);
            $this->fail('A rebalance summing to 115 must be rejected.');
        } catch (ModuleWeightException) {
            // expected
        }

        $this->assertEqualsWithDelta(40.0, (float) $modules[0]->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
    }

    public function test_archiving_a_module_without_rebalancing_is_rejected(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);

        try {
            // Removing the 25% module would leave 75% — invariant violated
            $this->moduleService()->archive($modules[2], $actor);
            $this->fail('Archiving without rebalancing must be rejected.');
        } catch (ModuleWeightException) {
            // expected
        }

        $this->assertNull($modules[2]->fresh()->deleted_at);
        $this->assertSame(3, $this->moduleService()->activeCount($project->id));
    }

    public function test_archiving_the_last_module_is_allowed(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();

        $this->moduleService()->archive($module, $actor);

        $this->assertSame(0, $this->moduleService()->activeCount($project->id));
        $this->assertEqualsWithDelta(0.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
    }

    public function test_archiving_a_module_rebalances_the_rest_in_the_same_transaction(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);
        [$a, $b, $c] = [$modules[0], $modules[1], $modules[2]];

        $this->moduleService()->archive($c, $actor, [$a->id => 60, $b->id => 40]);

        $this->assertSame(2, $this->moduleService()->activeCount($project->id));
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_restoring_without_rebalancing_is_rejected(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);
        [$a, $b, $c] = [$modules[0], $modules[1], $modules[2]];

        $this->moduleService()->archive($c, $actor, [$a->id => 60, $b->id => 40]);

        try {
            // 60 + 40 + 25 = 125 — an invalid final state
            $this->moduleService()->restore($c, $actor);
            $this->fail('Restoring without rebalancing must be rejected.');
        } catch (ModuleWeightException) {
            // expected
        }

        $this->assertNotNull($c->fresh()->deleted_at);
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
    }

    public function test_restoring_with_a_rebalance_brings_the_module_back(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $modules = $this->makeModules($project, $actor, [40, 35, 25]);
        [$a, $b, $c] = [$modules[0], $modules[1], $modules[2]];

        $this->moduleService()->archive($c, $actor, [$a->id => 60, $b->id => 40]);
        $this->moduleService()->restore($c, $actor, [$a->id => 45, $b->id => 30, $c->id => 25]);

        $this->assertNull($c->fresh()->deleted_at);
        $this->assertSame(3, $this->moduleService()->activeCount($project->id));
        $this->assertEqualsWithDelta(100.0, $this->moduleService()->activeWeightSum($project->id), 0.001);
    }

    public function test_module_weight_cannot_be_updated_directly(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();

        $this->expectException(ModuleWeightException::class);

        $this->moduleService()->update($module, ['weight' => 50], $actor);
    }

    public function test_module_name_can_be_updated_and_is_audited(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();

        $this->moduleService()->update($module, ['name' => 'Renamed'], $actor);

        $this->assertSame('Renamed', $module->fresh()->name);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'module_updated_sensitive_field',
            'entity_id' => $module->id,
        ]);
    }

    public function test_stage_weights_can_be_rebalanced_before_any_approval(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();
        $stages = $module->stages()->pluck('weight', 'stage_code')->all();

        // Move 5 points from coding to analysis and keep the sum at 100.
        $coding = $module->stages()->where('stage_code', ModuleStageCode::Coding->value)->first();
        $analysis = $module->stages()->where('stage_code', ModuleStageCode::Analysis->value)->first();

        app(ModuleStageService::class)->rebalance($module, [
            $coding->id => 30,
            $analysis->id => 20,
        ], $actor);

        $this->assertEqualsWithDelta(30.0, (float) $coding->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $analysis->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(100.0, app(ModuleStageService::class)->allocatedSum($module->id), 0.001);
        $this->assertSame(9, count($stages));
    }

    public function test_a_single_stage_weight_change_that_breaks_the_sum_is_rejected(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();
        $analysis = $module->stages()->where('stage_code', ModuleStageCode::Analysis->value)->first();

        $this->expectException(StageWeightException::class);

        app(ModuleStageService::class)->changeWeight($analysis, 20, $actor);
    }

    public function test_stage_weight_is_locked_once_an_approval_row_exists(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('supervisor');
        $module = $this->makeModules($project, $actor, [100])->first();

        $analysis = $module->stages()->where('stage_code', ModuleStageCode::Analysis->value)->first();
        $coding = $module->stages()->where('stage_code', ModuleStageCode::Coding->value)->first();

        app(\App\Domain\Services\StageProgressApprovalService::class)->propose($analysis, $actor, 5);

        $this->assertTrue($analysis->fresh()->isWeightLocked());

        try {
            // Same rebalance that succeeds when no approval exists.
            app(ModuleStageService::class)->rebalance($module, [
                $coding->id => 30,
                $analysis->id => 20,
            ], $actor);
            $this->fail('A locked stage weight must not be changeable.');
        } catch (StageWeightLockedException) {
            // expected
        }

        $this->assertEqualsWithDelta(15.0, (float) $analysis->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(100.0, app(ModuleStageService::class)->allocatedSum($module->id), 0.001);
    }

    public function test_approval_history_is_never_lost_when_a_module_is_archived(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $module = $this->makeModules($project, $actor, [100])->first();
        $stageCount = $module->stages()->count();

        $this->moduleService()->archive($module, $actor);

        // Soft delete keeps the children and the history reachable.
        $this->assertSame($stageCount, $module->stages()->count());
        $this->assertNotNull(Module::withTrashed()->find($module->id));
    }
}
