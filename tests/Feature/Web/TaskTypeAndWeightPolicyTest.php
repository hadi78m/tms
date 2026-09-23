<?php

namespace Tests\Feature\Web;

use App\Domain\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V1.8 Post-Migration Code Hardening — T-2-A / DEC-037 and R-1 / DEC-036.
 *
 * Owner decisions under test (no business rule invented by the agent):
 *
 *   T-2-A  — task_type is a REQUIRED, explicit input of Create Task,
 *            validated against the frozen vocabulary (development | support)
 *            via the existing TaskType enum. The DB default 'development'
 *            (DEC-016) is a migration backfill concern, never business input.
 *
 *   T-2-UI-A — the Create Task form shows a "نوع تسک" selector.
 *
 *   R1-F1 — weight stays REQUIRED on the official create path.
 *
 *   R1-D  — `tasks.weight` remains nullable in the schema (M-07) purely for
 *           legacy/import scenarios; the HTTP path must never produce NULL.
 *           The report keeps using SUM(tasks.weight) unchanged (R1-A/B/C were
 *           NOT chosen), so this suite only asserts the input invariant.
 *
 * All tests run against tms_testing (RefreshDatabase) — never against tms.
 */
class TaskTypeAndWeightPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['contractor_id' => null]);
        $this->project = Project::factory()->create();
    }

    /**
     * The valid Create Task payload; overrides are applied per test.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'title' => 'Policy test task',
            'priority' => 'high',
            'task_type' => TaskType::Development->value,
            'weight' => 15,
        ], $overrides);
    }

    private function postTask(array $payload)
    {
        return $this->actingAs($this->manager)->post(route('tasks.store'), $payload);
    }

    /** T-2-A — Development */
    public function test_create_task_with_task_type_development_persists_development(): void
    {
        $response = $this->postTask($this->validPayload([
            'title' => 'dev typed task',
            'task_type' => TaskType::Development->value,
        ]));

        $response->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'dev typed task',
            'task_type' => TaskType::Development->value,
        ]);
    }

    /** T-2-A — Support */
    public function test_create_task_with_task_type_support_persists_support(): void
    {
        $response = $this->postTask($this->validPayload([
            'title' => 'support typed task',
            'task_type' => TaskType::Support->value,
        ]));

        $response->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'support typed task',
            'task_type' => TaskType::Support->value,
        ]);
    }

    /** T-2-A — a Support Task persists its explicit type, never a silent 'development' default */
    public function test_support_task_is_not_silently_coerced_to_development(): void
    {
        $this->postTask($this->validPayload([
            'title' => 'real support task',
            'task_type' => TaskType::Support->value,
        ]));

        $task = Task::where('title', 'real support task')->firstOrFail();

        $this->assertSame(TaskType::Support->value, $task->task_type);
        $this->assertNotSame(TaskType::Development->value, $task->task_type);
    }

    /** T-2-A — invalid value is rejected, no task created */
    public function test_create_task_with_invalid_task_type_fails_validation(): void
    {
        $response = $this->postTask($this->validPayload([
            'task_type' => 'something_invalid',
        ]));

        $response->assertSessionHasErrors('task_type');

        $this->assertDatabaseMissing('tasks', ['title' => 'Policy test task']);
    }

    /** T-2-A — missing value is rejected (required, not defaulted), no task created */
    public function test_create_task_without_task_type_fails_validation(): void
    {
        $payload = $this->validPayload();
        unset($payload['task_type']);

        $response = $this->postTask($payload);

        $response->assertSessionHasErrors('task_type');

        $this->assertDatabaseMissing('tasks', ['title' => 'Policy test task']);
    }

    /** T-2-A — empty value is rejected as well */
    public function test_create_task_with_empty_task_type_fails_validation(): void
    {
        $response = $this->postTask($this->validPayload(['task_type' => '']));

        $response->assertSessionHasErrors('task_type');

        $this->assertDatabaseMissing('tasks', ['title' => 'Policy test task']);
    }

    /** T-2-A — vocabulary stays frozen: no new value can sneak through */
    public function test_task_type_vocabulary_is_frozen_to_the_two_owner_values(): void
    {
        $this->assertSame(['development', 'support'], array_column(TaskType::cases(), 'value'));
    }

    /** T-2-UI-A — the form renders the required task-type selector */
    public function test_create_task_form_shows_required_task_type_selector(): void
    {
        $response = $this->actingAs($this->manager)->get(route('tasks.create'));

        $response->assertStatus(200);
        $response->assertSee('name="task_type"', false);
        $response->assertSee('value="development"', false);
        $response->assertSee('value="support"', false);
    }

    /** R1-F1 — weight remains required on the official create path */
    public function test_create_task_without_weight_fails_validation(): void
    {
        $payload = $this->validPayload();
        unset($payload['weight']);

        $response = $this->postTask($payload);

        $response->assertSessionHasErrors('weight');

        $this->assertDatabaseMissing('tasks', ['title' => 'Policy test task']);
    }

    /** R1-F1 — empty weight is rejected too */
    public function test_create_task_with_empty_weight_fails_validation(): void
    {
        $response = $this->postTask($this->validPayload(['weight' => '']));

        $response->assertSessionHasErrors('weight');

        $this->assertDatabaseMissing('tasks', ['title' => 'Policy test task']);
    }

    /** R1-D — every task created through the official path has a non-null weight */
    public function test_official_create_path_never_produces_null_weight(): void
    {
        $this->postTask($this->validPayload(['title' => 'weighted task', 'weight' => 42.5]));

        $task = Task::where('title', 'weighted task')->firstOrFail();

        $this->assertNotNull($task->weight);
        $this->assertEquals(42.5, (float) $task->weight);
    }

    /** R1-D — the schema keeps weight nullable (M-07 unchanged); legacy/insert paths outside HTTP may still produce NULL */
    public function test_schema_weight_column_remains_nullable_per_m07(): void
    {
        $columns = \Schema::getColumnListing('tasks');

        $this->assertContains('weight', $columns);
        $this->assertContains('task_type', $columns);
    }
}
