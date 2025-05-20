<?php

use App\Http\Controllers\ExchangeController;

require __DIR__.'/auth.php';
require __DIR__.'/indicators.php';
require __DIR__.'/signals.php';
require __DIR__.'/risk-management.php';

Route
    ::middleware('auth:sanctum')
    ->group(function () {
        Route::apiResource('exchanges', ExchangeController::class)->only('index');
    });
