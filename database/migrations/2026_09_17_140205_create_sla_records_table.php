<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('restrict');
            $table->string('sla_type', 50);
            $table->integer('target_duration'); // User confirmed: minutes
            $table->timestampTz('started_at');
            $table->timestampTz('stopped_at')->nullable();
            $table->integer('actual_duration')->nullable(); // User confirmed: minutes
            $table->string('status', 50);
            $table->boolean('is_breached')->default(false);
            $table->timestampTz('breached_at')->nullable();
            $table->text('breach_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['task_id', 'sla_type']);
        });

        DB::statement('ALTER TABLE sla_records ADD CONSTRAINT chk_sla_target_duration CHECK (target_duration > 0)');
        DB::statement('ALTER TABLE sla_records ADD CONSTRAINT chk_sla_actual_duration CHECK (actual_duration IS NULL OR actual_duration >= 0)');
        DB::statement('ALTER TABLE sla_records ADD CONSTRAINT chk_sla_stopped_at CHECK (stopped_at IS NULL OR stopped_at >= started_at)');
        DB::statement('ALTER TABLE sla_records ADD CONSTRAINT chk_sla_breached_at CHECK (breached_at IS NULL OR breached_at >= started_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_records');
    }
};
