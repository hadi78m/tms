<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 255);
            $table->bigInteger('attachable_id');
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size');
            $table->string('checksum', 128)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
