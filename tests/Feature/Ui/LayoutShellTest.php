<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LayoutShellTest extends TestCase
{
    use RefreshDatabase;

    private function refreshPermissionCache(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dashboard_renders_new_component_based_layout(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        // navigation content از sidebars/main
        $response->assertSee('پیشخوان');
        $response->assertSee('مدیریت وظایف');
        // header از elements/app-header + section صفحه
        $response->assertSee('داشبورد کاربری');
        // mobile toggle markup
        $response->assertSee('toggle-mobile-sidebar');
        $response->assertSee('fa-bars');
    }

    public function test_logout_form_is_present_with_csrf(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('action="'.route('logout').'"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('خروج');
    }

    public function test_role_gated_navigation_is_hidden_from_plain_user(): void
    {
        $this->refreshPermissionCache();
        $user = User::factory()->create(['contractor_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('گزارش‌ها');
        $response->assertDontSee('ساختار ماژول‌ها');
        $response->assertDontSee('فازهای WBS');
        $response->assertDontSee('مدیریت سیستم');
    }

    public function test_admin_role_gated_navigation_is_visible_for_admin(): void
    {
        $this->refreshPermissionCache();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('گزارش‌ها');
        $response->assertSee('ساختار ماژول‌ها');
        $response->assertSee('فازهای WBS');
        $response->assertSee('مدیریت سیستم');
        $response->assertSee('کاربران سیستم');
        $response->assertSee('تنظیمات سامانه');
    }

    public function test_dashboard_route_active_state_is_marked(): void
    {
        $user = User::factory()->create(['contractor_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('aria-current="page"', false);
    }
}
