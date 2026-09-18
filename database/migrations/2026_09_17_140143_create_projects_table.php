<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('synced_contracts')->onDelete('restrict');
            $table->foreignId('contractor_id')->constrained('synced_contractors')->onDelete('restrict');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 50);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index('contractor_id');
        });

        DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_project_dates CHECK (end_date IS NULL OR end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
