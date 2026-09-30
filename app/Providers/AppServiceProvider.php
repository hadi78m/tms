<?php

namespace App\Providers;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Services\DatabaseAuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        // V1.8: the real, synchronous, transactional audit writer.
        // NullAuditService is kept for tests that want no-op behaviour and can be
        // swapped in with $this->app->instance(AuditServiceInterface::class, new NullAuditService).
        $this->app->bind(
            AuditServiceInterface::class,
            DatabaseAuditService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // V1.11 Approval Phase 2 · I-2 (DEC-058): explicit policy registrations.
        // The dedicated approval policies are NOT named after their models
        // (<Model>Policy), so Laravel's auto-discovery convention cannot map
        // them. The Gate resolves a policy by the RESOURCE CLASS passed to
        // authorize(); the controllers pass unsaved Approval /
        // StageProgressApproval lookahead instances carrying the target
        // relationship (task_id / module_id), so the mappings below are the
        // correct resource→policy bindings. TaskPolicy remains EXCLUSIVELY
        // registered to Task by auto-discovery (no override, no collision).
        // Business rules remain untouched — this only completes
        // resource→policy resolution (DEC-050 compliant).
        Gate::policy(App\Models\Approval::class, App\Policies\ApprovalPolicy::class);
        Gate::policy(App\Models\StageProgressApproval::class, App\Policies\StageProgressApprovalPolicy::class);
    }
}
