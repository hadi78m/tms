<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('restrict');
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');
            $table->decimal('old_weight', 5, 2);
            $table->decimal('new_weight', 5, 2);
            $table->text('reason');
            $table->string('status', 50);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('task_id');
            $table->index('requested_by');
            $table->index('approved_by');
        });

        DB::statement('ALTER TABLE weight_change_requests ADD CONSTRAINT chk_wcr_old_weight CHECK (old_weight >= 0 AND old_weight <= 100)');
        DB::statement('ALTER TABLE weight_change_requests ADD CONSTRAINT chk_wcr_new_weight CHECK (new_weight >= 0 AND new_weight <= 100)');
        DB::statement('ALTER TABLE weight_change_requests ADD CONSTRAINT chk_wcr_weight_diff CHECK (new_weight <> old_weight)');
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_change_requests');
    }
};
