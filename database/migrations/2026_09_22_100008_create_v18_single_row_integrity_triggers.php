<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.8 — M-08: create_v18_single_row_integrity_triggers
 *
 * Scope is deliberately restricted to SINGLE-ROW integrity
 * (OQ-30 = option D · DEC-020 · DEC-029):
 *
 *   1. trg_module_stages_weight_locked   BEFORE UPDATE on module_stages
 *   2. trg_spa_immutable                 BEFORE UPDATE on stage_progress_approvals
 *   3. trg_spa_no_delete                 BEFORE DELETE on stage_progress_approvals
 *   4. trg_activity_logs_no_delete       BEFORE DELETE on activity_logs
 *
 * The three AGGREGATE triggers (modules weight sum, module_stages weight sum,
 * cumulative approval ceiling) are NOT part of this migration. They remain
 * DEFERRED by owner decision and, if ever built, are a backstop — never a
 * replacement for the parent-lock doctrine (DEC-023 · DEC-026).
 *
 * These are the first non-internal triggers in this schema; the baseline had
 * zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Stage weight lock after the first approval (OQ-05 = 3A · §6.3) ──
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION assert_stage_weight_locked()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.weight IS DISTINCT FROM OLD.weight THEN
        IF EXISTS (
            SELECT 1 FROM stage_progress_approvals
             WHERE module_stage_id = OLD.id
             LIMIT 1
        ) THEN
            RAISE EXCEPTION
                'module_stages.weight is locked after the first approval (stage id=%)',
                OLD.id
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;

    RETURN NEW;
END;
$$
SQL);

        // ── 2. Decided rows are immutable (N-1 · DEC-027) ──
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION assert_stage_approval_immutable()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.status <> 'pending' THEN
        RAISE EXCEPTION
            'stage_progress_approvals row % is immutable once decided (status=%)',
            OLD.id, OLD.status
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$
SQL);

        // ── 3. Shared delete guard for immutable history (OQ-31 · DEC-029) ──
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION assert_history_not_deletable()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION
        'Rows in %.% are immutable history and cannot be deleted (id=%)',
        TG_TABLE_SCHEMA, TG_TABLE_NAME, OLD.id
        USING ERRCODE = 'check_violation';
END;
$$
SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_module_stages_weight_locked ON module_stages');
        DB::statement(<<<'SQL'
CREATE TRIGGER trg_module_stages_weight_locked
BEFORE UPDATE ON module_stages
FOR EACH ROW
EXECUTE FUNCTION assert_stage_weight_locked()
SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_spa_immutable ON stage_progress_approvals');
        DB::statement(<<<'SQL'
CREATE TRIGGER trg_spa_immutable
BEFORE UPDATE ON stage_progress_approvals
FOR EACH ROW
EXECUTE FUNCTION assert_stage_approval_immutable()
SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_spa_no_delete ON stage_progress_approvals');
        DB::statement(<<<'SQL'
CREATE TRIGGER trg_spa_no_delete
BEFORE DELETE ON stage_progress_approvals
FOR EACH ROW
EXECUTE FUNCTION assert_history_not_deletable()
SQL);

        DB::statement('DROP TRIGGER IF EXISTS trg_activity_logs_no_delete ON activity_logs');
        DB::statement(<<<'SQL'
CREATE TRIGGER trg_activity_logs_no_delete
BEFORE DELETE ON activity_logs
FOR EACH ROW
EXECUTE FUNCTION assert_history_not_deletable()
SQL);
    }

    public function down(): void
    {
        // Triggers must be dropped before the functions they depend on.
        DB::statement('DROP TRIGGER IF EXISTS trg_module_stages_weight_locked ON module_stages');
        DB::statement('DROP TRIGGER IF EXISTS trg_spa_immutable ON stage_progress_approvals');
        DB::statement('DROP TRIGGER IF EXISTS trg_spa_no_delete ON stage_progress_approvals');
        DB::statement('DROP TRIGGER IF EXISTS trg_activity_logs_no_delete ON activity_logs');

        DB::statement('DROP FUNCTION IF EXISTS assert_stage_weight_locked()');
        DB::statement('DROP FUNCTION IF EXISTS assert_stage_approval_immutable()');
        DB::statement('DROP FUNCTION IF EXISTS assert_history_not_deletable()');
    }
};
