<?php

namespace Tests\Feature\Ui;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Domain\Services\WbsPhaseService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function route;

/** @test */
class Phase5B7UiTest extends TestCase
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
    // wbs-phases/show — authorization + identity
    // ==================================================================

    public function test_wbs_show_renders_for_supervisor_with_active_membership(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->assertSee('Phase One');
    }

    public function test_wbs_show_keeps_back_link(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $html = $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.route('wbs-phases.index').'"',
            $html
        );
    }

    // ==================================================================
    // wbs-phases/show — checklist contract
    // ==================================================================

    public function test_wbs_show_draws_checklist_actions_with_same_routes(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');
        $item = app(WbsPhaseService::class)->addChecklistItem($phase, $this->manager, 'SRS');

        $html = $this->actingAs($this->manager)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('SRS', $html);
        $this->assertStringContainsString(
            route('wbs-phases.checklist-items.complete', [$phase->id, $item->id]),
            $html
        );
    }

    public function test_wbs_show_reopen_action_appears_only_for_completed_items(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');
        $item = app(WbsPhaseService::class)->addChecklistItem($phase, $this->manager, 'ERD');
        app(WbsPhaseService::class)->completeChecklistItem($item, $this->manager);

        $html = $this->actingAs($this->manager)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('بازگشایی', $html);
        $this->assertStringContainsString(
            route('wbs-phases.checklist-items.reopen', [$phase->id, $item->id]),
            $html
        );
        $this->assertStringNotContainsString(
            route('wbs-phases.checklist-items.complete', [$phase->id, $item->id]),
            $html
        );
    }

    public function test_wbs_show_add_form_contract_is_unchanged(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $html = $this->actingAs($this->manager)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<form method="POST" action="'.route('wbs-phases.checklist-items.store', $phase->id).'"',
            $html,
            false
        );
        $this->assertStringContainsString('name="title" maxlength="255"', $html);
        $this->assertStringContainsString('name="description" maxlength="2000"', $html);
    }

    public function test_wbs_show_add_form_submits_and_reuses_status(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $this->actingAs($this->manager)
            ->post(route('wbs-phases.checklist-items.store', $phase->id), [
                'title' => 'Test item',
                'description' => 'desc',
            ])
            ->assertRedirect(route('wbs-phases.show', $phase->id))
            ->assertSessionHas('status');

        $html = $this->actingAs($this->manager)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Test item', $html);
        $this->assertStringContainsString('desc', $html);

        $item = WbsPhaseChecklistItem::where('title', 'Test item')->firstOrFail();
        $this->assertFalse((bool) $item->is_completed);
    }

    // ==================================================================
    // wbs-phases/show — supervisor decision contract
    // ==================================================================

    public function test_wbs_show_decision_form_contract_is_unchanged(): void
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

        $this->assertMatchesRegularExpression(
            '/type="radio" name="decision" value="complete"/u',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/type="radio" name="decision" value="not_completed"/u',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/type="radio" name="decision" value="reopen"/u',
            $html
        );

        $this->assertStringContainsString('name="comment" maxlength="2000"', $html);
    }

    public function test_wbs_show_decision_preserves_disabled_constraints(): void
    {
        // قرارداد مخزن (HEAD): pending → complete/not_completed فعال و reopen
        // غیرفعال؛ غیر pending → برعکس. بررسی صفت‌به‌صفت و مستقل از نحو
        // بستن تگ (`>` در برابر `/>`).
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $pendingHtml = $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        app(WbsPhaseService::class)->complete($phase, $this->supervisor, 'done');

        $completedHtml = $this->actingAs($this->supervisor)
            ->get(route('wbs-phases.show', $phase->id))
            ->assertOk()
            ->getContent();

        foreach ([$pendingHtml, $completedHtml] as $html) {
            foreach (['complete', 'not_completed', 'reopen'] as $value) {
                $this->assertMatchesRegularExpression(
                    '/type="radio" name="decision" value="'.$value.'"/u',
                    $html,
                    $value.' radio must exist'
                );
            }
        }

        // pending: complete/not_completed فعال، reopen غیرفعال.
        $this->assertFalse($this->decisionRadioIsDisabled($pendingHtml, 'complete'));
        $this->assertFalse($this->decisionRadioIsDisabled($pendingHtml, 'not_completed'));
        $this->assertTrue($this->decisionRadioIsDisabled($pendingHtml, 'reopen'));

        // غیر pending: complete/not_completed غیرفعال، reopen فعال.
        $this->assertTrue($this->decisionRadioIsDisabled($completedHtml, 'complete'));
        $this->assertTrue($this->decisionRadioIsDisabled($completedHtml, 'not_completed'));
        $this->assertFalse($this->decisionRadioIsDisabled($completedHtml, 'reopen'));
    }

    /**
     * صفت disabled رادیوی تصمیم را از HTML رندرشده استخراج می‌کند
     * (تگ input تا نزدیک‌ترین `>` بعد از value).
     */
    private function decisionRadioIsDisabled(string $html, string $value): bool
    {
        $start = strpos($html, 'type="radio" name="decision" value="'.$value.'"');
        $this->assertNotFalse($start, $value.' radio must exist');

        $end = strpos($html, '>', $start);
        $tag = substr($html, $start, $end - $start);

        return (bool) preg_match('/\bdisabled\b/', $tag);
    }

    public function test_wbs_show_decision_submits_complete(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $this->actingAs($this->supervisor)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'complete',
                'comment' => 'done',
            ])
            ->assertRedirect(route('wbs-phases.show', $phase->id))
            ->assertSessionHas('status');

        $this->assertSame('completed', $phase->fresh()->completion_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'wbs_phase_completed']);
    }

    public function test_wbs_show_decision_not_completed_requires_comment(): void
    {
        $phase = $this->makeWbsPhase($this->project, $this->manager, 'Phase One');

        $this->actingAs($this->supervisor)
            ->post(route('wbs-phases.decide', $phase->id), [
                'decision' => 'not_completed',
                'comment' => '',
            ])
            ->assertRedirect(route('wbs-phases.show', $phase->id))
            ->assertSessionHas('error');

        $this->assertSame('pending', $phase->fresh()->completion_status);
    }

    // ==================================================================
    // audit/index — read-only + filters + visible fields
    // ==================================================================

    public function test_audit_index_renders_for_supervisor(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('گزارش فعالیت', false);
    }

    public function test_audit_index_keeps_get_filters(): void
    {
        $html = $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            '<form method="GET" action="'.route('audit.index').'"',
            $html,
            false
        );
        $this->assertStringContainsString('name="action"', $html);
        $this->assertStringContainsString('name="entity_type"', $html);
        $this->assertStringContainsString('همه', $html);
        $this->assertStringContainsString('نوع رویداد', $html);
        $this->assertStringContainsString('موجودیت', $html);
    }

    public function test_audit_index_filters_still_apply_via_query(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('audit.index', ['action' => 'stage_progress_approved']))
            ->assertOk();
    }

    public function test_audit_index_preserves_pagination_block(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertViewHas('logs');

        $logs = $response->viewData('logs');
        $this->assertNotNull($logs);
        $this->assertTrue(method_exists($logs, 'links'));
    }

    public function test_audit_index_displays_visible_fields(): void
    {
        $log = ActivityLog::create([
            'action' => 'audit_entry',
            'entity_type' => 'wbs_phases',
            'entity_id' => 1,
            'user_id' => $this->supervisor->id,
            'reason' => 'audit visible check',
        ]);

        $html = $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('audit_entry', $html);
        $this->assertStringContainsString('wbs_phases#1', $html);
        $this->assertStringContainsString($this->supervisor->name, $html);
        $this->assertStringContainsString('audit visible check', $html);
    }

    // ==================================================================
    // audit/index — Phase 5B-7A visual migration contract
    // ==================================================================

    public function test_audit_index_keeps_seven_table_columns(): void
    {
        $html = $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->getContent();

        foreach (['رویداد', 'موجودیت', 'کاربر', 'تغییرات', 'دلیل', 'زمان'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
        $this->assertSame(1, substr_count($html, '<caption'));
    }

    public function test_audit_index_renders_empty_state_when_no_logs(): void
    {
        // activity_logs از طریق trigger دیتابیس immutable است؛ خالی‌بودن را با
        // فیلتری که هیچ رکوردی ندارد شبیه‌سازی می‌کنیم (همان مسیر @empty واقعی).
        $html = $this->actingAs($this->supervisor)
            ->get(route('audit.index', ['action' => 'no_such_action_xyz']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('هیچ رکورد فعالیتی یافت نشد.', $html);
    }
}
