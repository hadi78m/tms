<?php

namespace Tests\Feature\Web;

use App\Models\Project;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
