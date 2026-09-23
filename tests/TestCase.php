<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * T-6 — Test Database Safety Gate.
     *
     * The V1.8 test suite relies on `RefreshDatabase`, which is a
     * framework-supported reset (equivalent to `migrate:fresh` on first use).
     * This gate makes it impossible for the suite — and therefore any
     * RefreshDatabase-driven reset — to ever run against a database that is
     * not clearly a *_testing database (the project uses `tms_testing`).
     *
     * Detection is based on the ACTUAL database name of the default
     * connection (the same target the framework's reset would use), not on a
     * separate, manually-maintained flag.
     *
     * ENFORCED SAFETY  → the test-driven reset path (RefreshDatabase).
     * DOCUMENTED SAFETY → manual artisan commands (migrate:fresh, etc.),
     *                     see docs/DATABASE_SAFETY.md.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTestDatabaseIsolated();
    }

    private function assertTestDatabaseIsolated(): void
    {
        $connection = config('database.default');
        $database = DB::connection($connection)->getDatabaseName();

        if (is_string($database) && str_ends_with($database, 'testing')) {
            return;
        }

        $this->fail(sprintf(
            'T-6 SAFETY GATE: the test suite is pointed at database "%s" (connection "%s"). '
            .'RefreshDatabase performs a destructive reset and would DESTROY it. '
            .'Set DB_DATABASE to a *_testing database (tms_testing) in phpunit.xml before running the suite. '
            .'The production database `tms` must never be used for tests.',
            $database ?? '(null)',
            $connection ?? '(null)'
        ));
    }
}
