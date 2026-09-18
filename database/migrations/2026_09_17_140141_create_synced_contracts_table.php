<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synced_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 255);
            $table->string('source_system', 255);
            $table->foreignId('contractor_id')->constrained('synced_contractors')->onDelete('restrict');
            $table->foreignId('system_id')->constrained('synced_systems')->onDelete('restrict');
            $table->string('contract_number', 255);
            $table->string('title', 255);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 15, 2);
            $table->string('status', 50);
            $table->timestampTz('source_updated_at');
            $table->timestampTz('last_synced_at');
            $table->string('sync_status', 50);
            $table->text('sync_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->unique(['source_system', 'external_id']);
            $table->index('contractor_id');
            $table->index('system_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synced_contracts');
    }
};
