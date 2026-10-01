<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * V1.11 — Module Phase · I-4 (DEC-061 · DEC-063): HTTP wiring + security for
 * GET stages/{stage} (modules.stages.show).
 *
 * Authorization chain under test:
 *   GET stages/{stage} → ModuleStageController@show → authorize('view', $stage)
 *   → ModuleStagePolicy::view → ModuleStage→Module→Project → ProjectScopeService
 *
 * Also pins (without duplicating I-3): the stage rebalance route keeps its
 * Module-level authorization (ModulePolicy::rebalanceStages) — I-4 introduces
 * no ModuleStagePolicy ability for it and does not move it.
 *
 * Expected role matrix (locked, DR-EMP-01 OPEN):
 *   admin/management unrestricted · PM/viewer preserved (fail-open) ·
 *   supervisor = active-membership scope · employer fail-open ·
 *   contractor blocked by route middleware.
 */
class ModuleStageScopeWiringTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private Project $projectA;

    private Project $projectB;

    private Module $moduleA;

    private Module $moduleB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);

        $this->moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $this->moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();
    }

    private function scopeSupervisorToProjectA(User $user): void
    {
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $this->projectA->id,
                user_id: $user->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );
    }

    private function stageOf(Module $module, string $code = 'analysis')
    {
        return $module->stages()->firstWhere('stage_code', $code);
    }

    // ------------------------------------------------------------------
    // HTTP wiring — role matrix over GET stages/{stage}
    // ------------------------------------------------------------------

    public function test_supervisor_views_same_project_stage(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleA)->id))
            ->assertOk();
    }

    public function test_supervisor_denied_foreign_project_stage(): void
    {
        // The I-3 gap: direct-ID enumeration across projects is now closed.
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleB)->id))
            ->assertForbidden();
    }

    public function test_supervisor_without_membership_denied_stage(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->actingAs($supervisor)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleA)->id))
            ->assertForbidden();
    }

    public function test_admin_views_foreign_project_stage(): void
    {
        $this->actingAs($this->admin)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleB)->id))
            ->assertOk();
    }

    public function test_pm_preserves_current_behavior(): void
    {
        $pm = $this->makeUserWithRole('project_manager');

        $this->actingAs($pm)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleB)->id))
            ->assertOk();
    }

    public function test_viewer_preserves_current_behavior(): void
    {
        $viewer = $this->makeUserWithRole('viewer');

        $this->actingAs($viewer)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleB)->id))
            ->assertOk();
    }

    public function test_employer_preserves_current_behavior(): void
    {
        // DR-EMP-01 OPEN — fail-open pinned as current behavior, NOT a decision.
        $employer = $this->makeUserWithRole('employer');

        $this->actingAs($employer)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleB)->id))
            ->assertOk();
    }

    public function test_contractor_denied_by_middleware(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)
            ->get(route('modules.stages.show', $this->stageOf($this->moduleA)->id))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Wiring proof
    // ------------------------------------------------------------------

    public function test_show_wires_the_policy_and_rebalance_stays_module_level(): void
    {
        // show() actually consults the policy: a scope-restricted supervisor
        // is 403 (above) while the same actor passes middleware — here we pin
        // the wiring non-brittlely via the Gate mapping and the controller
        // signature (ModuleStage-bound, view ability name).
        $this->assertSame(
            \App\Policies\ModuleStagePolicy::class,
            \Illuminate\Support\Facades\Gate::getPolicyFor($this->stageOf($this->moduleA))::class
        );

        $show = new \ReflectionMethod(\App\Http\Controllers\Web\ModuleStageController::class, 'show');
        $this->assertSame(\App\Models\ModuleStage::class, (string) $show->getParameters()[0]->getType());

        $body = file_get_contents((new \ReflectionClass(\App\Http\Controllers\Web\ModuleStageController::class))->getFileName());
        $this->assertStringContainsString("authorize('view', \$stage)", $body);
        $this->assertStringNotContainsString('updateWeight', $body);
    }

    // ------------------------------------------------------------------
    // Rebalance regression — stays on ModulePolicy::rebalanceStages
    // ------------------------------------------------------------------

    public function test_stage_rebalance_still_authorizes_via_module_policy(): void
    {
        // Supervisor scoped to A: foreign-module stage rebalance must be 403 —
        // and that denial comes from ModulePolicy::rebalanceStages (role mirror
        // denies supervisor before scope even matters), NOT from any stage
        // ability. A ModuleStagePolicy has no rebalance ability (I-4).
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $stages = $this->moduleB->stages()->orderBy('sort_order')->get();

        $this->actingAs($supervisor)
            ->post(route('modules.stages.rebalance', $this->moduleB->id), [
                'weights' => [
                    $stages->firstWhere('stage_code', 'analysis')->id => 20,
                    $stages->firstWhere('stage_code', 'coding')->id => 30,
                ],
            ])
            ->assertForbidden();

        $this->assertFalse(method_exists(\App\Policies\ModuleStagePolicy::class, 'rebalanceStages'));
        $this->assertFalse(method_exists(\App\Policies\ModuleStagePolicy::class, 'updateWeight'));
    }

    public function test_module_rebalance_policy_remains_intact(): void
    {
        // The I-1 ModulePolicy::rebalanceStages ability still exists and maps
        // the Module resource — nothing moved to ModuleStagePolicy in I-4.
        $this->assertTrue(method_exists(\App\Policies\ModulePolicy::class, 'rebalanceStages'));

        $rebalance = new \ReflectionMethod(\App\Http\Controllers\Web\ModuleStageController::class, 'rebalance');
        $this->assertSame(\App\Models\Module::class, (string) $rebalance->getParameters()[0]->getType());
    }
}
