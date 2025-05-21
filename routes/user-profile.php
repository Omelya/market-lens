<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\ApiKeyPermissionsController;
use App\Http\Controllers\ApiKeyStatisticsController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ExchangeApiKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::put('/profile', [UserProfileController::class, 'update']);

        Route::get('/notifications', [NotificationPreferencesController::class, 'show']);
        Route::put('/notifications', [NotificationPreferencesController::class, 'update']);
        Route::post('/notifications/reset', [NotificationPreferencesController::class, 'reset']);

        Route::post('/change-password', [UserProfileController::class, 'changePassword']);

        Route::get('/verify-email-change/{token}', [UserProfileController::class, 'verifyEmailChange'])
            ->name('api.users.verify-email-change');
        Route::post('/resend-verification-email', [UserProfileController::class, 'resendVerificationEmail']);
        Route::post('/cancel-email-change', [UserProfileController::class, 'cancelEmailChange']);

        Route::get('/security', [AccountSecurityController::class, 'getSecuritySettings']);
        Route::put('/security', [AccountSecurityController::class, 'updateSecuritySettings']);
        Route::get('/security/logs', [AccountSecurityController::class, 'getSecurityLog']);
        Route::get('/security/block-status', [AccountSecurityController::class, 'getBlockStatus']);
        Route::post('/security/unblock', [AccountSecurityController::class, 'unblockAccount']);
        Route::post('/security/trusted-device', [AccountSecurityController::class, 'addTrustedDevice']);
        Route::delete('/security/trusted-device', [AccountSecurityController::class, 'removeTrustedDevice']);

        Route::get('/exchange-api-keys', [ExchangeApiKeyController::class, 'index']);
        Route::post('/exchange-api-keys', [ExchangeApiKeyController::class, 'store']);
        Route::get('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'show']);
        Route::put('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'update']);
        Route::delete('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'destroy']);
        Route::post('/exchange-api-keys/{id}/verify', [ExchangeApiKeyController::class, 'verify']);

        Route::get('/exchange-api-keys/statistics', [ApiKeyStatisticsController::class, 'getAllApiKeysStatistics']);
        Route::get('/exchange-api-keys/{id}/statistics', [ApiKeyStatisticsController::class, 'getApiKeyStatistics']);
        Route::get('/exchange-api-keys/{id}/usage-history', [ApiKeyStatisticsController::class, 'getUsageHistory']);
        Route::get('/exchange-api-keys/{id}/logs', [ApiKeyStatisticsController::class, 'getApiKeyUsageLogs']);

        Route::get('/exchange-api-keys/{id}/permissions', [ApiKeyPermissionsController::class, 'getPermissions']);
        Route::put('/exchange-api-keys/{id}/permissions', [ApiKeyPermissionsController::class, 'updatePermissions']);
        Route::get('/exchanges/{slug}/permissions', [ApiKeyPermissionsController::class, 'getAvailablePermissions']);
    });
});
