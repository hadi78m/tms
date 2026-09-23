<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.8 — M-01: create_modules_table
 *
 * A Module owns a share of the development scope of a Project
 * (OQ-01a = 1A). Invariant: SUM(active modules.weight) = 100 — enforced in the
 * Service layer with a parent lock on projects.id (DEC-022 · DEC-023 · DEC-024);
 * no aggregate CHECK is faked here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('restrict');
            $table->string('name', 255);
            $table->string('code', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->string('status', 50)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->unique(['project_id', 'code'], 'uq_modules_project_code');
            $table->index('project_id', 'idx_modules_project');
            $table->index(['project_id', 'status'], 'idx_modules_project_status');
        });

        DB::statement('ALTER TABLE modules ADD CONSTRAINT chk_modules_weight CHECK (weight > 0 AND weight <= 100)');
        DB::statement('ALTER TABLE modules ADD CONSTRAINT chk_modules_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)');
        DB::statement("ALTER TABLE modules ADD CONSTRAINT chk_modules_name CHECK (length(btrim(name)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
