<?php

use App\Models\User;
use App\Models\Task;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\{actingAs, get};

beforeEach(function () {
    Role::firstOrCreate(['name' => 'management', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employer', 'guard_name' => 'web']);
});

it('allows managers to view reports dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('management');

    actingAs($user)->get(route('reports.index'))->assertOk();
});

it('prevents regular users from viewing reports', function () {
    $user = User::factory()->create();
    // No role

    actingAs($user)->get(route('reports.index'))->assertForbidden();
});

it('downloads csv export for all tasks', function () {
    $user = User::factory()->create();
    $user->assignRole('employer');

    Task::factory()->count(3)->create();

    $response = actingAs($user)->get(route('reports.export', ['type' => 'all_tasks']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    
    // Check if it's a streamed response
    expect($response->streamedContent())->toContain('شناسه');
});
