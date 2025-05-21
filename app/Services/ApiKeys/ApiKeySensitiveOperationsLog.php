<?php

namespace App\Services\ApiKeys;

use App\Models\ExchangeApiKey;
use App\Models\ExchangeApiKeyLog;
use App\Services\Security\SecurityAlertsService;
use Illuminate\Support\Facades\Log;

class ApiKeySensitiveOperationsLog
{
    protected SecurityAlertsService $alertsService;

    public function __construct(SecurityAlertsService $alertsService)
    {
        $this->alertsService = $alertsService;
    }

    /**
     * Логує і перевіряє операцію використання API ключа.
     */
    public function logApiKeyUsage(
        ExchangeApiKey $apiKey,
        string $operation,
        array $params = [],
        bool $isHighRisk = false
    ): void
    {
        try {
            $apiKey->markAsUsed();

            ExchangeApiKeyLog::create([
                'exchange_api_key_id' => $apiKey->id,
                'action' => 'api_usage',
                'details' => [
                    'operation' => $operation,
                    'params' => $this->sanitizeParams($params),
                    'high_risk' => $isHighRisk,
                    'timestamp' => now()->toIso8601String()
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Якщо операція вважається ризикованою, відправляємо сповіщення
            if ($isHighRisk) {
                $this->alertsService->createSecurityAlert($apiKey->user_id, 'api_key_usage', [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                    'exchange' => $apiKey->exchange->name,
                    'operation' => $operation,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toIso8601String(),
                    'high_risk' => true
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log API key usage', [
                'api_key_id' => $apiKey->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Логує зміну налаштувань API ключа.
     */
    public function logApiKeySettingsChange(
        ExchangeApiKey $apiKey,
        string $settingType,
        array $oldValues,
        array $newValues
    ): void
    {
        try {
            ExchangeApiKeyLog::create([
                'exchange_api_key_id' => $apiKey->id,
                'action' => 'settings_change',
                'details' => [
                    'setting_type' => $settingType,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'timestamp' => now()->toIso8601String()
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $isCriticalChange = $this->isCriticalSettingChange($settingType, $oldValues, $newValues);

            if ($isCriticalChange) {
                $this->alertsService->createSecurityAlert($apiKey->user_id, 'api_key_settings_changed', [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                    'exchange' => $apiKey->exchange->name,
                    'setting_type' => $settingType,
                    'changes' => [
                        'from' => $oldValues,
                        'to' => $newValues
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toIso8601String()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log API key settings change', [
                'api_key_id' => $apiKey->id,
                'setting_type' => $settingType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Логує помилку використання API ключа.
     */
    public function logApiKeyError(
        ExchangeApiKey $apiKey,
        string $operation,
        string $errorMessage,
        array $params = []
    ): void
    {
        try {
            ExchangeApiKeyLog::create([
                'exchange_api_key_id' => $apiKey->id,
                'action' => 'api_error',
                'details' => [
                    'operation' => $operation,
                    'error' => $errorMessage,
                    'params' => $this->sanitizeParams($params),
                    'timestamp' => now()->toIso8601String()
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $errorCount = $this->getRecentErrorCount($apiKey->id);

            if ($errorCount >= 5) {
                $this->alertsService->createSecurityAlert($apiKey->user_id, 'api_key_errors', [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                    'exchange' => $apiKey->exchange->name,
                    'error_count' => $errorCount,
                    'last_error' => $errorMessage,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'timestamp' => now()->toIso8601String()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log API key error', [
                'api_key_id' => $apiKey->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Видаляє чутливі дані з параметрів.
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'api_key', 'api_secret', 'private_key'];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->sanitizeParams($value);
            } elseif (is_string($value) && in_array(strtolower($key), $sensitiveKeys)) {
                $params[$key] = '********';
            }
        }

        return $params;
    }

    /**
     * Перевіряє, чи є зміна налаштувань критичною.
     */
    private function isCriticalSettingChange(string $settingType, array $oldValues, array $newValues): bool
    {
        $criticalSettings = [
            'trading_enabled',
            'permissions'
        ];

        return in_array($settingType, $criticalSettings);
    }

    /**
     * Отримує кількість помилок для API ключа за останню годину.
     */
    private function getRecentErrorCount(int $apiKeyId): int
    {
        return ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->where('action', 'api_error')
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }
}
