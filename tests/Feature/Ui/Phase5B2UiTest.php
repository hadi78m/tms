<?php

namespace Tests\Feature\Ui;

use App\Domain\Enums\TaskPriority;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 5B-2 — Visual Migration (Dashboard + Task Index + Task Detail).
 *
 * These tests pin the CONTRACTS of the migration, not its pixels:
 *   - the three pages render, with their canonical headings and sections;
 *   - every previously visible datum is still reachable;
 *   - presentation comes from the canonical sources (BoardColumns status,
 *     TaskPriority) and from the design-system vocabulary (tokens/primitives);
 *   - the legacy ad-hoc container/colour signatures are gone;
 *   - behaviour-bearing markup (server-side filters, query string, pagination,
 *     form ids, the datepicker hooks, permission gates) is unchanged.
 *
 * They deliberately avoid asserting on decorative class-by-class markup so a
 * future visual tweak inside the design system does not break them.
 */
class Phase5B2UiTest extends TestCase
{
    use RefreshDatabase;

    /** Legacy signatures the migration must have removed from the three pages. */
    private const LEGACY_MARKUP = [
        'border-slate-200',
        'border-slate-100',
        'border-slate-300',
        'text-slate-800',
        'text-slate-600',
        'text-slate-500',
        'bg-slate-50',
        'rounded-xl shadow-sm border',
    ];

    private Project $project;

    private User $admin;

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

        $this->admin = User::factory()->create(['contractor_id' => null]);
        $this->admin->assignRole('admin');
        $this->admin->refresh();

        $this->project = Project::factory()->create(['name' => 'Phase 5B-2 Project']);
    }

    private function makeTask(string $title, string $status = 'assigned', string $priority = 'normal'): Task
    {
        return Task::factory()->create([
            'project_id' => $this->project->id,
            'contractor_id' => $this->project->contractor_id,
            'contract_id' => $this->project->contract_id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
        ]);
    }

    /**
     * The migrated pages must not carry the pre-migration ad-hoc markup.
     */
    private function assertNoLegacyMarkup(string $html, string $page): void
    {
        foreach (self::LEGACY_MARKUP as $legacy) {
            $this->assertStringNotContainsString(
                $legacy,
                $html,
                "{$page} still contains legacy presentation markup: {$legacy}"
            );
        }
    }

    // ==================================================================
    // B — Dashboard
    // ==================================================================

    public function test_dashboard_renders_all_of_its_sections(): void
    {
        $task = $this->makeTask('Dash Rework Task', 'needs_rework');
        $this->assertNotNull($task);

        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        // Every section that existed before the migration is still there.
        $response->assertSee('خوش آمدید،');
        $response->assertSee('وظایف نیازمند بررسی');
        $response->assertSee('نقض زمان‌بندی (SLA)');
        $response->assertSee('دسترسی سریع');
        $response->assertSee('برو به کارتابل وظایف');
        $response->assertSee('توزیع وضعیت وظایف');

        // Metric values are still rendered.
        $response->assertViewHas('myTasksCount', 0);
        $response->assertViewHas('breachedSlapCount', 0);
    }

    public function test_dashboard_uses_the_design_system_and_canonical_status_presentation(): void
    {
        $this->makeTask('Dash Dist Task', 'needs_rework');

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'dashboard');

        // Design-system surfaces/primitives are in play.
        $this->assertStringContainsString('border-line', $html);
        $this->assertStringContainsString('text-ink', $html);

        // Canonical status label, never a raw domain value.
        $this->assertStringContainsString(BoardColumns::statusLabel('needs_rework'), $html);
        foreach (BoardColumns::filterableStatuses() as $status) {
            $this->assertStringNotContainsString('>'.$status.'<', $html);
        }
    }

    public function test_dashboard_empty_state_is_a_real_component_state(): void
    {
        // No tasks at all → the distribution panel shows its genuine empty state.
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('هیچ وظیفه‌ای برای نمایش وجود ندارد.', $html);
        $this->assertStringContainsString('border-dashed', $html);
    }

    // ==================================================================
    // C — Task Index
    // ==================================================================

    public function test_task_index_keeps_heading_filters_and_table_columns(): void
    {
        $this->makeTask('Index Migrated Task', 'assigned', 'high');

        $response = $this->actingAs($this->admin)->get(route('tasks.index'))->assertOk();

        // Heading is the page's own h1 (unchanged text).
        $response->assertSee('<h1 class="text-2xl font-bold text-ink">فهرست وظایف</h1>', false);

        // Real server-side filter form, all four controls intact.
        $response->assertSee('action="'.route('tasks.index').'"', false);
        foreach (['search', 'project', 'status', 'priority'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        $response->assertSee('for="index-search"', false);
        $response->assertSee('for="index-project"', false);
        $response->assertSee('for="index-status"', false);
        $response->assertSee('for="index-priority"', false);

        // View switcher both ways.
        $response->assertSee(route('tasks.board'), false);

        // Columns still carry the same information.
        foreach (['شناسه', 'عنوان وظیفه', 'پروژه', 'پیمانکار', 'وزن', 'مهلت انجام', 'وضعیت', 'عملیات'] as $column) {
            $response->assertSee($column);
        }

        $response->assertSee('Index Migrated Task');
        $response->assertSee('مشاهده جزئیات');
    }

    public function test_task_index_uses_design_system_and_canonical_labels(): void
    {
        $this->makeTask('Index Labels Task', 'needs_rework', 'urgent');

        $html = $this->actingAs($this->admin)->get(route('tasks.index'))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'tasks/index');

        // Canonical status label for the row.
        $this->assertStringContainsString(BoardColumns::statusLabel('needs_rework'), $html);

        // Canonical priority labels in the filter, with raw enum values submitted.
        foreach (TaskPriority::cases() as $priority) {
            $this->assertStringContainsString($priority->label(), $html);
            $this->assertStringContainsString('value="'.$priority->value.'"', $html);
        }
        $this->assertDoesNotMatchRegularExpression('/<option value="[^"]*"[^>]*>\s*(low|normal|high|urgent)\s*<\/option>/u', $html);
    }

    public function test_task_index_empty_state_replaces_the_empty_table_row(): void
    {
        $project = Project::factory()->create(['name' => 'Empty Index Project']);

        // A different project's tasks exist, but the filter narrows to nothing.
        $this->makeTask('Index Other Task');
        $project->id;

        $html = $this->actingAs($this->admin)
            ->get(route('tasks.index', ['search' => 'no-such-title-anywhere']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('هیچ وظیفه‌ای یافت نشد.', $html);
    }

    public function test_task_index_pagination_and_query_string_are_preserved(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->makeTask("Index Page Task {$i}", 'draft');
        }

        $response = $this->actingAs($this->admin)
            ->get(route('tasks.index', ['status' => 'draft']))
            ->assertOk();

        $response->assertSee('17 وظیفه');

        $html = $response->getContent();

        // Pagination exists (page 15 = paginate(15)) and the active filter is
        // carried into the page links through withQueryString().
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('status=draft', $html);
    }

    public function test_task_index_row_has_no_placeholder_links(): void
    {
        $task = $this->makeTask('Index Link Task');

        $html = $this->actingAs($this->admin)->get(route('tasks.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringContainsString(route('tasks.show', $task->id), $html);
    }

    // ==================================================================
    // D — Task Detail
    // ==================================================================

    public function test_task_detail_keeps_every_field_it_showed_before(): void
    {
        $task = $this->makeTask('Detail Migrated Task', 'assigned', 'urgent');

        $response = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk();

        // Primary header block.
        $response->assertSee('مشخصات وظیفه #'.$task->id);
        $response->assertSee('Detail Migrated Task');

        // Context / schedule metadata labels.
        foreach ([
            'پروژه مرتبط',
            'پیمانکار مجری',
            'مرحلهٔ ماژول (V1.10)',
            'وزن اجرایی',
            'اولویت',
            'تاریخ شروع (برنامه‌ای)',
            'مهلت انجام (برنامه‌ای)',
        ] as $label) {
            $response->assertSee($label);
        }

        // Sections that exist in the current repository must survive.
        $response->assertSee('مستندات و پیوست‌ها');
        $response->assertSee('زیرتسک‌ها (Subtasks)');
        $response->assertSee('پیش‌نیازها');
        $response->assertSee('وضعیت SLA');
    }

    public function test_task_detail_uses_design_system_and_canonical_presentation(): void
    {
        $task = $this->makeTask('Detail Presentation Task', 'needs_rework', 'urgent');

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'tasks/show');

        // Canonical status + priority presentation, never a raw value or key.
        $this->assertStringContainsString(BoardColumns::statusLabel('needs_rework'), $html);
        $this->assertStringContainsString(TaskPriority::Urgent->label(), $html);
        $this->assertStringNotContainsString('priority.urgent', $html);
        $this->assertStringNotContainsString(TaskPriority::Urgent->variant().'">urgent', $html);
    }

    public function test_task_detail_keeps_the_datepicker_hook_and_upload_form(): void
    {
        $task = $this->makeTask('Detail Datepicker Task');

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        // The persianDatepicker initializer in layouts/app.blade.php targets
        // '.datedown' — the hook must survive the visual migration.
        $this->assertStringContainsString('class="datedown ', $html);
        $this->assertStringContainsString('name="claimed_at"', $html);
        $this->assertStringContainsString('action="'.route('documents.store', $task->id).'"', $html);
        $this->assertStringContainsString('name="file"', $html);
    }

    public function test_task_detail_document_and_subtask_empty_states_render(): void
    {
        $task = $this->makeTask('Detail Empty States Task');

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        $this->assertStringContainsString('مدرکی برای این وظیفه بارگذاری نشده است.', $html);
        $this->assertStringContainsString('این وظیفه هیچ زیرتسکی ندارد.', $html);
        $this->assertStringContainsString('تایمر SLA هنوز فعال نشده است.', $html);
    }

    public function test_task_detail_documents_keep_their_metadata_and_no_download_link(): void
    {
        $task = $this->makeTask('Detail Document Task');

        Document::create([
            'attachable_type' => Task::class,
            'attachable_id' => $task->id,
            'original_name' => 'phase5b2-evidence.pdf',
            'stored_name' => 'documents/phase5b2-evidence.pdf',
            'mime_type' => 'application/pdf',
            'size' => 4096,
            'checksum' => str_repeat('b', 64),
            'uploaded_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        $this->assertStringContainsString('phase5b2-evidence.pdf', $html);
        $this->assertStringContainsString('KB', $html);

        // The broken download affordance stays removed (Phase 5B-1 decision).
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('>دانلود<', $html);
    }

    public function test_task_detail_approval_gates_and_form_ids_are_unchanged(): void
    {
        $task = $this->makeTask('Detail Approval Task', 'under_review');

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        // Behaviour-bearing identifiers the JS/QA rely on must survive.
        foreach ([
            'id="form-technical-approve"',
            'id="btn-technical-approve"',
            'id="form-technical-rework"',
            'id="technical-rework-comment"',
            'id="btn-technical-rework"',
        ] as $id) {
            $this->assertStringContainsString($id, $html);
        }

        $this->assertStringContainsString('تایید فنی (ناظر)', $html);
        $this->assertStringContainsString('اعلام نیاز به اصلاح', $html);

        // Accessibility: the rework textarea is now label-associated.
        $this->assertStringContainsString('for="technical-rework-comment"', $html);
    }

    public function test_task_detail_final_approval_panel_for_supervisor_approved_task(): void
    {
        $task = $this->makeTask('Detail Final Task', 'supervisor_approved');

        $html = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();

        $this->assertStringContainsString('تایید نهایی (بهره‌بردار)', $html);
        $this->assertStringContainsString('id="form-final-approve"', $html);
        $this->assertStringContainsString('id="final-rework-comment"', $html);
        $this->assertStringContainsString('for="final-rework-comment"', $html);
    }

    public function test_task_detail_renders_each_flash_message_once_after_migration(): void
    {
        $task = $this->makeTask('Detail Flash Task');
        $status = 'پیام یکتای 5B-2 برای آزمون';

        $html = $this->actingAs($this->admin)
            ->withSession(['status' => $status])
            ->get(route('tasks.show', $task->id))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $status));
    }

    // ==================================================================
    // Drawer ↔ Detail consistency (§9)
    // ==================================================================

    public function test_drawer_and_detail_share_one_status_and_priority_presentation(): void
    {
        $task = $this->makeTask('Detail Drawer Consistency Task', 'needs_rework', 'urgent');

        $detail = $this->actingAs($this->admin)->get(route('tasks.show', $task->id))->assertOk()->getContent();
        $drawer = $this->actingAs($this->admin)->get(route('tasks.show', [$task->id, 'view' => 'drawer']))->assertOk()->getContent();

        $statusLabel = BoardColumns::statusLabel('needs_rework');
        $priorityLabel = TaskPriority::Urgent->label();

        foreach (['detail' => $detail, 'drawer' => $drawer] as $surface => $html) {
            $this->assertStringContainsString($statusLabel, $html, "{$surface} is missing the canonical status label");
            $this->assertStringContainsString($priorityLabel, $html, "{$surface} is missing the canonical priority label");
        }
    }
}
