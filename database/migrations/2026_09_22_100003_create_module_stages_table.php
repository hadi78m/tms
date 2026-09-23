<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-02: create_module_stages_table
 *
 * The nine standard stages of every Module. The Stage — not the Task — owns
 * weight (analysis 15 · design 5 · coding 35 · functional_test 3 ·
 * penetration_test 5 · training 7 · pilot 10 · production 5 · support 15 = 100).
 *
 * `stage_code` is a plain VARCHAR(50) with no `CHECK IN (...)` so that adding a
 * future stage does not require a migration (same pattern as `tasks.status`).
 *
 * Invariant SUM(module_stages.weight) = 100 per Module is a Service rule with a
 * parent lock on modules.id (DEC-022..DEC-024). No Soft Delete: a Stage is part
 * of a fixed catalogue, and superseding it would orphan approval history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('restrict');
            $table->string('stage_code', 50);
            $table->string('name', 255);
            $table->decimal('weight', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['module_id', 'stage_code'], 'uq_module_stages_module_code');
            $table->index('module_id', 'idx_module_stages_module');
            $table->index('stage_code', 'idx_module_stages_code');
        });

        DB::statement('ALTER TABLE module_stages ADD CONSTRAINT chk_module_stages_weight CHECK (weight > 0 AND weight <= 100)');
        DB::statement("ALTER TABLE module_stages ADD CONSTRAINT chk_module_stages_code CHECK (length(btrim(stage_code)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_stages');
    }
};
