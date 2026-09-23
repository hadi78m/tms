<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * V1.8 — M-09: seed_v18_reference_data
 *
 * Seeds the first real consumer of SettingsService. `supervisor_only` is the
 * default because BD-05 requires that the Project Supervisor may set and then
 * approve the percentage themselves without being restricted.
 *
 * Idempotent (updateOrInsert) and non-destructive on rollback: only the row it
 * created is removed.
 *
 * IMPLEMENTATION NOTE (deviation from §9.2 of the schema design):
 * the design named `SystemSetting::updateOrCreate`. The query builder is used
 * instead for two concrete reasons:
 *   1. `php artisan migrate --pretend` must be runnable and readable for the
 *      whole batch (§20 of the migration phase). An Eloquent insert calls
 *      `INSERT ... RETURNING id` and aborts under pretend mode with
 *      "Undefined array key 0".
 *   2. A migration must not depend on an application model: models evolve, and
 *      a migration is immutable history. `DB::table()` freezes the exact SQL.
 * The resulting row is byte-identical to what `updateOrCreate` would write.
 */
return new class extends Migration
{
    private const KEY = 'progress_approval_mode';

    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => self::KEY],
            [
                'value' => 'supervisor_only',
                'type' => 'string',
                'description' => 'حالت تعیین و تأیید درصد پیشرفت مرحله',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', self::KEY)->delete();
    }
};
