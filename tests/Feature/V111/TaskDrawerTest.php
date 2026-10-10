<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Approval;
use App\Models\Module;
use App\Models\ModuleStage;
use App\Models\Project;
use App\Models\SlaRecord;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\User;
use App\Models\WbsPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 4 — Read-only Task Drawer (Drawer Gate).
 *
 * The Drawer is a second, strictly read-only PRESENTATION of one Task that
 * reuses the existing tasks.show route, controller and authorization as its
 * SOLE data source (gate §3/§4/§29 — Option B, fetch on open):
 *   §5/§29  drawer content is served by GET /tasks/{task}?view=drawer —
 *           no new route, no new endpoint, no new controller/service/policy
 *   §4      TaskPolicy::view (+ ProjectScope + contractor isolation) stays
 *           the single canonical authorization; ?view=drawer never grants
 *           anything the full page would not
 *   §7      header: real Task ID, title, project, close affordance
 *   §8/§9   status uses the canonical Phase 3 presentation; the review tier
 *           labels are the approved semantics; needs_rework is distinguished
 *   §10     identity: project / module-stage only when the relation exists
 *   §11     dependencies are read-only metadata, no actions
 *   §12     documents: metadata only — no download/upload/delete invented
 *   §13     approvals: immutable history, no actions
 *   §14     SLA: only the existing authoritative is_breached value
 *   §15     WBS: phase NAME only — no task-level checklist/progress
 *   §16     activity log: compact read-only history
 *   §17     description: read-only when present
 *   §18     HARD GATE: zero mutation controls
 *   §19     full-detail link points at the canonical tasks.show route
 *   §20     board card opens the drawer (fetch-on-open)
 *   §21     loading / error states exist on the board shell
 *   §22     close affordances (button + backdrop + Escape) come from the
 *           Phase 1 foundation
 *   §23     a11y: dialog semantics, labelled close, status not color-only
 */
class TaskDrawerTest extends TestCase
{
    use RefreshDatabase;

    private Project $projectA;

    private Project $projectB;

    private User $admin;

    private User $supervisorS;

    protected function setUp(): void
    {
        parent::setUp();

        // InitialTmsSeeder slice — identical to the sibling V1.11 suites so
        // permission/role state is the same across Phase 3/4 tests.
        foreach ([
            'manage users', 'manage settings', 'manage projects', 'create tasks', 'assign tasks',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::findOrCreate('admin', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage users', 'manage settings', 'manage projects',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ]);

        $this->admin = $this->makeUserWithRole('admin');

        $this->projectA = Project::factory()->create(['name' => 'Drawer Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Drawer Project B']);

        $this->supervisorS = $this->makeUserWithRole('supervisor');

        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $this->supervisorS->id, assigned_by: $this->admin->id),
            $this->admin
        );
    }

    private function makeTask(Project $project, string $title, string $status = 'assigned', ?string $dueAt = null, string $priority = 'normal'): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $project->contractor_id,
            'contract_id' => $project->contract_id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'planned_due_at' => $dueAt,
        ]);
    }

    private function makeUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function drawerUrl(Task $task): string
    {
        return route('tasks.show', ['task' => $task->id, 'view' => 'drawer']);
    }

    // ==================================================================
    // §5/§29 — Drawer endpoint: existing route, drawer presentation
    // ==================================================================

    public function test_drawer_reuses_the_existing_tasks_show_route(): void
    {
        // No new route exists for the drawer: it is the SAME named route,
        // only the view query param differs.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'tasks.show');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());

        // No drawer-specific route name was introduced.
        $this->assertNull(collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() !== null && str_contains($r->getName(), 'drawer')));
    }

    public function test_drawer_endpoint_returns_the_drawer_partial(): void
    {
        $task = $this->makeTask($this->projectA, 'Drawer Partial Task', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertViewIs('components.tasks.task-drawer-content');
    }

    public function test_drawer_contains_the_real_task_id_and_title(): void
    {
        $task = $this->makeTask($this->projectA, 'Identity Probe Task', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('#'.$task->id, false)
            ->assertSee('Identity Probe Task');
    }

    public function test_drawer_does_not_render_the_full_detail_page(): void
    {
        // ?view=drawer must render ONLY the drawer representation — never the
        // full tasks.show page (gate §24 "renders only the Drawer
        // representation").
        $task = $this->makeTask($this->projectA, 'Drawer Not Full Page', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertViewIs('components.tasks.task-drawer-content')
            ->assertDontSee('id="btn-technical-approve"', false)
            ->assertDontSee('id="btn-final-approve"', false);
    }

    public function test_full_detail_page_still_renders_without_the_drawer_param(): void
    {
        // Regression guard (gate §4/§24): the existing full detail page is
        // untouched when view != drawer.
        $task = $this->makeTask($this->projectA, 'Full Page Preserved', 'in_progress');

        $this->actingAs($this->admin)
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->assertViewIs('tasks.show')
            ->assertSee('Full Page Preserved');
    }

    public function test_unknown_view_value_falls_back_to_the_full_page(): void
    {
        // Only the literal 'drawer' switches to the drawer partial — any other
        // value keeps the canonical full-page behavior.
        $task = $this->makeTask($this->projectA, 'Fallback Full Page', 'in_progress');

        $this->actingAs($this->admin)
            ->get(route('tasks.show', ['task' => $task->id, 'view' => 'anything-else']))
            ->assertOk()
            ->assertViewIs('tasks.show');
    }

    // ==================================================================
    // §24 — Rendering: Board exposes the drawer interaction
    // ==================================================================

    public function test_board_renders_task_cards(): void
    {
        $task = $this->makeTask($this->projectA, 'Board Card Present', 'draft');

        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('Board Card Present');
    }

    public function test_board_card_exposes_the_drawer_interaction(): void
    {
        // The card carries the drawer URL as a data attribute so Alpine can
        // open the drawer instead of navigating (gate §20).
        $task = $this->makeTask($this->projectA, 'Drawer Trigger Task', 'draft');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'data-drawer-url="'.$this->drawerUrl($task).'"',
            $html
        );
        // The real detail link is preserved as the no-JS/keyboard fallback.
        $this->assertStringContainsString('href="'.route('tasks.show', $task->id).'"', $html);
    }

    public function test_board_mounts_the_phase_one_drawer_foundation(): void
    {
        // The board hosts the Phase 1 drawer shell (gate §6/§22/§23) with the
        // loading and error states (gate §21).
        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // Drawer opens from the LEFT, desktop max width ~430px (gate §6).
        $this->assertStringContainsString('inset-y-0 left-0', $html);
        $this->assertStringContainsString('sm:max-w-[430px]', $html);

        // Event wiring: open / close through the foundation contract.
        $this->assertStringContainsString('open-task-drawer', $html);
        $this->assertStringContainsString('open-drawer', $html);
        $this->assertStringContainsString('close-drawer', $html);

        // Loading + error states (gate §21).
        $this->assertStringContainsString('در حال دریافت اطلاعات وظیفه', $html);
        $this->assertStringContainsString('تلاش دوباره', $html);

        // Dialog semantics + labelled close (gate §23).
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-label="بستن پنل جزئیات وظیفه"', $html);
    }

    // ==================================================================
    // §24 — Data: real available fields
    // ==================================================================

    public function test_drawer_renders_project_and_status(): void
    {
        $task = $this->makeTask($this->projectA, 'Data Fields Task', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('Drawer Project A')
            ->assertSee('در حال انجام'); // canonical Phase 3 status label
    }

    public function test_drawer_renders_assignee_from_active_assignment(): void
    {
        $task = $this->makeTask($this->projectA, 'Assignee Data Task', 'assigned');
        $assignee = User::factory()->create(['name' => 'Drawer Assignee Name', 'contractor_id' => null]);
        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('Drawer Assignee Name');
    }

    public function test_drawer_renders_priority_and_dates(): void
    {
        $task = $this->makeTask(
            $this->projectA,
            'Priority And Dates Task',
            'assigned',
            now()->addDays(5)->toDateTimeString(),
            'urgent'
        );

        // Phase 5B-1 (item F): the drawer renders the CANONICAL Persian
        // priority label (TaskPriority::label()) instead of the raw enum value —
        // the raw code is no longer the presentation. 'urgent' => «فوری».
        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('فوری')
            ->assertSee('تاریخ ایجاد')
            ->assertSee('موعد انجام');
    }

    public function test_drawer_renders_module_stage_when_the_relation_exists(): void
    {
        // §10: Module/Stage render only when the relation provides data.
        $module = Module::create([
            'project_id' => $this->projectA->id,
            'name' => 'Drawer Module',
            'code' => 'DM-01',
            'weight' => 20,
            'sort_order' => 1,
            'status' => 'active',
        ]);
        $stage = ModuleStage::create([
            'module_id' => $module->id,
            'stage_code' => 'coding',
            'name' => 'Drawer Stage Name',
            'weight' => 10,
            'sort_order' => 1,
        ]);
        $task = $this->makeTask($this->projectA, 'Module Stage Task', 'in_progress');
        // chk_tasks_module_consistency: module_stage_id requires module_id.
        $task->update(['module_stage_id' => $stage->id, 'module_id' => $module->id]);

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task->fresh()))
            ->assertOk()
            ->assertSee('Drawer Stage Name');
    }

    public function test_drawer_uses_the_empty_convention_for_missing_module_stage(): void
    {
        // §10: no invented fallback — the established empty value is shown.
        $task = $this->makeTask($this->projectA, 'No Stage Task', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('بدون مرحله');
    }

    public function test_drawer_renders_description_when_present(): void
    {
        // §17: read-only description from the authoritative field.
        $task = $this->makeTask($this->projectA, 'Described Task', 'in_progress');
        $task->update(['description' => 'A unique read-only description body.']);

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task->fresh()))
            ->assertOk()
            ->assertSee('A unique read-only description body.');
    }

    public function test_drawer_renders_wbs_phase_name_only(): void
    {
        // §15: the WBS phase NAME is allowed; no task checklist/progress.
        $phase = WbsPhase::create([
            'project_id' => $this->projectA->id,
            'name' => 'Drawer WBS Phase',
            'expected_output' => 'Deliverable output',
            'weight' => 10,
            'planned_duration' => 5,
            'duration_unit' => 'days',
            'status' => 'active',
        ]);
        $task = $this->makeTask($this->projectA, 'WBS Phase Task', 'in_progress');
        $task->update(['wbs_phase_id' => $phase->id]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task->fresh()))
            ->assertOk()
            ->assertSee('Drawer WBS Phase')
            ->getContent();

        // No task-level checklist / completion is invented from phase data.
        $this->assertStringNotContainsString('checklist', $html);
        $this->assertStringNotContainsString('درصد تکمیل', $html);
    }

    // ==================================================================
    // §24 — Status matrix (canonical Phase 3 presentation)
    // ==================================================================

    public function test_drawer_status_matrix(): void
    {
        $matrix = [
            'draft' => 'پیش‌نویس',
            'in_progress' => 'در حال انجام',
            'needs_rework' => 'نیاز به اصلاح',
            'submitted_for_review' => 'ارائه شده برای بررسی',
            'under_review' => 'در حال بررسی',
            'supervisor_approved' => 'تأیید ناظر',
            'approved' => 'تأیید نهایی',
            'cancelled' => 'لغو شده',
        ];

        foreach ($matrix as $status => $label) {
            $task = $this->makeTask($this->projectA, "Status Probe {$status}", $status);

            $this->actingAs($this->admin)
                ->get($this->drawerUrl($task))
                ->assertOk()
                ->assertSee($label, false);
        }
    }

    // ==================================================================
    // §24 — Review Tier (approved Phase 3 semantics)
    // ==================================================================

    public function test_drawer_review_tier_labels(): void
    {
        $tiers = [
            'submitted_for_review' => 'در انتظار بررسی سرپرست',
            'under_review' => 'در حال بررسی سرپرست',
            'supervisor_approved' => 'در انتظار تأیید کارفرما',
        ];

        foreach ($tiers as $status => $tier) {
            $task = $this->makeTask($this->projectA, "Tier Probe {$status}", $status);

            $this->actingAs($this->admin)
                ->get($this->drawerUrl($task))
                ->assertOk()
                ->assertSee($tier, false);
        }
    }

    public function test_drawer_review_tier_absent_outside_review_statuses(): void
    {
        $task = $this->makeTask($this->projectA, 'No Tier Task', 'draft');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertDontSee('در انتظار بررسی سرپرست')
            ->assertDontSee('در حال بررسی سرپرست')
            ->assertDontSee('در انتظار تأیید کارفرما');
    }

    public function test_needs_rework_is_visually_distinguished_by_text(): void
    {
        // §9/§23: the state is conveyed by text + icon, never color alone.
        $task = $this->makeTask($this->projectA, 'Rework Distinction Task', 'needs_rework');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('این وظیفه برای اصلاح بازگشته است');
    }

    // ==================================================================
    // §11/§12/§13/§14/§16 — Read-only supporting sections
    // ==================================================================

    public function test_drawer_renders_dependencies_as_read_only_metadata(): void
    {
        // §11: identify the predecessor; no dependency actions.
        $predecessor = $this->makeTask($this->projectA, 'Drawer Predecessor Task', 'draft');
        $task = $this->makeTask($this->projectA, 'Drawer Successor Task', 'in_progress');
        TaskDependency::create([
            'successor_task_id' => $task->id,
            'predecessor_task_id' => $predecessor->id,
            'dependency_type' => 'fs',
            'created_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('Drawer Predecessor Task')
            ->getContent();

        // No invented dependency status (e.g. "blocked") and no actions.
        $this->assertStringNotContainsString('مسدود', $html);
        $this->assertStringNotContainsString('حذف پیش‌نیاز', $html);
    }

    public function test_drawer_renders_document_metadata_without_download(): void
    {
        // §12: metadata only — NO download/upload/delete URL is invented.
        $task = $this->makeTask($this->projectA, 'Document Metadata Task', 'in_progress');
        $task->documents()->create([
            'original_name' => 'drawer-evidence.pdf',
            'stored_name' => 'stored/drawer-evidence.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('drawer-evidence.pdf')
            ->getContent();

        // No document action surface and no invented download link.
        $this->assertStringNotContainsString('download', $html);
        $this->assertStringNotContainsString('بارگذاری', $html);
    }

    public function test_drawer_renders_approvals_as_immutable_history(): void
    {
        // §13: read-only history, no approve/reject/revoke actions.
        $actor = User::factory()->create(['name' => 'Drawer Approver', 'contractor_id' => null]);
        $task = $this->makeTask($this->projectA, 'Approval History Task', 'under_review');
        Approval::create([
            'task_id' => $task->id,
            'approval_type' => 'technical',
            'sequence' => 1,
            'status' => 'approved',
            'requested_at' => now()->subDay(),
            'acted_by' => $actor->id,
            'acted_at' => now(),
            'comment' => 'Looks good',
        ]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('Drawer Approver')
            ->assertSee('تأیید فنی')
            ->getContent();

        $this->assertStringNotContainsString('رد کردن', $html);
        $this->assertStringNotContainsString('ابطال', $html);
    }

    public function test_drawer_exposes_only_the_existing_sla_breach_state(): void
    {
        // §14: the authoritative is_breached value is displayed; nothing is
        // calculated (no countdown, no remaining time, no percentage).
        $task = $this->makeTask($this->projectA, 'SLA Breach Task', 'in_progress');
        SlaRecord::create([
            'task_id' => $task->id,
            'sla_type' => 'resolution',
            'target_duration' => 120,
            'started_at' => now()->subDay(),
            'status' => 'breached',
            'is_breached' => true,
            'breached_at' => now(),
        ]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('نقض شده')
            ->getContent();

        $this->assertStringNotContainsString('زمان باقی‌مانده', $html);
        $this->assertStringNotContainsString('شمارش معکوس', $html);
        $this->assertStringNotContainsString('درصد', $html);
    }

    public function test_drawer_renders_activity_log_compactly(): void
    {
        // §16: compact, read-only, immutable history.
        $task = $this->makeTask($this->projectA, 'Activity Log Task', 'in_progress');
        $task->activityLogs()->create([
            'user_id' => $this->admin->id,
            'action' => 'task_created',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
        ]);

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('task_created')
            ->getContent();

        // No comment/note creation surface.
        $this->assertStringNotContainsString('افزودن نظر', $html);
        $this->assertStringNotContainsString('ثبت یادداشت', $html);
    }

    // ==================================================================
    // §19 — Full detail link
    // ==================================================================

    public function test_drawer_full_detail_link_points_to_the_canonical_route(): void
    {
        $task = $this->makeTask($this->projectA, 'Full Detail Link Task', 'in_progress');

        $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('مشاهده جزئیات کامل')
            ->assertSee('href="'.route('tasks.show', $task->id).'"', false);
    }

    // ==================================================================
    // §18 — Read-only HARD GATE: zero mutation controls
    // ==================================================================

    public function test_drawer_contains_no_mutation_controls(): void
    {
        $task = $this->makeTask($this->projectA, 'Read Only Audit Task', 'under_review');

        $html = $this->actingAs($this->admin)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->getContent();

        // No write surface of any kind in the drawer partial.
        foreach (['<form', '<textarea', '<select', '<button', 'type="submit"', 'method="POST"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "Drawer must not contain [{$forbidden}].");
        }

        // No mutation endpoint is referenced by the drawer. Asserted against
        // the real sub-route URIs (not bare words — the read-only history
        // section ids legitimately contain 'approvals'/'documents').
        $mutationRoutes = [
            route('tasks.start', $task->id),
            route('tasks.submit', $task->id),
            route('tasks.assign', $task->id),
            route('tasks.stage', $task->id),
            route('approvals.store', $task->id),
            route('documents.store', $task->id),
            route('tasks.dependencies.store', $task->id),
        ];

        foreach ($mutationRoutes as $mutationUrl) {
            $this->assertStringNotContainsString(
                $mutationUrl,
                $html,
                "Drawer must not reference mutation route [{$mutationUrl}]."
            );
        }
    }

    public function test_drawer_get_does_not_change_task_state(): void
    {
        $task = $this->makeTask($this->projectA, 'Immutable Drawer Task', 'assigned');

        $this->actingAs($this->admin)->get($this->drawerUrl($task))->assertOk();

        $this->assertSame('assigned', $task->fresh()->status);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'assigned']);
    }

    // ==================================================================
    // §4 — Authorization: the canonical path stays authoritative
    // ==================================================================

    public function test_drawer_requires_authentication(): void
    {
        $task = $this->makeTask($this->projectA, 'Auth Required Task', 'in_progress');

        $this->get($this->drawerUrl($task))->assertRedirect(route('login'));
    }

    public function test_supervisor_can_open_the_drawer_of_their_own_project(): void
    {
        $task = $this->makeTask($this->projectA, 'Supervisor Own Task', 'in_progress');

        $this->actingAs($this->supervisorS)
            ->get($this->drawerUrl($task))
            ->assertOk()
            ->assertSee('Supervisor Own Task');
    }

    public function test_cross_project_task_is_not_exposed_through_the_drawer(): void
    {
        // The supervisor has an ACTIVE membership on project A only. Project
        // B's task must stay unreachable via ?view=drawer — the drawer never
        // widens the canonical authorization (gate §4).
        $foreignTask = $this->makeTask($this->projectB, 'Foreign Project Task', 'in_progress');

        $this->actingAs($this->supervisorS)
            ->get($this->drawerUrl($foreignTask))
            ->assertForbidden();

        // And the full page agrees (no divergence between the two views).
        $this->actingAs($this->supervisorS)
            ->get(route('tasks.show', $foreignTask->id))
            ->assertForbidden();
    }

    public function test_contractor_isolation_applies_to_the_drawer(): void
    {
        $ownContractor = SyncedContractor::factory()->create();
        $otherContractor = SyncedContractor::factory()->create();

        $ownProject = Project::factory()->create(['name' => 'Own Contractor Project', 'contractor_id' => $ownContractor->id]);
        $otherProject = Project::factory()->create(['name' => 'Other Contractor Project', 'contractor_id' => $otherContractor->id]);

        $ownTask = $this->makeTask($ownProject, 'Contractor Own Drawer Task', 'in_progress');
        $foreignTask = $this->makeTask($otherProject, 'Contractor Foreign Drawer Task', 'in_progress');

        $worker = User::factory()->create(['contractor_id' => $ownContractor->id]);

        $this->actingAs($worker)
            ->get($this->drawerUrl($ownTask))
            ->assertOk()
            ->assertSee('Contractor Own Drawer Task');

        $this->actingAs($worker)
            ->get($this->drawerUrl($foreignTask))
            ->assertForbidden();
    }

    public function test_ended_supervisor_membership_blocks_the_drawer(): void
    {
        // An ended membership grants no scope — same rule as the board/index.
        $task = $this->makeTask($this->projectA, 'Ended Membership Drawer Task', 'in_progress');

        app(ProjectMembershipService::class)->endActiveSupervisor($this->projectA, $this->admin);

        $this->actingAs($this->supervisorS)
            ->get($this->drawerUrl($task))
            ->assertForbidden();
    }
}
