<?php

namespace Tests\Feature\Ui;

use App\Models\SyncedContractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 5B-5 — Visual Migration of users.create + users.edit.
 *
 * Pins the CONTRACTS of the migration, not its pixels:
 *   - both pages render for an admin and stay forbidden for other roles;
 *   - form actions, HTTP methods, CSRF/spoofing, field names/ids, required,
 *     maxlength/minlength, labels and help texts are unchanged;
 *   - validation error rendering (@error + old() input repopulation) survives;
 *   - edit populates from the user, with old() taking precedence;
 *   - role checkboxes keep their name/value/checked semantics and the
 *     contractor-wrapper JS hooks (`.role-checkbox`, `#contractor-wrapper`,
 *     `cb.value === 'contractor'`) are intact;
 *   - the legacy slate/blue/rose/purple/emerald vocabulary is gone and the
 *     design-system tokens are present.
 *
 * Like Phase5B2/3/4UiTest, these tests avoid decorative class-by-class
 * assertions for styling that carries no contract.
 */
class Phase5B5UiTest extends TestCase
{
    use RefreshDatabase;

    /** Legacy colour tokens that must be gone from both migrated pages. */
    private const LEGACY_MARKUP = [
        'blue-600',
        'blue-500',
        'rose-500',
        'purple-200',
        'purple-50',
        'emerald-200',
        'emerald-50',
        'rounded-xl',
        'bg-slate',
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

        // The role checkbox grid iterates Role::all() — pin the labels we assert on.
        Role::findOrCreate('supervisor', 'web');
        Role::findOrCreate('contractor', 'web');
        Role::findOrCreate('management', 'web');

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

        // "slate-N" as a colour token (the lookbehind skips "tranSLATE-x").
        $this->assertDoesNotMatchRegularExpression(
            '/(?<!tran)slate-\d/u',
            $html,
            "{$page} still contains a legacy slate colour token"
        );
    }

    // ==================================================================
    // users.create
    // ==================================================================

    public function test_create_page_renders_for_admin_with_its_heading(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.create'))->assertOk();

        $html = $response->getContent();

        // In-page h1 keeps its level and exact text (app-header h2 untouched).
        $this->assertMatchesRegularExpression('/<h1[^>]*>مشخصات کاربر جدید<\/h1>/u', $html);
        $response->assertSee('تعریف و ثبت کاربر جدید');
        $response->assertSee('اطلاعات هویتی، سطوح دسترسی و ارتباط با پیمانکار را وارد نمایید');
    }

    public function test_create_form_contract_is_unchanged(): void
    {
        $html = $this->actingAs($this->admin)->get(route('users.create'))->assertOk()->getContent();

        // Same action, method, CSRF.
        $this->assertStringContainsString('<form action="'.route('users.store').'" method="POST"', $html);
        $this->assertStringContainsString('name="_token"', $html);

        // Every field name survives.
        foreach (['name="name"', 'name="national_code"', 'name="mobile"', 'name="email"', 'name="password"', 'name="is_active"', 'name="roles[]"', 'name="contractor_id"'] as $field) {
            $this->assertStringContainsString($field, $html, "Missing field: {$field}");
        }

        // HTML validation contract (mirrors StoreUserRequest): required,
        // maxlength on national_code/mobile, minlength on password.
        $this->assertMatchesRegularExpression('/name="name"[^>]*required/u', $html);
        $this->assertMatchesRegularExpression('/name="national_code"[^>]*required[^>]*maxlength="10"/u', $html);
        $this->assertMatchesRegularExpression('/name="mobile"[^>]*required[^>]*maxlength="11"/u', $html);
        $this->assertMatchesRegularExpression('/name="password"[^>]*required[^>]*minlength="6"/u', $html);

        // Labels, help text and buttons survive verbatim.
        foreach ([
            'نام و نام خانوادگی', 'کد ملی (۱۰ رقم)', 'شماره همراه (۱۱ رقم)', 'پست الکترونیک (ایمیل)',
            'کلمه عبور', 'نام کاربری به صورت خودکار برابر با کد ملی خواهد بود.',
            'نقش‌های دسترسی سامانه', 'اتصال به شرکت پیمانکار', 'حساب کاربری فعال باشد',
            'ثبت و ذخیره کاربر', 'انصراف', 'بازگشت به فهرست',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Missing label: {$label}");
        }

        // Back/cancel affordances keep their route (the sidebar links there too,
        // so this is a lower bound); no dead placeholder links.
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.route('users.index').'"'));
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_create_role_controls_and_defaults_are_preserved(): void
    {
        $html = $this->actingAs($this->admin)->get(route('users.create'))->assertOk()->getContent();

        // One checkbox per role with its name and value; titles keep the map.
        foreach (['admin', 'supervisor', 'contractor', 'management'] as $roleName) {
            $this->assertMatchesRegularExpression(
                '/name="roles\[\]"\s+value="'.$roleName.'"/u',
                $html,
                "Missing roles[] checkbox for: {$roleName}"
            );
        }
        foreach (['مدیر ارشد (Admin)', 'ناظر سیستم (Supervisor)', 'پیمانکار (Contractor)'] as $title) {
            $this->assertStringContainsString($title, $html);
        }

        // is_active defaults to checked on create (old('is_active', '1')).
        $this->assertMatchesRegularExpression('/name="is_active"[^>]*checked/u', $html);

        // Without old input, the contractor wrapper starts hidden.
        $this->assertStringContainsString('id="contractor-wrapper" class="hidden"', $html);
    }

    public function test_validation_failures_render_errors_and_repopulate_old_input(): void
    {
        // Warm the session, then submit an invalid payload through the REAL
        // validation flow (StoreUserRequest rejects it — nothing is written).
        $this->actingAs($this->admin)->get(route('users.create'))->assertOk();

        $post = $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => '',
                'national_code' => '12',
                'mobile' => 'abc',
                'roles' => [],
            ]);

        // Validation rejected the payload: redirected, nothing written.
        $post->assertRedirect();
        $this->assertDatabaseMissing('users', ['national_code' => '12']);

        $html = $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertOk()
            ->getContent();

        // @error blocks render (the ids only exist inside those blocks) and
        // the inputs are linked to their error containers.
        $this->assertStringContainsString('id="name-error"', $html);
        $this->assertStringContainsString('id="national_code-error"', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="national_code-help national_code-error"/u', $html);

        // old() repopulates the submitted (rejected) values.
        $this->assertStringContainsString('value="12"', $html);
        $this->assertStringContainsString('value="abc"', $html);
    }

    public function test_create_js_hooks_are_intact(): void
    {
        $html = $this->actingAs($this->admin)->get(route('users.create'))->assertOk()->getContent();

        foreach ([
            "querySelectorAll('.role-checkbox')",
            "getElementById('contractor-wrapper')",
            "cb.value === 'contractor'",
            'toggleContractorSelect',
            "addEventListener('change'",
            'DOMContentLoaded',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "Missing JS hook: {$hook}");
        }

        // The class/id the script binds to still exist on the markup.
        $this->assertStringContainsString('class="role-checkbox', $html);
        $this->assertStringContainsString('id="contractor-wrapper"', $html);
    }

    // ==================================================================
    // users.edit
    // ==================================================================

    public function test_edit_page_renders_and_populates_existing_values(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $user = User::factory()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Old Name',
            'national_code' => '5555555555',
            'mobile' => '09120000000',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.edit', $user))->assertOk();
        $html = $response->getContent();

        // Heading keeps its level and interpolates the user name.
        $this->assertMatchesRegularExpression('/<h1[^>]*>ویرایش کاربر: Old Name<\/h1>/u', $html);

        // Existing values populate the fields.
        $this->assertStringContainsString('value="Old Name"', $html);
        $this->assertStringContainsString('value="5555555555"', $html);
        $this->assertStringContainsString('value="09120000000"', $html);
        $this->assertStringContainsString('value="old@example.com"', $html);

        // The contractor select marks the current contractor as selected.
        $this->assertStringContainsString('<option value="'.$contractor->id.'" selected>', $html);
    }

    public function test_edit_form_method_and_action_are_unchanged(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);

        $html = $this->actingAs($this->admin)->get(route('users.edit', $user))->assertOk()->getContent();

        $this->assertStringContainsString('<form action="'.route('users.update', $user).'" method="POST"', $html);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertStringContainsString('name="_token"', $html);

        // Password stays optional and is never pre-populated.
        $this->assertMatchesRegularExpression('/name="password"[^>]*minlength="6"/u', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="password"[^>]*value=/u', $html);

        // Same field set as create.
        foreach (['name="name"', 'name="national_code"', 'name="mobile"', 'name="email"', 'name="is_active"', 'name="roles[]"', 'name="contractor_id"'] as $field) {
            $this->assertStringContainsString($field, $html, "Missing field: {$field}");
        }

        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_edit_validation_failures_render_errors_without_writing(): void
    {
        $user = User::factory()->create([
            'contractor_id' => null,
            'name' => 'Kept Name',
            'national_code' => '5555555555',
            'mobile' => '09120000000',
        ]);

        // Real validation flow: PUT is rejected and nothing is persisted.
        $post = $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => '',
            'national_code' => '12',
            'roles' => [],
        ]);
        $post->assertRedirect();

        $user->refresh();
        $this->assertSame('Kept Name', $user->name);
        $this->assertSame('5555555555', $user->national_code);

        // The re-rendered form shows the error containers and repopulates old().
        $html = $this->actingAs($this->admin)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="name-error"', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="name-error"/u', $html);
        $this->assertStringContainsString('value="12"', $html);

        // The PUT spoofing field is still part of the re-rendered form.
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
    }

    public function test_edit_role_check_state_follows_user_then_old_input(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole('supervisor');

        $html = $this->actingAs($this->admin)->get(route('users.edit', $user))->assertOk()->getContent();

        // Current roles render checked; absent roles do not.
        $this->assertMatchesRegularExpression('/name="roles\[\]"\s+value="supervisor"[^>]*checked/u', $html);
        $this->assertDoesNotMatchRegularExpression('/name="roles\[\]"\s+value="admin"[^>]*checked/u', $html);

        // After a failed submit, old('roles') wins over the stored roles.
        $oldHtml = $this->actingAs($this->admin)
            ->withSession(['_old_input' => ['roles' => ['admin']]])
            ->get(route('users.edit', $user))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="roles\[\]"\s+value="admin"[^>]*checked/u', $oldHtml);
        $this->assertDoesNotMatchRegularExpression('/name="roles\[\]"\s+value="supervisor"[^>]*checked/u', $oldHtml);
    }

    public function test_edit_contractor_wrapper_visibility_logic_is_preserved(): void
    {
        // Without the contractor role: wrapper starts hidden.
        $plain = User::factory()->create(['contractor_id' => null]);
        $plainHtml = $this->actingAs($this->admin)->get(route('users.edit', $plain))->assertOk()->getContent();
        $this->assertStringContainsString('id="contractor-wrapper" class="hidden"', $plainHtml);

        // With the contractor role: wrapper renders without the hidden class.
        $contractorUser = User::factory()->create(['contractor_id' => null]);
        $contractorUser->assignRole('contractor');
        $contractorHtml = $this->actingAs($this->admin)->get(route('users.edit', $contractorUser))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="contractor-wrapper" class="hidden"', $contractorHtml);
    }

    // ==================================================================
    // Cross-cutting: authorization + migration itself
    // ==================================================================

    public function test_other_roles_cannot_reach_create_or_edit(): void
    {
        $supervisor = User::factory()->create(['contractor_id' => null]);
        $supervisor->assignRole('supervisor');

        $target = User::factory()->create(['contractor_id' => null]);

        $this->actingAs($supervisor)->get(route('users.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('users.edit', $target))->assertForbidden();
    }

    public function test_guests_are_redirected_from_create(): void
    {
        // Fresh test: no actingAs user, so the auth middleware answers first.
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    public function test_both_pages_have_no_legacy_markup_and_use_design_system(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);

        foreach ([route('users.create'), route('users.edit', $user)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertNoLegacyMarkup($html, $url);

            // Design-system chrome is actually present.
            $this->assertStringContainsString('border-line', $html);
            $this->assertStringContainsString('rounded-ui', $html);
            $this->assertStringContainsString('shadow-ui-soft', $html);
            $this->assertStringContainsString('bg-primary', $html);
        }
    }
}
