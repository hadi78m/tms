<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synced_contractors', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 255);
            $table->string('source_system', 255);
            $table->string('name', 255);
            $table->string('code', 255);
            $table->string('status', 50);
            $table->timestampTz('source_updated_at');
            $table->timestampTz('last_synced_at');
            $table->string('sync_status', 50);
            $table->text('sync_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->unique(['source_system', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synced_contractors');
    }
};
