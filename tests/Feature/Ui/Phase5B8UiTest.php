<?php

namespace Tests\Feature\Ui;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function route;

/** @test */
class Phase5B8UiTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $manager;

    private User $supervisor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->admin = $this->makeUserWithRole('admin');

        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisor->id,
                assigned_by: $this->supervisor->id
            ),
            $this->supervisor
        );
    }

    // ==================================================================
    // Part A — WBS decision UI authorization presentation
    // (server-side policy stays authoritative; this is UI presentation only)
    // ==================================================================

    public function test_wbs_decide_ui_presented_to_scoped_supervisor(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $html = $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<form method="POST" action="'.route('wbs-phases.decide', $phase->id).'"',
            $html,
            false
        );
        foreach (['complete', 'not_completed', 'reopen'] as $value) {
            $this->assertMatchesRegularExpression(
                '/type="radio" name="decision" value="'.$value.'"/u',
                $html,
                $value.' decision radio must render for the scoped supervisor'
            );
        }
    }

    public function test_wbs_decide_ui_not_presented_to_admin(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $html = $this->actingAs($this->admin)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->assertSee('Phase One') // page itself stays viewable (read role)
            ->getContent();

        // The decision UI must not be presented as an available action.
        $this->assertStringNotContainsString(
            'action="'.route('wbs-phases.decide', $phase->id).'"',
            $html
        );
        $this->assertStringNotContainsString('تصمیم ناظر پروژه', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/type="radio" name="decision"/u',
            $html
        );
        $this->assertStringNotContainsString('name="comment" maxlength="2000"', $html);
    }

    public function test_wbs_unauthorized_decide_post_still_rejected_server_side(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        // Admin passes the read boundary but the write is rejected by
        // middleware + WbsPhasePolicy (WBS-03b) — UI hiding is not the boundary.
        $this->actingAs($this->admin)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'complete',
                'comment' => 'admin must not decide',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $phase->fresh()->completion_status);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'wbs_phase_completed']);
    }

    public function test_wbs_checklist_and_info_still_render_for_admin_without_decide_ui(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $html = $this->actingAs($this->admin)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        // Non-decision sections are unaffected by the decide guard.
        $this->assertStringContainsString(
            '<form method="POST" action="'.route('wbs-phases.checklist-items.store', $phase->id).'"',
            $html,
            false
        );
        $this->assertStringContainsString('چک‌لیست خروجی‌ها', $html);
        $this->assertStringContainsString('اطلاعات فاز', $html);
    }

    // ==================================================================
    // Part B — modules/index migration contract (read-only page)
    // ==================================================================

    public function test_modules_index_renders_project_cards_with_manage_link(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('modules.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ساختار ماژول‌ها', $html);
        $this->assertStringContainsString($this->project->name, $html);
        $this->assertStringContainsString('پروژه #'.$this->project->id, $html);
        $this->assertStringContainsString(
            'href="'.route('modules.show', $this->project->id).'"',
            $html,
            false
        );
        $this->assertStringContainsString('مدیریت ساختار ماژول', $html);
    }

    public function test_modules_index_preserves_weight_and_stage_count_format(): void
    {
        $module = $this->makeSingleModule($this->project, $this->admin);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($module->name, $html);
        $this->assertStringContainsString(number_format($module->weight, 2).'%', $html);
        $this->assertStringContainsString(
            $module->stages->count().' مرحله استاندارد',
            $html
        );
    }

    public function test_modules_index_keeps_per_project_empty_state_text(): void
    {
        // No modules on the project → the same empty-state meaning as HEAD.
        $html = $this->actingAs($this->admin)
            ->get(route('modules.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'هنوز ماژول فعالی برای این پروژه تعریف نشده است.',
            $html
        );
    }

    public function test_modules_index_keeps_global_empty_state_text(): void
    {
        // Membershipless supervisor → Project Scope returns zero projects,
        // exercising the real @empty branch without mutating anything.
        $membershipless = $this->makeUserWithRole('supervisor');

        $html = $this->actingAs($membershipless)
            ->get(route('modules.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('هیچ پروژه‌ای ثبت نشده است.', $html);
    }

    public function test_modules_index_keeps_single_h1_heading_architecture(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('modules.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<h2[^>]*>/u', $html);
    }
}
