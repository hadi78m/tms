<?php

namespace Tests\Feature\Ui;

use App\Domain\Services\WbsPhaseService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 5B-3 — Visual Migration Wave 2 (users.index, wbs-phases.index, settings.index).
 *
 * Pins the CONTRACTS of the migration, not its pixels:
 *   - pages render with their headings, labels, columns and empty states;
 *   - routes, forms, field names, method overrides and confirmation handlers
 *     are unchanged;
 *   - role/phase status presentation stays semantically identical (same
 *     Persian labels, only the visual vocabulary moved to the design system);
 *   - the legacy ad-hoc container/colour signatures are gone from the
 *     migrated pages.
 *
 * Like Phase5B2UiTest, these tests avoid decorative class-by-class assertions.
 */
class Phase5B3UiTest extends TestCase
{
    use RefreshDatabase;

    /** Legacy signatures that must be gone from the migrated pages. */
    private const LEGACY_MARKUP = [
        'border-slate-200',
        'border-slate-100',
        'border-slate-300',
        'text-slate-800',
        'text-slate-600',
        'text-slate-500',
        'text-slate-700',
        'bg-slate-50',
        'bg-blue-600',
        'bg-green-100',
        'bg-red-100',
        'bg-amber-100',
        'bg-purple-50',
        'bg-emerald-50',
        'bg-rose-',
        'text-rose-',
        'rounded-xl shadow-sm border',
    ];

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

        // The users.index role badges include supervisor/contractor rows.
        Role::findOrCreate('supervisor', 'web');
        Role::findOrCreate('contractor', 'web');

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create(['contractor_id' => null]);
        $this->admin->assignRole('admin');
        $this->admin->refresh();
    }

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
    // users.index
    // ==================================================================

    public function test_users_index_keeps_heading_columns_and_actions(): void
    {
        $member = User::factory()->create(['contractor_id' => null]);
        $member->assignRole('supervisor');

        $response = $this->actingAs($this->admin)->get(route('users.index'))->assertOk();

        // Heading and subtitle survive verbatim.
        $response->assertSee('<h1 class="text-xl font-bold text-ink">فهرست کاربران سامانه</h1>', false);
        $response->assertSee('مدیریت حساب‌های کاربری، تعیین نقش‌های دسترسی و اتصال به پیمانکاران');

        // Primary action keeps its route.
        $response->assertSee(route('users.create'), false);

        // Table columns all survive.
        foreach (['شناسه / نام کاربر', 'کد ملی (نام کاربری)', 'نقش‌های دسترسی', 'پیمانکار متصل', 'شماره همراه', 'وضعیت', 'عملیات'] as $column) {
            $response->assertSee($column);
        }

        // Role labels are semantically identical (same Persian text).
        $response->assertSee('مدیر ارشد');
        $response->assertSee('ناظر سیستم');

        // Active status label.
        $response->assertSee('فعال');
    }

    public function test_users_index_delete_contract_is_unchanged(): void
    {
        $victim = User::factory()->create(['contractor_id' => null]);

        $html = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'users/index');

        // The delete affordance keeps its exact contract: POST + @method(DELETE)
        // + the same confirmation prompt + self-protection guard.
        $this->assertStringContainsString('action="'.route('users.destroy', $victim).'"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString("return confirm('آیا از حذف این کاربر اطمینان دارید؟');", $html);

        // The admin row itself must not offer self-deletion.
        $this->assertStringNotContainsString('action="'.route('users.destroy', $this->admin).'"', $html);
    }

    public function test_users_index_has_no_placeholder_links(): void
    {
        // The users table is never empty (the acting admin is always listed),
        // so only the placeholder-link contract is asserted here.
        $html = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringNotContainsString('action="'.route('users.destroy', $this->admin).'"', $html);
    }

    // ==================================================================
    // wbs-phases.index
    // ==================================================================

    public function test_wbs_index_keeps_create_form_contract(): void
    {
        Project::factory()->create(['name' => 'WBS Form Project']);

        $response = $this->actingAs($this->admin)->get(route('wbs-phases.index'))->assertOk();

        $response->assertSee('<h1 class="text-2xl font-bold text-ink">فازهای WBS</h1>', false);

        // The create form keeps route, method and every field name.
        $response->assertSee('action="'.route('wbs-phases.store').'"', false);
        foreach (['project_id', 'name', 'weight', 'planned_duration', 'duration_unit'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        // Field labels survive (now label-associated for accessibility).
        foreach (['پروژه', 'نام فاز', 'وزن (Legacy)', 'مدت برنامه‌ریزی‌شده', 'واحد مدت'] as $label) {
            $response->assertSee($label);
        }

        // The legacy weight input keeps its constraints.
        $this->assertMatchesRegularExpression(
            '/name="weight"[^>]*min="0"[^>]*max="100"/s',
            $response->getContent()
        );
    }

    public function test_wbs_index_phase_status_presentation_is_semantically_identical(): void
    {
        $project = Project::factory()->create(['name' => 'WBS Status Project']);

        // WbsPhase has no factory; phases are created through the domain
        // service (the same path the V19 suites use).
        $service = $this->app->make(WbsPhaseService::class);

        foreach (['Pending Phase', 'Second Phase', 'Third Phase'] as $name) {
            $service->createPhase($project, [
                'name' => $name,
                'expected_output' => 'checklist placeholder',
                'weight' => 50,
                'planned_duration' => 30,
                'duration_unit' => 'day',
                'status' => 'active',
            ], $this->admin);
        }

        $html = $this->actingAs($this->admin)->get(route('wbs-phases.index'))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'wbs-phases/index');

        // The phases render with their names, checklist counts and the
        // «در انتظار» initial status the service assigns automatically.
        foreach (['Pending Phase', 'Second Phase', 'Third Phase'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        $this->assertStringContainsString('آیتم چک‌لیست', $html);
        $this->assertStringContainsString('در انتظار', $html);
    }

    public function test_wbs_index_empty_state_renders(): void
    {
        $html = $this->actingAs($this->admin)->get(route('wbs-phases.index'))->assertOk()->getContent();

        $this->assertStringContainsString('هیچ فازی ثبت نشده است.', $html);
    }

    // ==================================================================
    // settings.index
    // ==================================================================

    public function test_settings_keeps_the_three_toggles_and_their_defaults(): void
    {
        $html = $this->actingAs($this->admin)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertNoLegacyMarkup($html, 'settings/index');

        // Form contract: same action, same three checkbox names + select.
        $this->assertStringContainsString('action="'.route('settings.update').'"', $html);
        foreach (['approval_mode', 'allow_reopen', 'require_evidence_on_submit', 'lock_weight'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
        }

        // Toggle labels and descriptions survive verbatim.
        foreach ([
            'حالت تایید وظایف',
            'امکان بازگشایی وظایف تایید شده',
            'الزام آپلود مستندات در زمان اتمام',
            'قفل شدن وزن',
        ] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        // The evidence + lock-weight defaults remain checked=true.
        $this->assertMatchesRegularExpression(
            '/name="require_evidence_on_submit"[^>]*checked/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="lock_weight"[^>]*checked/s',
            $html
        );

        // Submit keeps its text.
        $this->assertStringContainsString('ذخیره تنظیمات', $html);
    }

    public function test_settings_renders_flash_once_after_migration(): void
    {
        $status = 'پیام یکتای 5B-3 برای آزمون';

        $html = $this->actingAs($this->admin)
            ->withSession(['status' => $status])
            ->get(route('settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $status));
    }

    // ==================================================================
    // Cross-page: canonical sources still win where they exist
    // ==================================================================

    public function test_migrated_pages_still_resolve_status_via_canonical_sources_only(): void
    {
        // The wbs phase statuses above are local presentation; the task domain
        // statuses must never leak as raw values on any migrated page.
        $task = Task::factory()->create([
            'project_id' => Project::factory()->create()->id,
            'status' => 'needs_rework',
            'priority' => 'normal',
        ]);

        foreach ([route('users.index'), route('wbs-phases.index'), route('settings.index')] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('>needs_rework<', $html);
            $this->assertStringNotContainsString('priority.normal', $html);
        }

        $this->assertNotNull($task);
    }
}
