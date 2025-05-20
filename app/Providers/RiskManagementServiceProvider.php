<?php

namespace App\Providers;

use App\Interfaces\RiskManagerInterface;
use App\Services\RiskManagement\RiskManagerService;
use Illuminate\Support\ServiceProvider;

class RiskManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RiskManagerInterface::class, RiskManagerService::class);
    }

    public function boot(): void
    {
        //
    }
}
