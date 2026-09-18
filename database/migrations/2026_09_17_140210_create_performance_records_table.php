<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('synced_contracts')->onDelete('restrict');
            $table->foreignId('contractor_id')->constrained('synced_contractors')->onDelete('restrict');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('tasks_completed');
            $table->decimal('total_weight_completed', 8, 2);
            $table->integer('rework_count');
            $table->integer('sla_breach_count');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['contract_id', 'contractor_id', 'period_start', 'period_end'], 'idx_perf_records_unique');
        });

        DB::statement('ALTER TABLE performance_records ADD CONSTRAINT chk_perf_period CHECK (period_end >= period_start)');
        DB::statement('ALTER TABLE performance_records ADD CONSTRAINT chk_perf_tasks_completed CHECK (tasks_completed >= 0)');
        DB::statement('ALTER TABLE performance_records ADD CONSTRAINT chk_perf_weight_completed CHECK (total_weight_completed >= 0 AND total_weight_completed <= 100)');
        DB::statement('ALTER TABLE performance_records ADD CONSTRAINT chk_perf_rework CHECK (rework_count >= 0)');
        DB::statement('ALTER TABLE performance_records ADD CONSTRAINT chk_perf_sla_breach CHECK (sla_breach_count >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');
    }
};
