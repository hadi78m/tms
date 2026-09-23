<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-06: add_v18_columns_to_wbs_phases
 *
 * A WBS Phase has NO business weight. It owns a time window, a checklist and a
 * Boolean/status completion whose final authority is the Project Supervisor.
 *
 * DEFAULT 'pending' back-fills every existing phase in the same ALTER TABLE:
 * correctly, none of them have been approved yet.
 *
 * Naming follows the repository's existing V1.3 pattern
 * (`supervisor_approved_at` / `supervisor_approved_by` on tasks).
 *
 * `expected_output` (TEXT NOT NULL) is deliberately untouched and stays LEGACY;
 * new phases must supply DEC-017's fixed text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wbs_phases', function (Blueprint $table) {
            $table->timestampTz('actual_completion')->nullable();
            $table->string('completion_status', 50)->default('pending');
            $table->text('supervisor_comment')->nullable();
            $table->timestampTz('supervisor_approved_at')->nullable();
            $table->foreignId('supervisor_approved_by')->nullable()->constrained('users')->onDelete('restrict');

            $table->index(['project_id', 'completion_status'], 'idx_wbs_phases_completion');
        });

        DB::statement("ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_completion_status CHECK (completion_status IN ('pending', 'completed', 'not_completed'))");
        DB::statement(<<<'SQL'
ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_completion_consistency CHECK (
    (completion_status = 'pending' AND supervisor_approved_at IS NULL
                                  AND supervisor_approved_by IS NULL)
    OR
    (completion_status <> 'pending' AND supervisor_approved_at IS NOT NULL
                                     AND supervisor_approved_by IS NOT NULL)
)
SQL);
        DB::statement("ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_rejection_reason CHECK (completion_status <> 'not_completed' OR length(btrim(COALESCE(supervisor_comment, ''))) > 0)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wbs_phases DROP CONSTRAINT IF EXISTS chk_wbs_completion_status');
        DB::statement('ALTER TABLE wbs_phases DROP CONSTRAINT IF EXISTS chk_wbs_completion_consistency');
        DB::statement('ALTER TABLE wbs_phases DROP CONSTRAINT IF EXISTS chk_wbs_rejection_reason');

        Schema::table('wbs_phases', function (Blueprint $table) {
            $table->dropIndex('idx_wbs_phases_completion');
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn([
                'actual_completion',
                'completion_status',
                'supervisor_comment',
                'supervisor_approved_at',
            ]);
        });
    }
};
