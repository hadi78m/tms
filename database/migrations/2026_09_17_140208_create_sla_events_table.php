<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_record_id')->constrained('sla_records')->onDelete('restrict');
            $table->string('event_type', 50);
            $table->timestampTz('occurred_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['sla_record_id', 'occurred_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_events');
    }
};
