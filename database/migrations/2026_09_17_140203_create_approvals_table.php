<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('restrict');
            $table->string('approval_type', 50);
            $table->integer('sequence');
            $table->string('status', 50);
            $table->timestampTz('requested_at');
            $table->foreignId('acted_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['task_id', 'approval_type', 'sequence']);
            $table->index('acted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
