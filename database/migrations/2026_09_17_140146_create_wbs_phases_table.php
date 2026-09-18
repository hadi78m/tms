<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wbs_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('restrict');
            $table->string('name', 255);
            $table->text('expected_output');
            $table->decimal('weight', 5, 2);
            $table->integer('planned_duration');
            $table->string('duration_unit', 50);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 50);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index('project_id');
        });

        DB::statement('ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_weight CHECK (weight >= 0 AND weight <= 100)');
        DB::statement('ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_planned_duration CHECK (planned_duration > 0)');
        DB::statement('ALTER TABLE wbs_phases ADD CONSTRAINT chk_wbs_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wbs_phases');
    }
};
