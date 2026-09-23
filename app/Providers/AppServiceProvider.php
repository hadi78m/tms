<?php

namespace App\Providers;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Services\DatabaseAuditService;
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
        //
    }
}
