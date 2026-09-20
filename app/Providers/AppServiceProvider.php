<?php

namespace App\Providers;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Services\NullAuditService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->bind(
            AuditServiceInterface::class,
            NullAuditService::class
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
