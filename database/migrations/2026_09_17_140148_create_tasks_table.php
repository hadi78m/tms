<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('restrict');
            $table->foreignId('contract_id')->constrained('synced_contracts')->onDelete('restrict');
            $table->foreignId('contractor_id')->constrained('synced_contractors')->onDelete('restrict');
            $table->foreignId('wbs_phase_id')->nullable()->constrained('wbs_phases')->onDelete('restrict');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->string('priority', 50);
            $table->string('status', 50);
            $table->timestampTz('planned_start_at')->nullable();
            $table->timestampTz('planned_due_at')->nullable();
            $table->timestampTz('actual_started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index(['project_id', 'wbs_phase_id']);
            $table->index('contract_id');
            $table->index(['contractor_id', 'status']);
            $table->index('wbs_phase_id');
            $table->index('parent_task_id');
            $table->index('created_by');
            $table->index('planned_due_at');
        });

        DB::statement('ALTER TABLE tasks ADD CONSTRAINT chk_task_weight CHECK (weight >= 0 AND weight <= 100)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT chk_task_planned_dates CHECK (planned_due_at IS NULL OR planned_start_at IS NULL OR planned_due_at >= planned_start_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
