<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-03: create_stage_progress_approvals_table
 *
 * Every approval event is a separate, immutable row. This is the single source
 * of truth for stage progress; there is no snapshot/aggregate column anywhere.
 *
 * Status domain (DEC-027): pending | approved | rejected.
 * `superseded` is a DERIVED state, never stored:
 *
 *   Active Approval ≡ status = 'approved'
 *                     AND NOT EXISTS (SELECT 1 FROM stage_progress_approvals s
 *                                      WHERE s.supersedes_approval_id = t.id)
 *
 * The self-referencing FK is added in a second step: the table must exist
 * before it can reference itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_progress_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_stage_id')->constrained('module_stages')->onDelete('restrict');
            $table->foreignId('module_id')->constrained('modules')->onDelete('restrict');
            $table->decimal('proposed_amount', 5, 2);
            $table->decimal('approved_amount', 5, 2)->nullable();
            $table->string('status', 50)->default('pending');
            $table->foreignId('proposed_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('supersedes_approval_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['module_stage_id', 'status'], 'idx_spa_stage_status');
            $table->index(['module_id', 'status'], 'idx_spa_module_status');
            $table->index('proposed_by', 'idx_spa_proposed_by');
            $table->index('final_approved_by', 'idx_spa_final_approved_by');
            $table->index('decided_at', 'idx_spa_decided_at');
            $table->index('supersedes_approval_id', 'idx_spa_supersedes');
        });

        Schema::table('stage_progress_approvals', function (Blueprint $table) {
            $table->foreign('supersedes_approval_id')
                ->references('id')
                ->on('stage_progress_approvals')
                ->onDelete('restrict');
        });

        DB::statement('ALTER TABLE stage_progress_approvals ADD CONSTRAINT chk_spa_proposed_amount CHECK (proposed_amount > 0 AND proposed_amount <= 100)');
        DB::statement('ALTER TABLE stage_progress_approvals ADD CONSTRAINT chk_spa_approved_amount CHECK (approved_amount IS NULL OR (approved_amount >= 0 AND approved_amount <= 100))');
        DB::statement('ALTER TABLE stage_progress_approvals ADD CONSTRAINT chk_spa_approved_le_proposed CHECK (approved_amount IS NULL OR approved_amount <= proposed_amount)');
        DB::statement(<<<'SQL'
ALTER TABLE stage_progress_approvals ADD CONSTRAINT chk_spa_decided_consistency CHECK (
    (status = 'pending'  AND approved_amount IS NULL
                         AND final_approved_by IS NULL
                         AND decided_at IS NULL)
    OR
    (status <> 'pending' AND final_approved_by IS NOT NULL
                         AND decided_at IS NOT NULL)
)
SQL);
        DB::statement('ALTER TABLE stage_progress_approvals ADD CONSTRAINT chk_spa_no_self_supersede CHECK (supersedes_approval_id IS NULL OR supersedes_approval_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_progress_approvals');
    }
};
