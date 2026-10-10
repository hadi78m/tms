<?php

namespace Tests\Feature\Ui;

use App\Domain\Enums\TaskPriority;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 5B-1 — UI/presentation continuation.
 *
 * Pins exactly the approved Phase 5B-1 items and nothing else:
 *   A/B header titles, C dashboard review metric, D/E/F canonical status and
 *   priority presentation, G real server-side Task Index filters, H removed
 *   broken document download link, I single flash rendering, L dynamic drawer
 *   aria-busy, P cancelled column collapsed by default.
 *
 * Focus management (J/K) is verified at the markup/contract level here; the
 * interactive Tab/Shift+Tab/Escape behavior itself is Alpine behaviour and is
 * not reproducible in a server-side test.
 */
class Phase5B1UiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = $this->makeUserWithRole('admin');
        $this->otherAdmin = User::factory()->create(['contractor_id' => null]);

        $this->project = Project::factory()->create(['name' => 'Phase 5B-1 Project']);
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function makeTask(string $title, string $status = 'assigned', string $priority = 'normal', ?Project $project = null): Task
    {
        $project ??= $this->project;

        return Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $project->contractor_id,
            'contract_id' => $project->contract_id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
        ]);
    }

    // ==================================================================
    // D/E/F — canonical presentation sources
    // ==================================================================

    public function test_status_presentation_is_the_canonical_9_status_map(): void
    {
        // Every real domain status must present a Persian label and a variant
        // drawn from the ui.badge vocabulary — no raw value ever leaks.
        foreach (BoardColumns::filterableStatuses() as $status) {
            $view = BoardColumns::statusView($status);

            $this->assertNotSame($status, $view['label'], "status {$status} has no Persian label");
            $this->assertMatchesRegularExpression('/[\x{0621}-\x{06CC}]/u', $view['label']);
            $this->assertContains($view['variant'], [
                'default', 'muted', 'success', 'warning', 'danger', 'info', 'primary',
            ]);
        }

        $this->assertCount(9, BoardColumns::filterableStatuses());
    }

    public function test_priority_presentation_has_persian_labels_and_variants(): void
    {
        $expected = [
            'low' => 'پایین',
            'normal' => 'متوسط',
            'high' => 'بالا',
            'urgent' => 'فوری',
        ];

        foreach (TaskPriority::cases() as $priority) {
            $this->assertSame($expected[$priority->value], $priority->label());
            $this->assertSame($expected[$priority->value], TaskPriority::viewOf($priority->value)['label']);
        }

        $this->assertSame('danger', TaskPriority::Urgent->variant());
        $this->assertSame('warning', TaskPriority::High->variant());

        // An unknown/legacy value is never invented into a Persian label.
        $this->assertSame('legacy-value', TaskPriority::viewOf('legacy-value')['label']);
        $this->assertSame('default', TaskPriority::viewOf('legacy-value')['variant']);
        $this->assertSame('', TaskPriority::viewOf(null)['label']);
    }

    // ==================================================================
    // P — cancelled column collapsed by default
    // ==================================================================

    public function test_board_column_starts_collapsed_when_asked(): void
    {
        $html = Blade::render(
            '<x-tasks.board-column column="cancelled" label="لغو شده" :tasks="collect()" variant="muted" :collapsible="true" :collapsed="true" />'
        );

        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="board-column-cancelled-body"', $html);
        $this->assertStringContainsString('style="display: none;"', $html);
    }

    public function test_board_column_starts_expanded_by_default(): void
    {
        $html = Blade::render(
            '<x-tasks.board-column column="review" label="در انتظار بررسی" :tasks="collect()" variant="warning" :collapsible="true" />'
        );

        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringNotContainsString('style="display: none;"', $html);
    }

    public function test_board_column_without_collapsible_has_no_toggle(): void
    {
        $html = Blade::render(
            '<x-tasks.board-column column="backlog" label="آماده شروع" :tasks="collect()" variant="default" />'
        );

        $this->assertStringNotContainsString('aria-controls=', $html);
        $this->assertStringNotContainsString('aria-expanded=', $html);
    }

    public function test_cancelled_column_is_collapsed_on_the_rendered_board(): void
    {
        $this->makeTask('Collapsed Cancelled Task', 'cancelled');

        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // Collapsed by default, but the task is still server-rendered (the board
        // never removes rows from the DOM).
        $this->assertStringContainsString('x-data="{ collapsed: true }"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('Collapsed Cancelled Task', $html);

        // No other column claims a collapsed initial state.
        $this->assertSame(1, substr_count($html, 'x-data="{ collapsed: true }"'));
    }

    // ==================================================================
    // L — dynamic drawer aria-busy
    // ==================================================================

    public function test_drawer_aria_busy_is_dynamic_not_hardcoded(): void
    {
        $html = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        $this->assertStringContainsString(':aria-busy="loading ? \'true\' : \'false\'"', $html);
        $this->assertStringContainsString('aria-busy="false"', $html);
        $this->assertStringNotContainsString('aria-busy="true"', $html);
    }

    // ==================================================================
    // J/K — focus management contracts (markup level)
    // ==================================================================

    public function test_drawer_shell_exposes_focus_entry_trap_and_return(): void
    {
        $html = Blade::render(
            '<x-elements.drawer name="task-detail" side="left" label="بستن">محتوا</x-elements.drawer>'
        );

        $this->assertStringContainsString('x-on:keydown.tab="trapFocus($event)"', $html);
        $this->assertStringContainsString('trapFocus(event)', $html);
        $this->assertStringContainsString('opener', $html);
        $this->assertStringContainsString('x-ref="drawerPanel"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);

        // Existing behavior preserved.
        $this->assertStringContainsString('keydown.escape.window', $html);
        $this->assertStringContainsString('overflow-y-hidden', $html);
    }

    public function test_mobile_sidebar_has_trap_inert_and_static_desktop(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(':inert="isMobile && ! open"', $html);
        $this->assertStringContainsString('x-on:keydown.tab="trapFocus($event)"', $html);
        $this->assertStringContainsString('matchMedia(\'(max-width: 767px)\')', $html);
        $this->assertStringContainsString('toggle-mobile-sidebar.window="toggle()"', $html);

        // Desktop must stay in the normal flow and out of any trap.
        $this->assertStringContainsString('md:static', $html);
        $this->assertStringContainsString('if (! this.open || ! this.isMobile) {', $html);
    }

    // ==================================================================
    // A — Task Index header title
    // ==================================================================

    public function test_task_index_renders_its_header_title(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('<h2 class="text-lg font-semibold text-ink">فهرست وظایف</h2>', false);
    }

    // ==================================================================
    // B — Task Detail header title
    // ==================================================================

    public function test_task_detail_renders_its_header_title(): void
    {
        $task = $this->makeTask('Header Title Task');

        $this->actingAs($this->admin)
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->assertSee('<h2 class="text-lg font-semibold text-ink">جزئیات وظیفه</h2>', false);
    }

    // ==================================================================
    // G — Task Index real server-side filters
    // ==================================================================

    public function test_task_index_renders_a_real_get_filter_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('action="'.route('tasks.index').'"', false)
            ->assertSee('name="search"', false)
            ->assertSee('name="project"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="priority"', false)
            ->assertSee('name="project"', false);
    }

    public function test_task_index_search_filter_narrows_on_the_title(): void
    {
        $this->makeTask('Index Needle Task');
        $this->makeTask('Index Haystack Task');

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['search' => 'Needle']))
            ->assertOk()
            ->assertSee('Index Needle Task')
            ->assertDontSee('Index Haystack Task');
    }

    public function test_task_index_status_filter_uses_the_real_status_not_the_group(): void
    {
        $this->makeTask('Index True In Progress', 'in_progress');
        $this->makeTask('Index True Needs Rework', 'needs_rework');

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['status' => 'in_progress']))
            ->assertOk()
            ->assertSee('Index True In Progress')
            ->assertDontSee('Index True Needs Rework');

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['status' => 'needs_rework']))
            ->assertOk()
            ->assertSee('Index True Needs Rework')
            ->assertDontSee('Index True In Progress');
    }

    public function test_task_index_priority_filter_acts_on_the_query(): void
    {
        $this->makeTask('Index Urgent Task', 'draft', 'urgent');
        $this->makeTask('Index Low Task', 'draft', 'low');

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['priority' => 'urgent']))
            ->assertOk()
            ->assertSee('Index Urgent Task')
            ->assertDontSee('Index Low Task');
    }

    public function test_task_index_project_filter_acts_on_the_query(): void
    {
        $other = Project::factory()->create(['name' => 'Phase 5B-1 Other Project']);

        $this->makeTask('Index Project A Task');
        $this->makeTask('Index Project B Task', 'assigned', 'normal', $other);

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['project' => $other->id]))
            ->assertOk()
            ->assertSee('Index Project B Task')
            ->assertDontSee('Index Project A Task');
    }

    public function test_task_index_invalid_filter_values_do_not_widen_the_query(): void
    {
        $this->makeTask('Index Valid Task', 'draft');

        // Unknown status / priority are ignored, never treated as a wildcard,
        // and never reach the query as a raw value.
        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['status' => 'not-a-status', 'priority' => 'not-a-priority']))
            ->assertOk()
            ->assertSee('Index Valid Task');
    }

    public function test_task_index_renders_canonical_status_labels_not_translation_keys(): void
    {
        $this->makeTask('Index Label Task', 'needs_rework');

        $this->actingAs($this->admin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('نیاز به اصلاح')
            ->assertDontSee('status.needs_rework');
    }

    // ==================================================================
    // H — broken document download link removed
    // ==================================================================

    public function test_task_detail_document_row_has_no_broken_download_link(): void
    {
        $task = $this->makeTask('Document Task');

        Document::create([
            'attachable_type' => Task::class,
            'attachable_id' => $task->id,
            'original_name' => 'evidence.pdf',
            'stored_name' => 'documents/evidence.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'checksum' => str_repeat('a', 64),
            'uploaded_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->assertSee('evidence.pdf')
            ->getContent();

        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('>دانلود<', $html);
    }

    // ==================================================================
    // I — flash messages render once (layout only)
    // ==================================================================

    public function test_task_detail_renders_each_flash_message_exactly_once(): void
    {
        $task = $this->makeTask('Flash Task');
        $status = 'پیام وضعیت یکتا برای آزمون';
        $error = 'پیام خطای یکتا برای آزمون';

        $html = $this->actingAs($this->admin)
            ->withSession(['status' => $status, 'error' => $error])
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $status));
        $this->assertSame(1, substr_count($html, $error));
    }

    public function test_settings_page_renders_its_flash_message_exactly_once(): void
    {
        $status = 'پیام تنظیمات یکتا برای آزمون';

        $html = $this->actingAs($this->admin)
            ->withSession(['status' => $status])
            ->get(route('settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $status));
    }

    // ==================================================================
    // C — Dashboard review metric
    // ==================================================================

    public function test_dashboard_review_metric_counts_the_three_review_statuses(): void
    {
        $this->makeTask('Dash Submitted', 'submitted_for_review');
        $this->makeTask('Dash Under Review', 'under_review');
        $this->makeTask('Dash Supervisor Approved', 'supervisor_approved');
        // Out of scope for the review queue:
        $this->makeTask('Dash Approved', 'approved');
        $this->makeTask('Dash Draft', 'draft');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('myTasksCount', 3);
    }

    public function test_dashboard_review_metric_is_zero_without_review_statuses(): void
    {
        // 'completed' is not a TaskStatus; previously this metric was pinned to
        // a status that can never exist, so it was structurally always 0.
        $this->makeTask('Dash Approved Only', 'approved');
        $this->makeTask('Dash Draft Only', 'draft');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('myTasksCount', 0);
    }

    public function test_dashboard_status_distribution_uses_canonical_persian_labels(): void
    {
        $this->makeTask('Dash Dist One', 'needs_rework');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('نیاز به اصلاح')
            ->assertDontSee('>in_progress<', false);
    }

    // ==================================================================
    // F — Persian priority surfaces
    // ==================================================================

    public function test_task_detail_and_board_filters_render_persian_priority_labels(): void
    {
        $task = $this->makeTask('Priority Label Task', 'assigned', 'urgent');

        $this->actingAs($this->admin)
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->assertSee('اولویت')
            ->assertSee('فوری')
            ->assertDontSee('priority.urgent');

        $boardHtml = $this->actingAs($this->admin)->get(route('tasks.board'))->assertOk()->getContent();

        // The board's priority filter renders the canonical Persian label while
        // keeping the raw enum value as the submitted value.
        $this->assertMatchesRegularExpression('/<option value="urgent"[^>]*>\s*فوری\s*<\/option>/u', $boardHtml);
        $this->assertDoesNotMatchRegularExpression('/<option value="[^"]*"[^>]*>\s*(low|normal|high|urgent)\s*<\/option>/u', $boardHtml);
    }

    public function test_create_form_offers_only_valid_enum_priorities(): void
    {
        $html = $this->actingAs($this->admin)->get(route('tasks.create'))->assertOk()->getContent();

        foreach (TaskPriority::cases() as $priority) {
            $this->assertStringContainsString('value="'.$priority->value.'"', $html);
        }

        // The previous option values were not part of the enum and were
        // rejected by StoreTaskRequest.
        $this->assertStringNotContainsString('value="medium"', $html);
        $this->assertStringNotContainsString('value="critical"', $html);
    }
}
