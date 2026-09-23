<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-05: add_v18_columns_to_tasks
 *
 * `task_type` is the authoritative Business Truth for task kind. A Task must
 * NOT be typed solely from `module_stage_id` (BD-02): a Support Task may have
 * both `module_stage_id` and `wbs_phase_id` NULL and still be valid
 * (OQ-04 = 2B, DEC-016).
 *
 * DEFAULT 'development' is REQUIRED, not cosmetic: it back-fills every existing
 * task row inside the same ALTER TABLE, so NOT NULL can be satisfied
 * immediately without a separate data query (DEC-016).
 *
 * The DEFAULT is intentionally kept afterwards: today no application path sets
 * `task_type`, so dropping it would break every existing task-creation route.
 * (Technical note T-2 of the Final Design Reconciliation stays open.)
 *
 * Cross-table consistency (`tasks.module_id` vs `module_stages.module_id`, and
 * Stage/Module belonging to the same Project) is a Service rule — no trigger
 * (DEC-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type', 50)->default('development');
            $table->foreignId('module_stage_id')->nullable()->constrained('module_stages')->onDelete('restrict');
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('restrict');

            $table->index('task_type', 'idx_tasks_task_type');
            $table->index(['task_type', 'status'], 'idx_tasks_task_type_status');
            $table->index('module_stage_id', 'idx_tasks_module_stage');
            $table->index('module_id', 'idx_tasks_module');
        });

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT chk_tasks_task_type CHECK (task_type IN ('development', 'support'))");
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT chk_tasks_module_consistency CHECK (module_stage_id IS NULL OR module_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_tasks_task_type');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_tasks_module_consistency');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_task_type');
            $table->dropIndex('idx_tasks_task_type_status');
            $table->dropIndex('idx_tasks_module_stage');
            $table->dropIndex('idx_tasks_module');

            $table->dropConstrainedForeignId('module_stage_id');
            $table->dropConstrainedForeignId('module_id');
            $table->dropColumn('task_type');
        });
    }
};
