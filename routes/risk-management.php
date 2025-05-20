<?php

use App\Http\Controllers\RiskManagement\RiskManagementController;
use Illuminate\Support\Facades\Route;

Route
    ::middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('risk-management')->group(function () {
            Route::get('/strategies', [RiskManagementController::class, 'index']);
            Route::get('/strategies/{id}', [RiskManagementController::class, 'show']);
            Route::post('/strategies', [RiskManagementController::class, 'store']);
            Route::put('/strategies/{id}', [RiskManagementController::class, 'update']);
            Route::delete('/strategies/{id}', [RiskManagementController::class, 'destroy']);
            Route::post('/strategies/{id}/activate', [RiskManagementController::class, 'activate']);
            Route::post('/strategies/{id}/deactivate', [RiskManagementController::class, 'deactivate']);

            Route::post('/calculate-position-size', [RiskManagementController::class, 'calculatePositionSize']);
            Route::post('/calculate-pnl', [RiskManagementController::class, 'calculatePotentialPnL']);

            Route::post('/positions/{id}/activate-trailing-stop', [RiskManagementController::class, 'activateTrailingStop']);
            Route::post('/positions/{id}/deactivate-trailing-stop', [RiskManagementController::class, 'deactivateTrailingStop']);
            Route::post('/update-all-trailing-stops', [RiskManagementController::class, 'updateAllTrailingStops']);
        });
    });
