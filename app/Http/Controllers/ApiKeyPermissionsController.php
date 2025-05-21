<?php

namespace App\Http\Controllers;

use App\Models\ExchangeApiKey;
use App\Models\ExchangeApiKeyLog;
use App\Services\ApiKeys\ApiKeyPermissionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyPermissionsController extends Controller
{
    protected ApiKeyPermissionsService $permissionsService;

    public function __construct(ApiKeyPermissionsService $permissionsService)
    {
        $this->permissionsService = $permissionsService;
    }

    public function getPermissions(int $id): JsonResponse
    {
        $user = Auth::user();
        $apiKey = $user->exchangeApiKeys()->with('exchange')->findOrFail($id);

        $currentPermissions = $apiKey->permissions ?? [];
        $availablePermissions = $this->permissionsService->getAvailablePermissions($apiKey->exchange->slug);

        $permissionsData = [];

        foreach ($availablePermissions as $permission => $permissionInfo) {
            $permissionsData[] = [
                'key' => $permission,
                'name' => $permissionInfo['name'],
                'description' => $permissionInfo['description'],
                'is_required' => $permissionInfo['is_required'],
                'is_enabled' => in_array($permission, $currentPermissions, true)
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'api_key_id' => $apiKey->id,
                'exchange' => $apiKey->exchange->name,
                'permissions' => $permissionsData,
                'trading_enabled' => $apiKey->trading_enabled
            ]
        ]);
    }

    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $apiKey = Auth
            ::user()
            ?->exchangeApiKeys()
            ->with('exchange')
            ->findOrFail($id);

        $permissions = $request->input('permissions', []);
        $tradingEnabled = $request->boolean('trading_enabled');

        $hasTrading = in_array(ApiKeyPermissionsService::PERMISSION_TRADING, $permissions, true);

        if ($tradingEnabled && !$hasTrading) {
            return response()->json([
                'status' => 'error',
                'message' => 'Для увімкнення торгівлі необхідно мати дозвіл на торгівлю'
            ], 422);
        }

        $this->permissionsService->setPermissions($apiKey, $permissions);

        $apiKey->update(['trading_enabled' => $tradingEnabled && $hasTrading]);

        $this->logPermissionsUpdate($apiKey, $permissions, $tradingEnabled);

        return response()->json([
            'status' => 'success',
            'message' => 'Дозволи API ключа оновлено успішно',
            'data' => [
                'api_key_id' => $apiKey->id,
                'permissions' => $apiKey->permissions,
                'trading_enabled' => $apiKey->trading_enabled
            ]
        ]);
    }

    public function getAvailablePermissions(string $exchangeSlug): JsonResponse
    {
        $availablePermissions = $this->permissionsService->getAvailablePermissions($exchangeSlug);

        $permissionsData = [];

        foreach ($availablePermissions as $permission => $permissionInfo) {
            $permissionsData[] = [
                'key' => $permission,
                'name' => $permissionInfo['name'],
                'description' => $permissionInfo['description'],
                'is_required' => $permissionInfo['is_required']
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'exchange' => $exchangeSlug,
                'permissions' => $permissionsData
            ]
        ]);
    }

    private function logPermissionsUpdate(ExchangeApiKey $apiKey, array $permissions, bool $tradingEnabled): void
    {
        ExchangeApiKeyLog::create([
            'exchange_api_key_id' => $apiKey->id,
            'action' => 'permissions_update',
            'details' => [
                'previous_permissions' => $apiKey->getOriginal('permissions'),
                'new_permissions' => $permissions,
                'previous_trading_enabled' => $apiKey->getOriginal('trading_enabled'),
                'new_trading_enabled' => $tradingEnabled
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
