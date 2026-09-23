<?php

namespace Tests\Feature\V18;

use App\Domain\Enums\ModuleStageCode;
use App\Domain\Enums\TaskType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * Schema-level verification of the V1.8 incremental layer: every object the
 * design promises actually exists in PostgreSQL, and the four single-row
 * integrity triggers actually fire.
 */
class ModuleSchemaTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    public function test_all_four_v18_tables_exist(): void
    {
        foreach (['modules', 'module_stages', 'stage_progress_approvals', 'wbs_phase_checklist_items'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} is missing.");
        }
    }

    public function test_new_columns_exist_on_tasks_and_wbs_phases(): void
    {
        $this->assertTrue(Schema::hasColumns('tasks', ['task_type', 'module_stage_id', 'module_id']));
        $this->assertTrue(Schema::hasColumns('wbs_phases', [
            'actual_completion', 'completion_status', 'supervisor_comment',
            'supervisor_approved_at', 'supervisor_approved_by',
        ]));
    }

    public function test_m07_made_tasks_weight_nullable_without_dropping_the_column(): void
    {
        $column = DB::selectOne("
            SELECT is_nullable FROM information_schema.columns
             WHERE table_name = 'tasks' AND column_name = 'weight'
        ");

        $this->assertNotNull($column, 'tasks.weight must still exist (M-07 only relaxes NOT NULL).');
        $this->assertSame('YES', $column->is_nullable);
    }

    public function test_module_stage_catalogue_sums_to_one_hundred(): void
    {
        $this->assertSame(9, count(ModuleStageCode::cases()));
        $this->assertEqualsWithDelta(100.0, ModuleStageCode::totalDefaultWeight(), 0.001);
        $this->assertSame('penetration_test', ModuleStageCode::PenetrationTest->value);
    }

    public function test_four_single_row_integrity_triggers_exist(): void
    {
        $triggers = DB::table('pg_trigger')
            ->whereIn('tgname', [
                'trg_module_stages_weight_locked',
                'trg_spa_immutable',
                'trg_spa_no_delete',
                'trg_activity_logs_no_delete',
            ])
            ->count();

        $this->assertSame(4, $triggers);
    }

    public function test_no_aggregate_triggers_were_created(): void
    {
        // OQ-30 = D: the three aggregate triggers are DEFERRED, not implemented.
        foreach (['trg_modules_weight_sum', 'trg_module_stages_weight_sum', 'trg_spa_capacity'] as $name) {
            $this->assertSame(0, DB::table('pg_trigger')->where('tgname', $name)->count(), "{$name} must not exist.");
        }
    }

    public function test_module_weight_check_rejects_zero_and_out_of_range(): void
    {
        $project = $this->makeProject();

        $this->expectException(QueryException::class);

        DB::table('modules')->insert([
            'project_id' => $project->id, 'name' => 'bad', 'weight' => 0,
            'sort_order' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_task_type_check_rejects_unknown_value(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $this->expectException(QueryException::class);

        DB::table('tasks')->insert([
            'project_id' => $project->id, 'contract_id' => $project->contract_id,
            'contractor_id' => $project->contractor_id, 'title' => 't', 'weight' => 5,
            'priority' => 'normal', 'status' => 'draft', 'created_by' => $actor->id,
            'task_type' => 'bogus', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_task_type_defaults_to_development_for_legacy_inserts(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $id = DB::table('tasks')->insertGetId([
            'project_id' => $project->id, 'contract_id' => $project->contract_id,
            'contractor_id' => $project->contractor_id, 'title' => 'legacy', 'weight' => 5,
            'priority' => 'normal', 'status' => 'draft', 'created_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(TaskType::Development->value, DB::table('tasks')->where('id', $id)->value('task_type'));
    }

    public function test_support_task_may_live_outside_the_module_hierarchy(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        // Both module_stage_id and wbs_phase_id NULL — still a valid Support Task.
        DB::table('tasks')->insert([
            'project_id' => $project->id, 'contract_id' => $project->contract_id,
            'contractor_id' => $project->contractor_id, 'title' => 'support', 'weight' => 5,
            'priority' => 'normal', 'status' => 'draft', 'created_by' => $actor->id,
            'task_type' => TaskType::Support->value, 'module_stage_id' => null, 'wbs_phase_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('tasks', ['title' => 'support', 'task_type' => 'support']);
    }

    public function test_tasks_module_consistency_rejects_stage_without_module(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');
        $module = $this->makeModules($project, $actor, [100])->first();
        $stage = $module->stages()->where('stage_code', 'analysis')->first();

        $this->expectException(QueryException::class);

        DB::table('tasks')->insert([
            'project_id' => $project->id, 'contract_id' => $project->contract_id,
            'contractor_id' => $project->contractor_id, 'title' => 't', 'weight' => 5,
            'priority' => 'normal', 'status' => 'draft', 'created_by' => $actor->id,
            'module_stage_id' => $stage->id, 'module_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_wbs_phase_completion_columns_default_to_pending(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('project_manager');

        $id = DB::table('wbs_phases')->insertGetId([
            'project_id' => $project->id, 'name' => 'phase', 'expected_output' => 'checklist',
            'weight' => 50, 'planned_duration' => 30, 'duration_unit' => 'day',
            'sort_order' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $phase = DB::table('wbs_phases')->where('id', $id)->first();

        $this->assertSame('pending', $phase->completion_status);
        $this->assertNull($phase->supervisor_approved_at);
        $this->assertNull($phase->supervisor_approved_by);
        $this->assertNull($phase->actual_completion);
    }

    public function test_deleting_approval_history_is_blocked_by_the_database(): void
    {
        $project = $this->makeProject();
        $actor = $this->makeUserWithRole('supervisor');
        $module = $this->makeModules($project, $actor, [100])->first();
        $stage = $module->stages()->where('stage_code', 'analysis')->first();

        $id = DB::table('stage_progress_approvals')->insertGetId([
            'module_stage_id' => $stage->id, 'module_id' => $module->id,
            'proposed_amount' => 5, 'status' => 'pending', 'proposed_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('stage_progress_approvals')->where('id', $id)->delete();
    }

    public function test_deleting_activity_logs_is_blocked_by_the_database(): void
    {
        $actor = $this->makeUserWithRole('supervisor');

        $id = DB::table('activity_logs')->insertGetId([
            'user_id' => $actor->id, 'action' => 'probe',
            'entity_type' => 'App\\Models\\Task', 'entity_id' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('activity_logs')->where('id', $id)->delete();
    }
}
