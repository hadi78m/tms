<?php

namespace Tests\Feature\Web;

use App\Domain\DTOs\AssignTaskData;
use App\Domain\Enums\SlaType;
use App\Domain\Enums\TaskStatus;
use App\Domain\Services\SlaService;
use App\Http\Requests\Web\AssignTaskRequest;
use App\Models\Project;
use App\Models\SlaRecord;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class WebTaskStabilizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ==========================================
     * Fix #1: Task Assignment Tests
     * ==========================================
     */
    public function test_valid_task_assignment_via_web(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $manager = User::factory()->create(['contractor_id' => null]);
        $worker = User::factory()->create(['contractor_id' => $contractor->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::Draft->value,
        ]);

        $response = $this->actingAs($manager)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker->id,
            'reason' => 'Assigning for development',
        ]);

        $response->assertRedirect(route('tasks.show', $task->id));
        $response->assertSessionHas('status', 'وظیفه با موفقیت ارجاع داده شد.');

        $task->refresh();
        $this->assertEquals(TaskStatus::Assigned->value, $task->status);

        $activeAssignment = $task->activeAssignment;
        $this->assertNotNull($activeAssignment);
        $this->assertEquals($worker->id, $activeAssignment->user_id);
        $this->assertEquals($manager->id, $activeAssignment->assigned_by);
        $this->assertEquals('Assigning for development', $activeAssignment->reason);

        // Verify SLAs were started on initial assignment
        $this->assertDatabaseHas('sla_records', [
            'task_id' => $task->id,
            'sla_type' => SlaType::Response->value,
            'stopped_at' => null,
        ]);
        $this->assertDatabaseHas('sla_records', [
            'task_id' => $task->id,
            'sla_type' => SlaType::Resolution->value,
            'stopped_at' => null,
        ]);
    }

    public function test_task_reassignment_to_another_user(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $manager = User::factory()->create(['contractor_id' => null]);
        $worker1 = User::factory()->create(['contractor_id' => $contractor->id]);
        $worker2 = User::factory()->create(['contractor_id' => $contractor->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::Draft->value,
        ]);

        // First assignment
        $this->actingAs($manager)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker1->id,
            'reason' => 'First assignee',
        ]);

        $firstAssignment = $task->fresh()->activeAssignment;
        $this->assertEquals($worker1->id, $firstAssignment->user_id);

        // Reassign to second user
        $response = $this->actingAs($manager)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker2->id,
            'reason' => 'Reassigned to worker2',
        ]);

        $response->assertRedirect(route('tasks.show', $task->id));

        $task->refresh();
        $this->assertNotNull($firstAssignment->fresh()->ended_at);

        $newActiveAssignment = $task->activeAssignment;
        $this->assertNotNull($newActiveAssignment);
        $this->assertEquals($worker2->id, $newActiveAssignment->user_id);
        $this->assertEquals('Reassigned to worker2', $newActiveAssignment->reason);
    }

    public function test_idempotent_task_assignment(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $manager = User::factory()->create(['contractor_id' => null]);
        $worker = User::factory()->create(['contractor_id' => $contractor->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::Draft->value,
        ]);

        // Assign worker
        $this->actingAs($manager)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker->id,
            'reason' => 'Initial assignment',
        ]);

        $countBefore = TaskAssignment::where('task_id', $task->id)->count();
        $this->assertEquals(1, $countBefore);

        // Assign same worker again
        $response = $this->actingAs($manager)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker->id,
            'reason' => 'Duplicate assignment attempt',
        ]);

        $response->assertRedirect(route('tasks.show', $task->id));

        $countAfter = TaskAssignment::where('task_id', $task->id)->count();
        $this->assertEquals(1, $countAfter);
    }

    public function test_unauthorized_contractor_cannot_assign_other_contractor_task(): void
    {
        $contractor1 = SyncedContractor::factory()->create();
        $contractor2 = SyncedContractor::factory()->create();

        $contractorUser = User::factory()->create(['contractor_id' => $contractor1->id]);
        $worker = User::factory()->create(['contractor_id' => $contractor2->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor2->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor2->id,
            'status' => TaskStatus::Draft->value,
        ]);

        $response = $this->actingAs($contractorUser)->post(route('tasks.assign', $task->id), [
            'user_id' => $worker->id,
            'reason' => 'Illegal assignment attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_assign_task_request_to_dto_mapping(): void
    {
        $manager = User::factory()->create(['contractor_id' => null]);
        $task = Task::factory()->create();

        $this->actingAs($manager);

        $request = AssignTaskRequest::create("/tasks/{$task->id}/assign", 'POST', [
            'user_id' => 42,
            'reason' => 'Exact reason test',
        ]);

        $route = new Route('POST', 'tasks/{task}/assign', []);
        $route->bind($request);
        $route->setParameter('task', $task);
        $request->setRouteResolver(fn () => $route);

        $dto = $request->toDto();

        $this->assertInstanceOf(AssignTaskData::class, $dto);
        $this->assertEquals($task->id, $dto->task_id);
        $this->assertEquals(42, $dto->user_id);
        $this->assertEquals($manager->id, $dto->assigned_by);
        $this->assertEquals('Exact reason test', $dto->reason);
    }

    /**
     * ==========================================
     * Fix #2: Task Submission Tests
     * ==========================================
     */
    public function test_contractor_can_submit_eligible_task(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $worker = User::factory()->create(['contractor_id' => $contractor->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::InProgress->value,
        ]);

        // Start SLA so stopResolutionSla has an active record to stop
        app(SlaService::class)->startResolutionSla($task);

        $response = $this->actingAs($worker)->post(route('tasks.submit', $task->id));

        $response->assertRedirect(route('tasks.show', $task->id));
        $response->assertSessionHas('status', 'وظیفه با موفقیت جهت بررسی ثبت شد.');

        $task->refresh();
        $this->assertEquals(TaskStatus::SubmittedForReview->value, $task->status);

        // Verify resolution SLA was stopped
        $sla = SlaRecord::where('task_id', $task->id)->where('sla_type', SlaType::Resolution->value)->first();
        $this->assertNotNull($sla);
        $this->assertNotNull($sla->stopped_at);
    }

    public function test_task_submission_rejected_for_invalid_state(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $worker = User::factory()->create(['contractor_id' => $contractor->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::Draft->value,
        ]);

        $response = $this->actingAs($worker)->post(route('tasks.submit', $task->id));

        $response->assertRedirect(route('tasks.show', $task->id));
        $response->assertSessionHas('error');

        $task->refresh();
        $this->assertEquals(TaskStatus::Draft->value, $task->status);
    }

    public function test_unauthorized_contractor_cannot_submit_task(): void
    {
        $contractor1 = SyncedContractor::factory()->create();
        $contractor2 = SyncedContractor::factory()->create();

        $workerFromOther = User::factory()->create(['contractor_id' => $contractor2->id]);

        $project = Project::factory()->create(['contractor_id' => $contractor1->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor1->id,
            'status' => TaskStatus::InProgress->value,
        ]);

        $response = $this->actingAs($workerFromOther)->post(route('tasks.submit', $task->id));

        $response->assertStatus(403);
    }

    /**
     * ==========================================
     * Fix #3: SLA Display Tests
     * ==========================================
     */
    public function test_sla_records_display_correctly_on_show_page(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $manager = User::factory()->create(['contractor_id' => null]);

        $project = Project::factory()->create(['contractor_id' => $contractor->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
            'status' => TaskStatus::InProgress->value,
        ]);

        // Create Response SLA (stopped)
        SlaRecord::create([
            'task_id' => $task->id,
            'sla_type' => SlaType::Response->value,
            'target_duration' => 60,
            'started_at' => now()->subMinutes(30),
            'stopped_at' => now()->subMinutes(10),
            'actual_duration' => 20,
            'status' => 'stopped',
            'is_breached' => false,
        ]);

        // Create Resolution SLA (active)
        SlaRecord::create([
            'task_id' => $task->id,
            'sla_type' => SlaType::Resolution->value,
            'target_duration' => 120,
            'started_at' => now()->subMinutes(45),
            'stopped_at' => null,
            'actual_duration' => null,
            'status' => 'active',
            'is_breached' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('tasks.show', $task->id));

        $response->assertStatus(200);
        $response->assertSee('وضعیت SLA');
        $response->assertSee('SLA پاسخ اولیه');
        $response->assertSee('SLA تکمیل و حل');
        $response->assertSee('متوقف شده');
        $response->assertSee('در حال محاسبه');
        $response->assertSee('20 دقیقه');
        $response->assertSee('120 دقیقه');
    }

    /**
     * ==========================================
     * Fix #4: Dependency Removal Tests (POST)
     * ==========================================
     */
    public function test_authorized_manager_can_remove_dependency_via_post(): void
    {
        $manager = User::factory()->create(['contractor_id' => null]);
        $project = Project::factory()->create();

        $task1 = Task::factory()->create(['project_id' => $project->id]);
        $task2 = Task::factory()->create(['project_id' => $project->id]);

        $dependency = TaskDependency::create([
            'successor_task_id' => $task1->id,
            'predecessor_task_id' => $task2->id,
            'dependency_type' => 'fs',
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->post(
            route('tasks.dependencies.destroy', [$task1->id, $dependency->id])
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'پیش‌نیاز با موفقیت حذف شد.');

        $this->assertDatabaseMissing('task_dependencies', [
            'id' => $dependency->id,
        ]);
    }

    public function test_contractor_cannot_remove_dependency(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $worker = User::factory()->create(['contractor_id' => $contractor->id]);
        $project = Project::factory()->create(['contractor_id' => $contractor->id]);

        $task1 = Task::factory()->create(['project_id' => $project->id, 'contractor_id' => $contractor->id]);
        $task2 = Task::factory()->create(['project_id' => $project->id, 'contractor_id' => $contractor->id]);

        $dependency = TaskDependency::create([
            'successor_task_id' => $task1->id,
            'predecessor_task_id' => $task2->id,
            'dependency_type' => 'fs',
            'created_by' => $worker->id,
        ]);

        // Attempt removal from unauthorized contractor
        $contractor2 = SyncedContractor::factory()->create();
        $otherWorker = User::factory()->create(['contractor_id' => $contractor2->id]);

        $response = $this->actingAs($otherWorker)->post(
            route('tasks.dependencies.destroy', [$task1->id, $dependency->id])
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('task_dependencies', ['id' => $dependency->id]);
    }

    public function test_removing_dependency_of_different_task_fails(): void
    {
        $manager = User::factory()->create(['contractor_id' => null]);
        $project = Project::factory()->create();

        $task1 = Task::factory()->create(['project_id' => $project->id]);
        $task2 = Task::factory()->create(['project_id' => $project->id]);
        $task3 = Task::factory()->create(['project_id' => $project->id]);

        // Dependency between task2 and task3
        $dependency = TaskDependency::create([
            'successor_task_id' => $task2->id,
            'predecessor_task_id' => $task3->id,
            'dependency_type' => 'fs',
            'created_by' => $manager->id,
        ]);

        // Attempting to delete using task1 in the route
        $response = $this->actingAs($manager)->post(
            route('tasks.dependencies.destroy', [$task1->id, $dependency->id])
        );

        $response->assertStatus(404);
        $this->assertDatabaseHas('task_dependencies', ['id' => $dependency->id]);
    }
}
