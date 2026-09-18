<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_task_id')->constrained('tasks')->onDelete('restrict');
            $table->foreignId('successor_task_id')->constrained('tasks')->onDelete('restrict');
            $table->string('dependency_type', 50);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['predecessor_task_id', 'successor_task_id', 'dependency_type'], 'idx_task_deps_unique');
            $table->index('successor_task_id');
            $table->index('created_by');
        });

        DB::statement('ALTER TABLE task_dependencies ADD CONSTRAINT chk_task_deps_diff CHECK (predecessor_task_id <> successor_task_id)');
        DB::statement("ALTER TABLE task_dependencies ADD CONSTRAINT chk_task_deps_type CHECK (dependency_type = 'fs')");
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
