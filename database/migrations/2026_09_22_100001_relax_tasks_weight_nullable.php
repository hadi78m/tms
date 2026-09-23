<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.8 — M-07: relax_tasks_weight_nullable
 *
 * `tasks.weight` is DEPRECATED in V1.8 (BD-07: a Task has no Business Weight).
 * This migration only DROPS the NOT NULL constraint — it removes nothing and
 * rewrites no data. `chk_task_weight` (0..100) stays intact and still applies
 * to every non-NULL value.
 *
 * BATCH SEPARATION (H-2 · DEC-018):
 *   down() below throws on purpose. If this migration lived in the same batch
 *   as the other eight V1.8 migrations, a single failing down() would abort the
 *   whole `migrate:rollback` command and M-01..M-06/M-08/M-09 would never be
 *   reverted. It is therefore executed in its own, earlier batch.
 *
 *   php artisan migrate --path=database/migrations/2026_09_22_100001_relax_tasks_weight_nullable.php
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tasks ALTER COLUMN weight DROP NOT NULL');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'M-07 cannot be safely rolled back: tasks.weight is NULL for existing rows '
            .'and no reliable original value can be reconstructed. '
            .'Decide manually before restoring NOT NULL.'
        );
    }
};
