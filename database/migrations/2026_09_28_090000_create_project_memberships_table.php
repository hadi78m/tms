<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V1.11 — V11-01 (DEC-048 / OD-1 · OD-6 · OD-8): project_memberships.
 *
 * Generic Project Membership / Assignment (M-1):
 *   User ↔ Project, historical, period-based.
 *
 * Owner-locked decisions encoded here:
 *   OD-6-a  active  = ended_at IS NULL          (no is_active, no status)
 *   OD-6-b  timestamps = timestampTz            (point-in-time, like task_assignments)
 *   OD-6-c  no future-dated assignments         (started_at = assignment moment)
 *   OD-6-d  replacement ends the previous active assignment (service layer)
 *   OD-8    NO backfill — table starts empty; controlled/manual initial
 *           assignment (B-2) happens outside migrations via the
 *           ProjectMembershipService. This migration must NOT fabricate
 *           Supervisor ownership.
 *
 * Constraints (Concrete Design §6 · K-1..K-6):
 *   K-1  project_id  → projects.id  ON DELETE RESTRICT
 *   K-2  user_id     → users.id     ON DELETE RESTRICT
 *   K-3  assigned_by → users.id     ON DELETE RESTRICT
 *   K-4  chk_pm_dates  ended_at IS NULL OR ended_at >= started_at
 *   K-5  chk_pm_type   membership_type is a non-empty code (VARCHAR(50),
 *        NO CHECK IN (...) — repo convention C-7 so a future type needs
 *        no migration; the V1.11 value set is only 'supervisor')
 *   K-6  idx_active_project_supervisor — partial unique index:
 *        UNIQUE(project_id) WHERE ended_at IS NULL AND membership_type='supervisor'
 *        → maximum ONE active Supervisor per Project (DB-level, the last line
 *        of defense under READ COMMITTED concurrency, mirroring
 *        idx_active_assignment on task_assignments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->string('membership_type', 50);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['user_id', 'membership_type', 'ended_at'], 'idx_pm_user_active');
            $table->index('project_id', 'idx_pm_project');
            $table->index('assigned_by', 'idx_pm_assigned_by');
        });

        // K-6 — the cardinality rule: at most ONE active Supervisor per Project.
        DB::statement(
            "CREATE UNIQUE INDEX idx_active_project_supervisor ON project_memberships (project_id) ".
            "WHERE ended_at IS NULL AND membership_type = 'supervisor'"
        );

        // K-4 — date integrity (mirror of chk_ta_dates on task_assignments).
        DB::statement(
            'ALTER TABLE project_memberships ADD CONSTRAINT chk_pm_dates '.
            'CHECK (ended_at IS NULL OR ended_at >= started_at)'
        );

        // K-5 — type integrity: a non-empty code (no CHECK IN — convention C-7).
        DB::statement(
            "ALTER TABLE project_memberships ADD CONSTRAINT chk_pm_type ".
            "CHECK (length(btrim(membership_type)) > 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('project_memberships');
    }
};
