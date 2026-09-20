<?php

namespace Tests\Feature\Web;

use App\Models\Project;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WebTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_can_only_see_their_own_tasks(): void
    {
        $contractor1 = SyncedContractor::factory()->create();
        $contractor2 = SyncedContractor::factory()->create();

        $project1 = Project::factory()->create(['contractor_id' => $contractor1->id]);
        $project2 = Project::factory()->create(['contractor_id' => $contractor2->id]);

        Task::factory()->create(['project_id' => $project1->id, 'title' => 'Task for C1']);
        Task::factory()->create(['project_id' => $project2->id, 'title' => 'Task for C2']);

        $userC1 = User::factory()->create(['contractor_id' => $contractor1->id]);

        $response = $this->actingAs($userC1)->get('/tasks');

        $response->assertStatus(200);
        $response->assertSee('Task for C1');
        $response->assertDontSee('Task for C2');
    }

    public function test_manager_can_see_all_tasks(): void
    {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();

        Task::factory()->create(['project_id' => $project1->id, 'title' => 'Task for P1']);
        Task::factory()->create(['project_id' => $project2->id, 'title' => 'Task for P2']);

        $manager = User::factory()->create(['contractor_id' => null]);

        $response = $this->actingAs($manager)->get('/tasks');

        $response->assertStatus(200);
        $response->assertSee('Task for P1');
        $response->assertSee('Task for P2');
    }

    public function test_manager_can_access_create_task_page(): void
    {
        $manager = User::factory()->create(['contractor_id' => null]);
        $project = Project::factory()->create(['name' => 'Project Alpha']);

        $response = $this->actingAs($manager)->get('/tasks/create');

        $response->assertStatus(200);
        $response->assertSee('Project Alpha');
        $response->assertSee('ایجاد وظیفه جدید');
    }

    public function test_dashboard_renders_with_correct_stats(): void
    {
        $contractor = SyncedContractor::factory()->create();
        $userC1 = User::factory()->create(['contractor_id' => $contractor->id, 'name' => 'John Doe']);
        $project = Project::factory()->create(['contractor_id' => $contractor->id]);

        Task::factory()->create(['project_id' => $project->id, 'status' => 'pending']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress']);

        $response = $this->actingAs($userC1)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('وظایف من');
        $response->assertSee('توزیع وضعیت وظایف');
    }

    public function test_supervisor_sees_technical_approval_buttons_in_under_review_status(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'technical_approval', 'guard_name' => 'web']);

        $supervisor = User::factory()->create(['contractor_id' => null]);
        $supervisor->givePermissionTo('technical_approval');

        $task = Task::factory()->create(['status' => 'under_review']);

        $response = $this->actingAs($supervisor)->get("/tasks/{$task->id}");

        $response->assertStatus(200);
        $response->assertSee('تایید فنی (ناظر)');
        $response->assertSee('id="btn-technical-approve"', false);

        $response->assertDontSee('id="btn-final-approve"', false);
    }

    public function test_employer_sees_final_approval_buttons_in_supervisor_approved_status(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'final_approval', 'guard_name' => 'web']);

        $employer = User::factory()->create(['contractor_id' => null]);
        $employer->givePermissionTo('final_approval');

        $task = Task::factory()->create(['status' => 'supervisor_approved']);

        $response = $this->actingAs($employer)->get("/tasks/{$task->id}");

        $response->assertStatus(200);
        $response->assertSee('تایید نهایی (بهره‌بردار)');
        $response->assertSee('id="btn-final-approve"', false);

        $response->assertDontSee('id="btn-technical-approve"', false);
    }

    public function test_supervisor_cannot_see_technical_approval_when_not_under_review(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'technical_approval', 'guard_name' => 'web']);

        $supervisor = User::factory()->create(['contractor_id' => null]);
        $supervisor->givePermissionTo('technical_approval');

        $task = Task::factory()->create(['status' => 'supervisor_approved']);

        $response = $this->actingAs($supervisor)->get("/tasks/{$task->id}");

        $response->assertStatus(200);
        $response->assertDontSee('id="btn-technical-approve"', false);
    }
}
