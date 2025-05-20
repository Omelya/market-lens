<?php

use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ExchangeApiKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::put('/profile', [UserProfileController::class, 'update']);

        Route::post('/change-password', [UserProfileController::class, 'changePassword']);

        Route::get('/verify-email-change/{token}', [UserProfileController::class, 'verifyEmailChange'])
            ->name('api.users.verify-email-change');
        Route::post('/resend-verification-email', [UserProfileController::class, 'resendVerificationEmail']);
        Route::post('/cancel-email-change', [UserProfileController::class, 'cancelEmailChange']);

        Route::get('/exchange-api-keys', [ExchangeApiKeyController::class, 'index']);
        Route::post('/exchange-api-keys', [ExchangeApiKeyController::class, 'store']);
        Route::get('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'show']);
        Route::put('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'update']);
        Route::delete('/exchange-api-keys/{id}', [ExchangeApiKeyController::class, 'destroy']);
        Route::post('/exchange-api-keys/{id}/verify', [ExchangeApiKeyController::class, 'verify']);
        Route::get('/exchange-api-keys/{id}/logs', [ExchangeApiKeyController::class, 'getActivityLog']);
    });
});
