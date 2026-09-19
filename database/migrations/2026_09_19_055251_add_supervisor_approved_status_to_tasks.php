<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase V1.3 — Two-Tier Approval & Employer Workflow.
 *
 * Adds supervisor_approved_at to track when supervisor (tier-1) approved a task.
 * The tasks.status column is VARCHAR and already accepts 'supervisor_approved'
 * as defined in App\Domain\Enums\TaskStatus.
 *
 * New state flow: under_review → supervisor_approved → approved
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestampTz('supervisor_approved_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('supervisor_approved_at');
        });
    }
};
