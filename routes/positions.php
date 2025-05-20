<?php

use App\Http\Controllers\RiskManagement\PositionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('positions')->group(function () {
            Route::get('/', [PositionController::class, 'index']);
            Route::get('/{id}', [PositionController::class, 'show']);
            Route::post('/', [PositionController::class, 'store']);
            Route::post('/{id}/close', [PositionController::class, 'close'])->name('positions.close');
            Route::post('/{id}/update-stop-loss', [PositionController::class, 'updateStopLoss'])->name('positions.updateStopLoss');
            Route::post('/{id}/update-take-profit', [PositionController::class, 'updateTakeProfit'])->name('positions.updateTakeProfit');

            Route::get('/statistics', [PositionController::class, 'getStatistics']);
        });
    });
