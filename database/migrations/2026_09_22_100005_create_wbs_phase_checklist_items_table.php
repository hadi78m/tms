<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-04: create_wbs_phase_checklist_items_table
 *
 * A WBS Phase has no weight at all — only completion. The checklist items
 * express complete / incomplete and who+when changed it. Items carry NO
 * computational weight either.
 *
 * `completed_by` must be nullable because chk_wpci_completion_consistency ties
 * it to is_completed = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbs_phase_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wbs_phase_id')->constrained('wbs_phases')->onDelete('restrict');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index('wbs_phase_id', 'idx_wpci_phase');
            $table->index(['wbs_phase_id', 'is_completed'], 'idx_wpci_phase_completed');
        });

        DB::statement(<<<'SQL'
ALTER TABLE wbs_phase_checklist_items ADD CONSTRAINT chk_wpci_completion_consistency CHECK (
    (is_completed = false AND completed_at IS NULL AND completed_by IS NULL)
    OR
    (is_completed = true  AND completed_at IS NOT NULL AND completed_by IS NOT NULL)
)
SQL);
        DB::statement("ALTER TABLE wbs_phase_checklist_items ADD CONSTRAINT chk_wpci_title CHECK (length(btrim(title)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('wbs_phase_checklist_items');
    }
};
