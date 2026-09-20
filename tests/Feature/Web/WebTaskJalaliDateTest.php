<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('manager can create task with jalali start and due dates', function () {
    $manager = User::factory()->create(['contractor_id' => null]);
    $project = Project::factory()->create();

    $response = $this->actingAs($manager)->post('/tasks', [
        'project_id' => $project->id,
        'title' => 'Task with Jalali dates',
        'priority' => 'high',
        'weight' => 25,
        'planned_start_date' => '۱۴۰۵/۰۱/۱۵', // Persian numerals
        'planned_due_date' => '1405/01/25',    // English numerals
        'description' => 'Testing jalali dates input in task creation',
    ]);

    $response->assertRedirect('/tasks');

    $task = Task::where('title', 'Task with Jalali dates')->first();
    expect($task)->not->toBeNull();
    // 1405/01/15 is 2026-04-04
    expect($task->planned_start_at->format('Y-m-d'))->toBe('2026-04-04');
    // 1405/01/25 is 2026-04-14
    expect($task->planned_due_at->format('Y-m-d'))->toBe('2026-04-14');
});

test('task show page displays dates in jalali format', function () {
    $manager = User::factory()->create(['contractor_id' => null]);
    $project = Project::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'planned_start_at' => '2026-04-04 09:00:00',
        'planned_due_at' => '2026-04-14 18:00:00',
    ]);

    $response = $this->actingAs($manager)->get("/tasks/{$task->id}");

    $response->assertStatus(200);
    $response->assertSee('1405/01/15');
    $response->assertSee('1405/01/25');
});

test('tasks index page displays jalali due date', function () {
    $manager = User::factory()->create(['contractor_id' => null]);
    $project = Project::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'planned_due_at' => '2026-04-14 18:00:00',
    ]);

    $response = $this->actingAs($manager)->get('/tasks');

    $response->assertStatus(200);
    $response->assertSee('1405/01/25');
});

test('document can be uploaded with jalali claimed_at date', function () {
    Storage::fake('local');

    $manager = User::factory()->create(['contractor_id' => null]);
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    $file = UploadedFile::fake()->create('contract.pdf', 100);

    $response = $this->actingAs($manager)->post("/tasks/{$task->id}/documents", [
        'file' => $file,
        'claimed_at' => '۱۴۰۵/۰۱/۱۵ 14:30',
    ]);

    $response->assertRedirect();

    $doc = $task->documents()->first();
    expect($doc)->not->toBeNull();
    expect($doc->claimed_at->format('Y-m-d H:i'))->toBe('2026-04-04 14:30');
});

test('csv report export includes jalali dates', function () {
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create(['contractor_id' => null]);
    $admin->assignRole('admin');

    $project = Project::factory()->create(['name' => 'Project Alpha']);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Task for report export',
        'status' => 'approved',
        'planned_start_at' => '2026-04-04 09:00:00',
        'planned_due_at' => '2026-04-14 18:00:00',
    ]);

    $response = $this->actingAs($admin)->get('/reports/export?type=all_tasks');

    $response->assertStatus(200);
    $content = $response->streamedContent();

    expect($content)->toContain('1405/01/15');
    expect($content)->toContain('1405/01/25');
});
