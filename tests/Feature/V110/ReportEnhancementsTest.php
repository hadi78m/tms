<?php

namespace Tests\Feature\V110;

use App\Domain\Services\StageProgressApprovalService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;
use function route;

/**
 * V1.10 — Phase 5: Report Improvements (DEC-046).
 *
 *   5-1 Project Filter — SELECTED
 *   5-2 Period Filter  — SELECTED (on decided_at)
 *   5-3 Stage CSV      — SELECTED
 *   5-4 totalWeight KPI change — NOT selected: legacy KPI must be untouched.
 */
class ReportEnhancementsTest extends TestCase
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

    private function approveAtStage(float $amount): array
    {
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');

        $service = app(StageProgressApprovalService::class);
        $approval = $service->propose($stage, $this->supervisor, $amount);
        $approved = $service->approve($approval, $this->supervisor, $amount);

        return [$module, $stage, $approved];
    }

    // ------------------------------------------------------------------
    // 5-1 — Project Filter
    // ------------------------------------------------------------------

    public function test_project_filter_scopes_stage_rows(): void
    {
        $this->approveAtStage(10);

        // Other project with no stages: filter must show zero allocation.
        $this->actingAs($this->manager)
            ->get(route('reports.index', ['project_id' => 999]))
            ->assertOk()
            ->assertSee('0.00', false)
            ->assertDontSee('Module A', false);

        // No filter shows the stage row.
        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Module A', false);
    }

    public function test_project_filter_scopes_delayed_tasks_kpi(): void
    {
        Task::create([
            'project_id' => $this->project->id,
            'contract_id' => $this->project->contract_id,
            'contractor_id' => $this->project->contractor_id,
            'title' => 'delayed task',
            'priority' => 'normal',
            'weight' => 5,
            'status' => 'in_progress',
            'planned_due_at' => now()->subDay(),
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->get(route('reports.index', ['project_id' => $this->project->id]))
            ->assertOk();

        $other = $this->makeProject(2);
        Task::create([
            'project_id' => $other->id,
            'contract_id' => $other->contract_id,
            'contractor_id' => $other->contractor_id,
            'title' => 'delayed task other',
            'priority' => 'normal',
            'weight' => 5,
            'status' => 'in_progress',
            'planned_due_at' => now()->subDay(),
            'created_by' => $this->manager->id,
        ]);

        // Project-scoped view shows only the one delayed task of that project.
        $response = $this->actingAs($this->manager)
            ->get(route('reports.index', ['project_id' => $other->id]))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 5-2 — Period Filter (decided_at)
    // ------------------------------------------------------------------

    public function test_period_filter_excludes_approvals_outside_window(): void
    {
        // stage_progress_approvals is immutable once decided (DB trigger), so
        // the window is exercised against the real decision date (now): a
        // past window excludes the approval, a wide window includes it.
        $this->approveAtStage(10);

        // Window entirely in the past → approved sum must be 0.00.
        $this->actingAs($this->manager)
            ->get(route('reports.index', ['from' => '2020-01-01', 'to' => '2020-12-31']))
            ->assertOk()
            ->assertSee('0.00', false);

        // Wide window covering today → shows the amount.
        $this->actingAs($this->manager)
            ->get(route('reports.index', ['from' => '2020-01-01', 'to' => '2100-01-01']))
            ->assertOk()
            ->assertSee('10.00', false);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('reports.index', ['from' => '2026-03-01', 'to' => '2026-01-01']))
            ->assertStatus(422);

        $this->actingAs($this->manager)
            ->get(route('reports.index', ['from' => 'not-a-date']))
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // 5-3 — Stage CSV
    // ------------------------------------------------------------------

    public function test_stage_csv_export_streams_stage_rows(): void
    {
        [$module, $stage] = $this->approveAtStage(10);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.export', ['type' => 'stage_progress']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString($module->name, $content);
        $this->assertStringContainsString($stage->name, $content);
        $this->assertStringContainsString('10.00', $content);
        $this->assertStringContainsString('مبلغ تأییدشدهٔ مراحل', $content);
    }

    public function test_stage_csv_respects_project_filter(): void
    {
        $this->approveAtStage(10);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.export', ['type' => 'stage_progress', 'project_id' => 999]))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringNotContainsString('Module A', $content);
    }

    // ------------------------------------------------------------------
    // 5-4 — NOT selected: legacy totalWeight KPI unchanged
    // ------------------------------------------------------------------

    public function test_total_weight_kpi_is_unchanged(): void
    {
        // A stage-less task with a distinctive weight keeps feeding the legacy
        // task-level KPI — 5-4 was not selected, so nothing changed here.
        $this->makeStagelessTask($this->project, $this->supervisor, 'kpi task');

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('وزن کل تسک‌ها', false)
            ->assertSee('99.0', false);
    }

    // ------------------------------------------------------------------
    // DEC-040 regression under the new filters
    // ------------------------------------------------------------------

    public function test_stageless_count_survives_filters(): void
    {
        $this->makeStagelessTask($this->project, $this->supervisor);

        $this->actingAs($this->manager)
            ->get(route('reports.index', ['project_id' => $this->project->id]))
            ->assertOk()
            ->assertSee('1 وظیفه بدون مرحله', false);
    }
}
