<?php

use App\Http\Controllers\TechnicalIndicatorController;

Route
    ::middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('indicators')->group(function () {
            Route::get('/', [TechnicalIndicatorController::class, 'index']);
            Route::get('/{id}', [TechnicalIndicatorController::class, 'show']);

            Route::post('/calculate', [TechnicalIndicatorController::class, 'calculate']);
            Route::post('/calculate-all/{tradingPairId}', [TechnicalIndicatorController::class, 'calculateAll']);

            Route::get('/{indicatorId}/trading-pairs/{tradingPairId}/latest', [TechnicalIndicatorController::class, 'latest']);

            Route::get('/trading-pairs', [TechnicalIndicatorController::class, 'availablePairs']);
            Route::get('/timeframes/{tradingPairId}', [TechnicalIndicatorController::class, 'availableTimeframes']);
        });
    });
