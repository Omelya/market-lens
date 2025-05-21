<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationPreferencesController extends Controller
{
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = Auth::user();
        $preferences = $request->validated();

        $user->update([
            'notification_preferences' => $preferences
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Налаштування сповіщень успішно оновлено',
            'data' => $preferences
        ]);
    }

    public function show(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data' => $user->notification_preferences ?? []
        ]);
    }

    public function reset(): JsonResponse
    {
        $user = Auth::user();
        $defaultPreferences = [
            'email_notifications' => true,
            'price_alerts' => true,
            'trading_signals' => true,
            'security_alerts' => true
        ];

        $user->update([
            'notification_preferences' => $defaultPreferences
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Налаштування сповіщень скинуто до значень за замовчуванням',
            'data' => $defaultPreferences
        ]);
    }
}
