<?php

namespace App\Providers;

use App\Services\ApiKeys\ApiKeyPermissionsService;
use App\Services\ApiKeys\ApiKeyVerificationService;
use App\Services\Security\AccountBlockService;
use App\Services\Security\SecurityAlertsService;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiKeyVerificationService::class, function ($app) {
            return new ApiKeyVerificationService();
        });

        $this->app->singleton(ApiKeyPermissionsService::class, function ($app) {
            return new ApiKeyPermissionsService();
        });

        $this->app->singleton(AccountBlockService::class, function ($app) {
            return new AccountBlockService();
        });

        $this->app->singleton(SecurityAlertsService::class, function ($app) {
            return new SecurityAlertsService();
        });
    }

    public function boot(): void
    {
        //
    }
}
