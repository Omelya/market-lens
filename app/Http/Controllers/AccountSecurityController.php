<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSecuritySettingsRequest;
use App\Services\Security\AccountBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSecurityController extends Controller
{
    protected AccountBlockService $blockService;

    public function __construct(AccountBlockService $blockService)
    {
        $this->blockService = $blockService;
    }

    public function getSecuritySettings(): JsonResponse
    {
        $user = Auth::user();
        $settings = $user->security_settings ?? [
            'login_notifications' => true,
            'trusted_devices' => [],
            'ip_whitelist' => [],
            'auto_logout_time' => 30, // minutes
            'sensitive_action_verification' => true
        ];

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function updateSecuritySettings(UpdateSecuritySettingsRequest $request): JsonResponse
    {
        $user = Auth::user();
        $settings = $request->validated();

        $user->update([
            'security_settings' => $settings
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Налаштування безпеки успішно оновлено',
            'data' => $settings
        ]);
    }

    public function getSecurityLog(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);

        $logs = $user->recentActivity()
            ->whereIn('action', [
                'login', 'login_failed', 'logout', 'password_change',
                'api_key_added', 'api_key_deleted', 'new_device_detected'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    public function addTrustedDevice(Request $request): JsonResponse
    {
        $user = Auth::user();
        $deviceName = $request->input('device_name', 'Unnamed Device');
        $deviceId = $request->input('device_id');

        if (!$deviceId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ідентифікатор пристрою обов\'язковий'
            ], 422);
        }

        $securitySettings = $user->security_settings ?? [];
        $trustedDevices = $securitySettings['trusted_devices'] ?? [];

        foreach ($trustedDevices as $device) {
            if ($device['id'] === $deviceId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Цей пристрій вже додано до списку довірених'
                ], 422);
            }
        }

        $trustedDevices[] = [
            'id' => $deviceId,
            'name' => $deviceName,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'added_at' => now()->toIso8601String()
        ];

        $securitySettings['trusted_devices'] = $trustedDevices;
        $user->update(['security_settings' => $securitySettings]);

        return response()->json([
            'status' => 'success',
            'message' => 'Пристрій додано до списку довірених',
            'data' => $trustedDevices
        ]);
    }

    public function removeTrustedDevice(Request $request): JsonResponse
    {
        $user = Auth::user();
        $deviceId = $request->input('device_id');

        if (!$deviceId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ідентифікатор пристрою обов\'язковий'
            ], 422);
        }

        $securitySettings = $user->security_settings ?? [];
        $trustedDevices = $securitySettings['trusted_devices'] ?? [];

        $found = false;

        foreach ($trustedDevices as $key => $device) {
            if ($device['id'] === $deviceId) {
                unset($trustedDevices[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json([
                'status' => 'error',
                'message' => 'Пристрій не знайдено у списку довірених'
            ], 404);
        }

        $securitySettings['trusted_devices'] = array_values($trustedDevices);
        $user->update(['security_settings' => $securitySettings]);

        return response()->json([
            'status' => 'success',
            'message' => 'Пристрій видалено зі списку довірених',
            'data' => $securitySettings['trusted_devices']
        ]);
    }

    public function getBlockStatus(): JsonResponse
    {
        $user = Auth::user();
        $blockInfo = $this->blockService->getBlockInfo($user->id);

        return response()->json([
            'status' => 'success',
            'data' => $blockInfo
        ]);
    }

    public function unblockAccount(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->blockService->unblockAccount($user->id);

        if ($result) {
            return response()->json([
                'status' => 'success',
                'message' => 'Обліковий запис успішно розблоковано'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Не вдалося розблокувати обліковий запис'
        ], 500);
    }
}
