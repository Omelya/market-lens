<?php

use App\Http\Controllers\TradingSignalController;

Route
    ::middleware('auth:sanctum')
    ->prefix('api')
    ->group(function () {
        Route::prefix('signals')->group(function () {
            Route::get('/', [TradingSignalController::class, 'index']);
            Route::get('/{id}', [TradingSignalController::class, 'show']);

            Route::get('/trading-pairs/{tradingPairId}', [TradingSignalController::class, 'getSignalsForPair']);

            Route::post('/analyze/{tradingPairId}', [TradingSignalController::class, 'analyzePatterns']);
            Route::post('/update-all', [TradingSignalController::class, 'updateAllSignals']);

            Route::post('/{id}/deactivate', [TradingSignalController::class, 'deactivate']);

            Route::get('/statistics', [TradingSignalController::class, 'getStatistics']);
            Route::get('/top/risk-reward', [TradingSignalController::class, 'getTopRiskRewardSignals']);
            Route::get('/top/probability', [TradingSignalController::class, 'getTopProbabilitySignals']);

            Route::get('/available-pairs', [TradingSignalController::class, 'availablePairs']);
        });
    });
