<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3 — Read-only Kanban Board (Board Design Gate).
 *
 * The board is a pure PRESENTATION layer over tasks.index data. These tests
 * pin exactly the gate contract and nothing more:
 *   §3  route is GET-only and read-only (no mutation route exists)
 *   §4  authorization REUSES TaskPolicy/ProjectScopeService/TaskScopeService
 *       — no new policy, no new permission, no behavior drift vs tasks.index
 *   §7  9 real statuses collapse to the 5 owner-approved columns
 *   §8  Review Tier labels render for the three review statuses
 *   §9  needs_rework sits in «در حال انجام» and is visually distinguished
 *   §10 cancelled is its own collapsible column and is NOT hidden
 *   §12 column counts come from the real filtered query
 *   §13 ordering is planned_due_at (nulls last)
 *   §14/§16 filters are real, server-side, and Status filters the REAL
 *       status — never the presentation group
 *   §17 empty states for an empty column and an empty result set
 *   §18 no KPI/SLA/workload/progress summary is rendered
 *   §22 responsive: stacked on mobile (no fixed-width kanban scroll trap)
 */
class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private Project $projectA;

    private Project $projectB;

    private User $admin;

    private User $supervisorS;

    private User $supervisorT;

    protected function setUp(): void
    {
        parent::setUp();

        // InitialTmsSeeder slice (catalog unchanged — mirror of the sibling
        // V1.11 suites so permission/role state is identical).
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
        Role::findOrCreate('project_manager', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage projects', 'view reports',
        ]);

        $this->admin = $this->makeUserWithRole('admin');

        $this->projectA = Project::factory()->create(['name' => 'Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Project B']);

        $this->supervisorS = $this->makeUserWithRole('supervisor');
        $this->supervisorT = $this->makeUserWithRole('supervisor');

        $membershipService = app(ProjectMembershipService::class);
        $membershipService->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $this->supervisorS->id, assigned_by: $this->admin->id),
            $this->admin
        );
        $membershipService->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectB->id, user_id: $this->supervisorT->id, assigned_by: $this->admin->id),
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

    // ==================================================================
    // §3 — Route: GET, read-only
    // ==================================================================

    public function test_board_route_requires_authentication(): void
    {
        $this->get(route('tasks.board'))->assertRedirect(route('login'));
    }

    public function test_board_route_responds_ok_for_authenticated_user(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk();
    }

    public function test_board_is_get_only_and_has_no_mutation_routes(): void
    {
        // The board declares exactly one GET route and no write verb.
        $boardRoute = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'tasks.board');

        $this->assertNotNull($boardRoute);
        $this->assertSame(['GET', 'HEAD'], $boardRoute->methods());
    }

    public function test_no_board_mutation_route_exists(): void
    {
        // Guard: the board adds zero write surface — no POST/PUT/PATCH/DELETE
        // route name may reference the board.
        $boardWrites = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() !== null
                && str_contains($route->getName(), 'board')
                && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));

        $this->assertCount(0, $boardWrites);
    }

    public function test_board_board_uri_is_not_swallowed_by_tasks_show(): void
    {
        // Regression guard for route precedence: /tasks/board must hit the
        // board controller, never be treated as {task} = 'board'.
        $this->actingAs($this->admin)
            ->get('/tasks/board')
            ->assertOk()
            ->assertViewIs('tasks.board');
    }

    // ==================================================================
    // §4 — Authorization: existing surfaces reused, no drift
    // ==================================================================

    public function test_admin_sees_all_projects_on_board(): void
    {
        $this->makeTask($this->projectA, 'Board Task A');
        $this->makeTask($this->projectB, 'Board Task B');

        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('Board Task A')
            ->assertSee('Board Task B');
    }

    public function test_supervisor_board_scope_matches_index_scope(): void
    {
        $this->makeTask($this->projectA, 'Scoped Task A');
        $this->makeTask($this->projectB, 'Scoped Task B');

        $board = $this->actingAs($this->supervisorS)->get(route('tasks.board'))->assertOk();
        $index = $this->actingAs($this->supervisorS)->get(route('tasks.index'))->assertOk();

        // Identical retrieval semantics — board is a second VIEW, not a
        // second authorization model.
        $this->assertStringContainsString('Scoped Task A', $board->getContent());
        $this->assertStringNotContainsString('Scoped Task B', $board->getContent());
        $this->assertStringContainsString('Scoped Task A', $index->getContent());
        $this->assertStringNotContainsString('Scoped Task B', $index->getContent());
    }

    public function test_cross_project_supervisor_matrix_on_board(): void
    {
        $this->makeTask($this->projectA, 'Matrix A');
        $this->makeTask($this->projectB, 'Matrix B');

        $this->actingAs($this->supervisorS)->get(route('tasks.board'))
            ->assertOk()->assertSee('Matrix A')->assertDontSee('Matrix B');

        $this->actingAs($this->supervisorT)->get(route('tasks.board'))
            ->assertOk()->assertSee('Matrix B')->assertDontSee('Matrix A');
    }

    public function test_ended_supervisor_membership_removes_board_visibility(): void
    {
        $this->makeTask($this->projectA, 'Ended Membership Task');

        app(ProjectMembershipService::class)->endActiveSupervisor($this->projectA, $this->admin);

        $this->actingAs($this->supervisorS)->get(route('tasks.board'))
            ->assertOk()
            ->assertDontSee('Ended Membership Task');
    }

    public function test_contractor_isolation_applies_to_board(): void
    {
        $workerA = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);

        $this->makeTask($this->projectA, 'Contractor Visible Task');
        $this->makeTask($this->projectB, 'Contractor Foreign Task');

        $this->actingAs($workerA)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('Contractor Visible Task')
            ->assertDontSee('Contractor Foreign Task');
    }

    // ==================================================================
    // §7 — Status grouping (9 statuses → 5 columns)
    // ==================================================================

    public function test_board_renders_all_five_columns(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('آماده شروع')
            ->assertSee('در حال انجام')
            ->assertSee('در انتظار بررسی')
            ->assertSee('تأیید شده')
            ->assertSee('لغو شده');
    }

    public function test_status_grouping_matrix(): void
    {
        // Titles are deliberately collision-free tokens (no title is a
        // substring of another) so strpos-based grouping assertions stay
        // unambiguous.
        $matrix = [
            'draft' => ['T-DRAFT', 'backlog'],
            'assigned' => ['T-ASSIGNED', 'backlog'],
            'in_progress' => ['T-INPROGRESS', 'in_progress'],
            'needs_rework' => ['T-REWORK', 'in_progress'],
            'submitted_for_review' => ['T-SUBMITTED', 'review'],
            'under_review' => ['T-UNDERREVIEW', 'review'],
            'supervisor_approved' => ['T-SUPAPPROVED', 'review'],
            'approved' => ['T-APPROVED', 'approved'],
            'cancelled' => ['T-CANCELLED', 'cancelled'],
        ];

        foreach ($matrix as $status => [$title, $column]) {
            $this->makeTask($this->projectA, $title, $status);
        }

        $response = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk();
        $html = $response->getContent();

        // All 9 tasks render (nothing is hidden or dropped).
        foreach ($matrix as $status => [$title, $column]) {
            $this->assertStringContainsString($title, $html, "Task [{$title}] must render.");
        }

        // Structural grouping: each task must occur exactly inside its
        // canonical column and in no other column.
        foreach ($matrix as $status => [$title, $expectedColumn]) {
            foreach (array_keys(BoardColumns::COLUMNS) as $column) {
                $occurrences = $this->occurrencesInSection($html, 'board-column-'.$column, $title);

                if ($column === $expectedColumn) {
                    $this->assertGreaterThanOrEqual(1, $occurrences, "Task [{$title}] must be in column [{$column}].");
                } else {
                    $this->assertSame(0, $occurrences, "Task [{$title}] must NOT be in column [{$column}].");
                }
            }
        }
    }

    // ==================================================================
    // §8 — Review Tier labels
    // ==================================================================

    public function test_review_tier_labels_render_for_review_statuses(): void
    {
        $this->makeTask($this->projectA, 'Tier Submitted', 'submitted_for_review');
        $this->makeTask($this->projectA, 'Tier UnderReview', 'under_review');
        $this->makeTask($this->projectA, 'Tier SupApproved', 'supervisor_approved');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertStringContainsString('در انتظار بررسی سرپرست', $html);
        $this->assertStringContainsString('در حال بررسی سرپرست', $html);
        $this->assertStringContainsString('در انتظار تأیید کارفرما', $html);
    }

    public function test_review_tier_absent_outside_review_column(): void
    {
        $this->makeTask($this->projectA, 'Plain Backlog Task', 'draft');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertStringNotContainsString('در انتظار بررسی سرپرست', $html);
    }

    // ==================================================================
    // §9 — needs_rework: in «در حال انجام», visually distinguished
    // ==================================================================

    public function test_needs_rework_lives_in_the_in_progress_column(): void
    {
        $this->makeTask($this->projectA, 'T-REWORK-ONLY', 'needs_rework');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // Present in «در حال انجام» and in NO other column.
        $this->assertGreaterThanOrEqual(
            1,
            $this->occurrencesInSection($html, 'board-column-in_progress', 'T-REWORK-ONLY')
        );

        foreach (['backlog', 'review', 'approved', 'cancelled'] as $other) {
            $this->assertSame(
                0,
                $this->occurrencesInSection($html, 'board-column-'.$other, 'T-REWORK-ONLY'),
                "needs_rework task must not appear in column [{$other}]."
            );
        }

        // Distinguished by TEXT (not color alone — gate §25).
        $this->assertStringContainsString('بازگشت خورده', $html);
    }

    public function test_needs_rework_card_is_visually_distinguished(): void
    {
        $this->makeTask($this->projectA, 'T-REWORK-CARD', 'needs_rework');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // A11y-safe distinction: an explicit rework badge label is present,
        // in addition to the status badge's color.
        $this->assertStringContainsString('بازگشت خورده', $html);
        $this->assertStringContainsString('ring-danger-200', $html);
    }

    // ==================================================================
    // §10 — cancelled: own column, collapsible, NOT hidden
    // ==================================================================

    public function test_cancelled_column_is_collapsible_and_tasks_remain_visible(): void
    {
        $this->makeTask($this->projectA, 'Cancelled Visible Task', 'cancelled');

        $response = $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('Cancelled Visible Task');

        $html = $response->getContent();

        // Collapsible affordance is only on the cancelled column.
        $this->assertStringContainsString('aria-controls="board-column-cancelled-body"', $html);
        $this->assertStringNotContainsString('aria-controls="board-column-backlog-body"', $html);

        // The cancelled task is NOT hidden — the board never hides rows.
        $this->assertStringContainsString('Cancelled Visible Task', $html);
    }

    // ==================================================================
    // §12 — Column counts come from the real filtered query
    // ==================================================================

    public function test_column_counts_reflect_filtered_query(): void
    {
        $draftOne = $this->makeTask($this->projectA, 'Count Draft One', 'draft');
        $draftTwo = $this->makeTask($this->projectA, 'Count Draft Two', 'draft');
        $approvedOne = $this->makeTask($this->projectA, 'Count Approved One', 'approved');

        // Unfiltered: backlog=2 cards, approved=1 card. Counted by unique
        // task detail links (one link per card), so markup repetition inside
        // a card cannot inflate the number.
        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertSame(2, $this->taskLinksInSection($html, 'board-column-backlog', [$draftOne->id, $draftTwo->id]));
        $this->assertSame(1, $this->taskLinksInSection($html, 'board-column-approved', [$approvedOne->id]));
        $this->assertSame(0, $this->taskLinksInSection($html, 'board-column-review', [$draftOne->id, $draftTwo->id, $approvedOne->id]));
    }

    public function test_column_counts_respect_the_project_filter(): void
    {
        $taskA = $this->makeTask($this->projectA, 'Filter Count A', 'draft');
        $taskB = $this->makeTask($this->projectB, 'Filter Count B', 'draft');

        $html = $this->actingAs($this->admin)
            ->get(route('tasks.board', ['project' => $this->projectA->id]))
            ->assertOk()
            ->assertSee('Filter Count A')
            ->assertDontSee('Filter Count B')
            ->getContent();

        $this->assertSame(1, $this->taskLinksInSection($html, 'board-column-backlog', [$taskA->id]));
        $this->assertSame(0, $this->taskLinksInSection($html, 'board-column-backlog', [$taskB->id]));
    }

    // ==================================================================
    // §13 — Ordering: planned_due_at ascending, nulls last
    // ==================================================================

    public function test_tasks_order_by_planned_due_at_with_nulls_last(): void
    {
        $this->makeTask($this->projectA, 'Due Later', 'assigned', now()->addDays(10)->toDateTimeString());
        $this->makeTask($this->projectA, 'Due Sooner', 'assigned', now()->addDays(2)->toDateTimeString());
        $this->makeTask($this->projectA, 'No Due Date', 'assigned', null);

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $sooner = strpos($html, 'Due Sooner');
        $later = strpos($html, 'Due Later');
        $none = strpos($html, 'No Due Date');

        $this->assertNotFalse($sooner);
        $this->assertNotFalse($later);
        $this->assertNotFalse($none);

        // Ascending due date first, undated tasks pushed to the end.
        $this->assertLessThan($later, $sooner);
        $this->assertLessThan($none, $later);
    }

    // ==================================================================
    // §14/§16 — Filters are real and server-side
    // ==================================================================

    public function test_search_filter_acts_on_the_query(): void
    {
        $this->makeTask($this->projectA, 'Searchable Needle Task', 'draft');
        $this->makeTask($this->projectA, 'Unrelated Haystack Task', 'draft');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['search' => 'Needle']))
            ->assertOk()
            ->assertSee('Searchable Needle Task')
            ->assertDontSee('Unrelated Haystack Task');
    }

    public function test_project_filter_acts_on_the_query(): void
    {
        $this->makeTask($this->projectA, 'Project A Board Task', 'draft');
        $this->makeTask($this->projectB, 'Project B Board Task', 'draft');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['project' => $this->projectB->id]))
            ->assertOk()
            ->assertSee('Project B Board Task')
            ->assertDontSee('Project A Board Task');
    }

    public function test_status_filter_acts_on_the_real_status_not_the_group(): void
    {
        // in_progress and needs_rework share the «در حال انجام» column.
        $this->makeTask($this->projectA, 'True In Progress', 'in_progress');
        $this->makeTask($this->projectA, 'True Needs Rework', 'needs_rework');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['status' => 'in_progress']))
            ->assertOk()
            ->assertSee('True In Progress')
            ->assertDontSee('True Needs Rework');

        // And the reverse — filtering needs_rework must NOT pull in_progress.
        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['status' => 'needs_rework']))
            ->assertOk()
            ->assertSee('True Needs Rework')
            ->assertDontSee('True In Progress');
    }

    public function test_status_filter_is_review_aware(): void
    {
        // The three review statuses share one column but are distinct real
        // statuses — a status filter must select exactly one of them.
        $this->makeTask($this->projectA, 'Review Submitted Only', 'submitted_for_review');
        $this->makeTask($this->projectA, 'Review Under Only', 'under_review');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['status' => 'under_review']))
            ->assertOk()
            ->assertSee('Review Under Only')
            ->assertDontSee('Review Submitted Only');
    }

    public function test_priority_filter_acts_on_the_query(): void
    {
        $this->makeTask($this->projectA, 'Urgent Priority Task', 'draft', null, 'urgent');
        $this->makeTask($this->projectA, 'Low Priority Task', 'draft', null, 'low');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['priority' => 'urgent']))
            ->assertOk()
            ->assertSee('Urgent Priority Task')
            ->assertDontSee('Low Priority Task');
    }

    public function test_filters_compose_and_are_deep_linkable(): void
    {
        $this->makeTask($this->projectA, 'Combined Match', 'in_progress', null, 'urgent');
        $this->makeTask($this->projectA, 'Combined Miss Status', 'draft', null, 'urgent');
        $this->makeTask($this->projectA, 'Combined Miss Priority', 'in_progress', null, 'low');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', [
                'project' => $this->projectA->id,
                'status' => 'in_progress',
                'priority' => 'urgent',
            ]))
            ->assertOk()
            ->assertSee('Combined Match')
            ->assertDontSee('Combined Miss Status')
            ->assertDontSee('Combined Miss Priority');
    }

    public function test_invalid_filter_values_do_not_widen_the_query(): void
    {
        $this->makeTask($this->projectA, 'Valid Status Task', 'draft');

        // An unknown status is ignored (not treated as a wildcard).
        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['status' => 'this_is_not_a_status']))
            ->assertOk()
            ->assertSee('Valid Status Task');
    }

    // ==================================================================
    // §17 — Empty states
    // ==================================================================

    public function test_empty_column_shows_empty_state(): void
    {
        // Only a draft task exists — the approved column is empty.
        $this->makeTask($this->projectA, 'Only Backlog Task', 'draft');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // The approved column renders its own per-column empty state.
        $this->assertGreaterThanOrEqual(
            1,
            $this->occurrencesInSection($html, 'board-column-approved', 'تسکی در این مرحله وجود ندارد')
        );
        // ...and it contains no task cards.
        $this->assertSame(0, $this->occurrencesInSection($html, 'board-column-approved', 'Only Backlog Task'));
    }

    public function test_fully_empty_result_shows_global_empty_state(): void
    {
        // No tasks exist anywhere. The 5-column skeleton still renders (each
        // column empty) and the global empty banner is shown above it.
        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertStringContainsString('نتیجه‌ای یافت نشد', $html);
        $this->assertStringContainsString('board-column-backlog', $html);
        // Every column shows its per-column empty state.
        foreach (['backlog', 'in_progress', 'review', 'approved', 'cancelled'] as $column) {
            $this->assertGreaterThanOrEqual(
                1,
                $this->occurrencesInSection($html, 'board-column-'.$column, 'تسکی در این مرحله وجود ندارد'),
                "Column [{$column}] must show its empty state."
            );
        }
    }

    public function test_filter_with_no_matches_shows_global_empty_state(): void
    {
        $this->makeTask($this->projectA, 'Existing For Filter Miss', 'draft');

        $this->actingAs($this->admin)
            ->get(route('tasks.board', ['search' => 'zzz-no-match-zzz']))
            ->assertOk()
            ->assertSee('نتیجه‌ای یافت نشد')
            ->assertDontSee('Existing For Filter Miss');
    }

    // ==================================================================
    // §18 — Board is not a dashboard
    // ==================================================================

    public function test_board_renders_no_kpi_or_summary_widgets(): void
    {
        $this->makeTask($this->projectA, 'Widget Probe Task', 'in_progress');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // Forbidden PRESENTATION surfaces (gate §13/§18). Note: the literal
        // token "progress" is intentionally not probed — it is a substring of
        // the legitimate `in_progress` status value in the filter options;
        // what is forbidden is a progress WIDGET, asserted by the Persian
        // widget labels and the ring/percentage markers below.
        foreach ([
            'SLA', 'نقض SLA', 'workload', 'بار کاری',
            'پیشرفت', 'درصد پیشرفت', 'درصد',
            'زمان باقی‌مانده', 'countdown', 'شمارش معکوس',
            'hours', 'ساعت کاری', 'بارگذاری',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "Board must not render [{$forbidden}].");
        }

        // No progress ring / percentage markup from the Phase 1 components.
        $this->assertStringNotContainsString('x-ui.ring', $html);
        $this->assertStringNotContainsString('x-ui.progress', $html);
    }

    // ==================================================================
    // §20 — Card click navigates to the existing detail route
    // ==================================================================

    public function test_card_links_to_task_detail_route(): void
    {
        $task = $this->makeTask($this->projectA, 'Detail Link Task', 'draft');

        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('href="'.route('tasks.show', $task->id).'"', false);
    }

    // ==================================================================
    // §22 — Responsive: no mobile horizontal-scroll trap
    // ==================================================================

    public function test_board_uses_stacked_mobile_layout_not_fixed_columns(): void
    {
        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // The board grid is a 5-column grid only at the xl breakpoint; below
        // that it is a single stacked column (no forced horizontal scroll).
        $this->assertStringContainsString('xl:grid xl:grid-cols-5', $html);
        $this->assertStringNotContainsString('overflow-x-auto', $html);
        $this->assertStringNotContainsString('min-w-[240px]', $html);
    }

    // ==================================================================
    // §19 — View switcher
    // ==================================================================

    public function test_board_links_back_to_table_view(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('href="'.route('tasks.index').'"', false);
    }

    public function test_table_links_to_board_view(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('href="'.route('tasks.board').'"', false);
    }

    // ==================================================================
    // §27 — Read-only: board triggers no state change
    // ==================================================================

    public function test_board_get_does_not_change_task_state(): void
    {
        $task = $this->makeTask($this->projectA, 'Immutable State Task', 'assigned');

        $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk();

        $this->assertSame('assigned', $task->fresh()->status);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'assigned']);
    }

    public function test_board_renders_assignee_from_eager_loaded_relation(): void
    {
        $task = $this->makeTask($this->projectA, 'Assignee Probe Task', 'assigned');
        $assignee = User::factory()->create(['name' => 'Board Assignee Name', 'contractor_id' => null]);
        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('Board Assignee Name');
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Count occurrences of a needle inside one board column's section —
     * measured from that column's opening tag to the NEXT column's opening
     * tag (or end of document). Section bounds use the exact section-id
     * pattern 'id="board-column-<key>"' — NOT a loose 'id="board-column-'
     * prefix, which would wrongly match the '-body' / '-title' ids that live
     * INSIDE a column. This makes grouping assertions structural rather than
     * positional, so they survive markup changes above/below the grid.
     */
    private function occurrencesInSection(string $html, string $columnId, string $needle): int
    {
        $start = strpos($html, 'id="'.$columnId.'"');
        if ($start === false) {
            return 0;
        }

        // Bound this section by the next canonical column section id.
        $nextColumn = false;
        foreach (array_keys(BoardColumns::COLUMNS) as $key) {
            if ($key === $columnId) {
                continue;
            }
            $pos = strpos($html, 'id="board-column-'.$key.'"', $start + 1);
            if ($pos !== false && ($nextColumn === false || $pos < $nextColumn)) {
                $nextColumn = $pos;
            }
        }

        $section = $nextColumn === false ? substr($html, $start) : substr($html, $start, $nextColumn - $start);

        return substr_count($section, $needle);
    }

    /**
     * Count how many of the given task ids have a detail link inside a column
     * section. Each card renders exactly one link to tasks.show, so this is a
     * card count that is immune to markup repetition inside a card.
     *
     * @param  list<int>  $taskIds
     */
    private function taskLinksInSection(string $html, string $columnId, array $taskIds): int
    {
        $count = 0;
        foreach ($taskIds as $id) {
            if ($this->occurrencesInSection($html, $columnId, 'href="'.route('tasks.show', $id).'"') > 0) {
                $count++;
            }
        }

        return $count;
    }
}
