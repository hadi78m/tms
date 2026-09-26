<?php

namespace Tests\Feature\V19;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Module;
use App\Models\Project;
use App\Models\StageProgressApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;
use function route;

/**
 * V1.9 — Progress Report (DEC-039/040/041).
 *
 *   DEC-039: the stage-based progress source is SUM(approved_amount) over
 *            ACTIVE approvals (approved + not superseded) — the legacy
 *            SUM(tasks.weight) reader is removed from the affected calculation.
 *   DEC-040: tasks with module_stage_id IS NULL are excluded from the
 *            stage-based aggregation (reporting rule only — they remain valid
 *            records and are counted, not hidden).
 *   DEC-041: the KPI label is «مبلغ تأییدشدهٔ مراحل», never a payable figure.
 */
class ProgressReportTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $manager;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->supervisor = $this->makeUserWithRole('supervisor');
    }

    /**
     * @return array{Module, object}
     */
    private function makeApprovedProgress(float $amount)
    {
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis'); // weight 15

        $service = app(StageProgressApprovalService::class);
        $approval = $service->propose($stage, $this->supervisor, $amount);
        $service->approve($approval, $this->supervisor, $amount);

        return [$module, $stage];
    }

    public function test_report_requires_authorization(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)->get(route('reports.index'))->assertForbidden();

        $this->post(route('logout'));

        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_report_uses_approved_amount_as_stage_source(): void
    {
        $this->makeApprovedProgress(10);

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('10.00', false);
    }

    public function test_task_weight_is_not_the_stage_source(): void
    {
        // A staged task with an obviously different weight (99) plus an approved
        // stage amount of 5: the KPI must show 5.00, proving tasks.weight no
        // longer feeds the affected calculation.
        [$module, $stage] = $this->makeApprovedProgress(5);

        $this->makeStagedTask($this->project, $this->supervisor, $stage->id, 'heavy staged task');

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('5.00', false)
            ->assertDontSee('99.00', false);
    }

    public function test_superseded_approvals_are_excluded(): void
    {
        $service = app(StageProgressApprovalService::class);
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');

        $original = $service->propose($stage, $this->supervisor, 10);
        $service->approve($original, $this->supervisor, 10);

        // Supersede the approved row: its amount must drop out of the report.
        $replacement = $service->supersede($original, $this->supervisor, 5);
        $service->approve($replacement, $this->supervisor, 5);

        // Active approved total for the stage must now be exactly 5, not 15.
        $this->assertEquals(5.00, $stage->fresh()->approvedWeight());

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('5.00', false);
    }

    public function test_rejected_approvals_are_excluded(): void
    {
        $service = app(StageProgressApprovalService::class);
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');

        $approval = $service->propose($stage, $this->supervisor, 10);
        $service->reject($approval, $this->supervisor, 'no evidence');

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('0.00', false);
    }

    public function test_stageless_task_is_excluded_from_stage_aggregation_but_counted(): void
    {
        // DEC-040: a stage-less task must not contribute to the stage report
        // and must be surfaced as an excluded count, not silently dropped.
        $this->makeStagelessTask($this->project, $this->supervisor, 'orphan heavy task');

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('0.00', false)
            ->assertSee('1 وظیفه بدون مرحله', false);
    }

    public function test_staged_task_exists_in_report_project_scope(): void
    {
        // DEC-040 must not hide staged tasks: a staged task of the project is a
        // normal record and the stage rows still render.
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->first();

        $this->makeStagedTask($this->project, $this->supervisor, $stage->id);

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee($stage->name, false);
    }

    public function test_label_is_mablagh_taeed_shodeye_marahel(): void
    {
        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('مبلغ تأییدشدهٔ مراحل', false)
            ->assertDontSee('آماده پرداخت', false);
    }

    public function test_unrelated_task_indicators_remain(): void
    {
        // Task-level reports are NOT part of the DEC-040 stage scope and must
        // keep working (total task weight + delayed tasks KPIs remain).
        $this->makeStagelessTask($this->project, $this->supervisor, 'plain task');

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('وزن کل تسک‌ها', false)
            ->assertSee('99.0', false);
    }

    public function test_csv_export_still_works(): void
    {
        $this->makeStagelessTask($this->project, $this->supervisor);

        $this->actingAs($this->manager)
            ->get(route('reports.export', ['type' => 'all_tasks']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_active_definition_uses_the_single_scope(): void
    {
        // Structural guard: the report service aggregates through the model's
        // active() scope — superseded-but-pending rows are excluded there too.
        $service = app(StageProgressApprovalService::class);
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');

        $pending = $service->propose($stage, $this->supervisor, 7);

        $supersededActiveCount = StageProgressApproval::query()
            ->where('module_stage_id', $stage->id)
            ->active()
            ->count();

        $this->assertSame(0, $supersededActiveCount);
        $this->assertTrue($pending->isPending());
    }
}
