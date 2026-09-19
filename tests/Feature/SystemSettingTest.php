<?php

use App\Models\User;
use App\Models\SystemSetting;
use Spatie\Permission\Models\Role;
use App\Domain\Services\SettingsService;
use function Pest\Laravel\{actingAs, get, post, assertDatabaseHas};

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employer', 'guard_name' => 'web']);
});

it('protects settings page for non-admins', function () {
    $user = User::factory()->create();
    $user->assignRole('employer');

    actingAs($user)->get(route('settings.index'))->assertForbidden();
});

it('allows admin to view and update settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SystemSetting::factory()->create(['key' => 'approval_mode', 'value' => 'two_tier', 'type' => 'string']);
    SystemSetting::factory()->create(['key' => 'allow_reopen', 'value' => 'false', 'type' => 'boolean']);

    actingAs($admin)->get(route('settings.index'))->assertOk();

    actingAs($admin)->post(route('settings.update'), [
        'approval_mode' => 'employer_only',
        'allow_reopen' => 'true'
    ])->assertRedirect();

    assertDatabaseHas('system_settings', [
        'key' => 'approval_mode',
        'value' => 'employer_only'
    ]);
    assertDatabaseHas('system_settings', [
        'key' => 'allow_reopen',
        'value' => 'true'
    ]);
});

it('caches settings correctly via SettingsService', function () {
    $service = app(SettingsService::class);
    $service->set('test_key', 'test_value');
    
    expect($service->get('test_key'))->toBe('test_value');
    
    // Test default
    expect($service->get('missing_key', 'default_val'))->toBe('default_val');
});
