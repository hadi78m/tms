<?php

use App\Domain\Exceptions\CircularDependencyException;
use App\Domain\Exceptions\TaskBlockedException;
use App\Domain\Services\TaskService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('prevents starting a task if predecessors are not approved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $predecessor = Task::factory()->create(['project_id' => $project->id, 'status' => 'assigned']);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'assigned']);

    $taskService = app(TaskService::class);
    $taskService->addDependency($task, $predecessor, $user);

    expect(fn () => $taskService->startProgress($task, $user))
        ->toThrow(TaskBlockedException::class);
});

it('allows starting a task if predecessors are approved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $predecessor = Task::factory()->create(['project_id' => $project->id, 'status' => 'approved']);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'assigned']);

    $taskService = app(TaskService::class);
    $taskService->addDependency($task, $predecessor, $user);

    $taskService->startProgress($task, $user);

    expect($task->fresh()->status)->toBe('in_progress');
});

it('prevents circular dependencies', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $taskA = Task::factory()->create(['project_id' => $project->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id]);
    $taskC = Task::factory()->create(['project_id' => $project->id]);

    $taskService = app(TaskService::class);

    // A depends on B
    $taskService->addDependency($taskA, $taskB, $user);
    // B depends on C
    $taskService->addDependency($taskB, $taskC, $user);

    // C depends on A (Circular!)
    expect(fn () => $taskService->addDependency($taskC, $taskA, $user))
        ->toThrow(CircularDependencyException::class);
});

it('can create a subtask with parent_id', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $parent = Task::factory()->create(['project_id' => $project->id]);

    $response = actingAs($user)->post(route('tasks.store'), [
        'title' => 'Subtask',
        'project_id' => $project->id,
        'priority' => 'high',
        'weight' => 10,
        'parent_task_id' => $parent->id,
    ]);

    $response->assertRedirect(route('tasks.index'));
    $this->assertDatabaseHas('tasks', [
        'title' => 'Subtask',
        'parent_task_id' => $parent->id,
    ]);
});
