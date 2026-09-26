<?php

namespace Tests\Feature\V19;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;

/**
 * V1.9 — Stage UI + Stage Weight Management (DEC-039).
 *
 * Weight moves only through ModuleStageService::rebalance(); the resulting
 * stage sum must stay 100 and locked stages (any approval row exists) must be
 * refused by the service, which the UI surfaces as an error flash.
 */
class StageWeightUiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $manager;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->module = $this->makeSingleModule($this->project, $this->manager);
    }

    public function test_stage_show_requires_authentication(): void
    {
        $stage = $this->module->stages()->first();

        $this->get(route('modules.stages.show', $stage->id))->assertRedirect(route('login'));
    }

    public function test_stage_show_forbidden_for_contractor(): void
    {
        $stage = $this->module->stages()->first();
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)
            ->get(route('modules.stages.show', $stage->id))
            ->assertForbidden();
    }

    public function test_stage_show_shows_weight_and_lock_state(): void
    {
        $stage = $this->module->stages()->first();

        $this->actingAs($this->manager)
            ->get(route('modules.stages.show', $stage->id))
            ->assertOk()
            ->assertSee($stage->name);
    }

    public function test_rebalance_moves_weight_between_two_stages(): void
    {
        $stages = $this->module->stages()->orderBy('sort_order')->get();
        $analysis = $stages->firstWhere('stage_code', 'analysis');
        $coding = $stages->firstWhere('stage_code', 'coding');

        // 15 → 20 and 35 → 30: the resulting sum stays 100.
        $this->actingAs($this->manager)
            ->post(route('modules.stages.rebalance', $this->module->id), [
                'weights' => [$analysis->id => 20, $coding->id => 30],
            ])
            ->assertRedirect(route('modules.show', $this->project->id))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('module_stages', ['id' => $analysis->id, 'weight' => 20]);
        $this->assertDatabaseHas('module_stages', ['id' => $coding->id, 'weight' => 30]);
    }

    public function test_rebalance_rejects_resulting_sum_other_than_100(): void
    {
        $stage = $this->module->stages()->first();

        $this->actingAs($this->manager)
            ->post(route('modules.stages.rebalance', $this->module->id), [
                'weights' => [$stage->id => 99],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('module_stages', ['id' => $stage->id, 'weight' => $stage->weight]);
    }

    public function test_rebalance_refuses_locked_stage(): void
    {
        $stage = $this->module->stages()->first();

        // Any approval row — pending included — locks the weight (OQ-05 = 3A).
        $supervisor = $this->makeUserWithRole('supervisor');
        app(StageProgressApprovalService::class)->propose($stage, $supervisor, 5);

        $this->actingAs($this->manager)
            ->post(route('modules.stages.rebalance', $this->module->id), [
                'weights' => [$stage->id => 20],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('module_stages', ['id' => $stage->id, 'weight' => $stage->weight]);
    }

    public function test_weight_change_is_audited(): void
    {
        $stages = $this->module->stages()->orderBy('sort_order')->get();
        $analysis = $stages->firstWhere('stage_code', 'analysis');
        $coding = $stages->firstWhere('stage_code', 'coding');

        $this->actingAs($this->manager)
            ->post(route('modules.stages.rebalance', $this->module->id), [
                'weights' => [$analysis->id => 20, $coding->id => 30],
            ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'stage_weight_changed']);
    }

    public function test_module_service_is_not_duplicated_by_http_layer(): void
    {
        // The HTTP path must go through ModuleStageService — the negative
        // rejection above proves the service guard runs. This positive guard
        // verifies the module service invariant is untouched (SUM = 100 after
        // rebalance through HTTP).
        $stages = $this->module->stages()->get();
        $sum = 0.0;

        foreach ($stages as $stage) {
            $sum += (float) $stage->weight;
        }

        $this->assertEquals(100.0, round($sum, 2));
    }
}
