<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['user_id', 'assigned_at']);
            $table->index('assigned_by');
        });

        DB::statement('CREATE UNIQUE INDEX idx_active_assignment ON task_assignments (task_id) WHERE ended_at IS NULL');
        DB::statement('ALTER TABLE task_assignments ADD CONSTRAINT chk_ta_dates CHECK (ended_at IS NULL OR ended_at >= assigned_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
