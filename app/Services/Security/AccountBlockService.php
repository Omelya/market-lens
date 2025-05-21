<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountBlockService
{
    /**
     * Блокує обліковий запис користувача.
     */
    public function blockAccount(int $userId, string $reason, int $durationMinutes = 30): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        $expiresAt = now()->addMinutes($durationMinutes);

        $blockData = [
            'blocked' => true,
            'reason' => $reason,
            'blocked_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String()
        ];

        Cache::put("user_block:{$userId}", $blockData, $expiresAt);

        $this->logBlockAction($userId, 'block', $blockData);

        return true;
    }

    /**
     * Розблоковує обліковий запис користувача.
     */
    public function unblockAccount(int $userId): bool
    {
        $blockInfo = $this->getBlockInfo($userId);

        if (!$blockInfo['blocked']) {
            return true;
        }

        Cache::forget("user_block:{$userId}");

        $this->logBlockAction($userId, 'unblock', [
            'previous_block' => $blockInfo,
            'unblocked_at' => now()->toIso8601String()
        ]);

        return true;
    }

    /**
     * Перевіряє, чи заблокований обліковий запис користувача.
     */
    public function isBlocked(int $userId): bool
    {
        $blockInfo = $this->getBlockInfo($userId);
        return $blockInfo['blocked'];
    }

    /**
     * Отримує інформацію про блокування облікового запису.
     */
    public function getBlockInfo(int $userId): array
    {
        $blockData = Cache::get("user_block:{$userId}");

        if (!$blockData) {
            return [
                'blocked' => false,
                'reason' => null,
                'blocked_at' => null,
                'expires_at' => null,
                'remaining_minutes' => 0
            ];
        }

        $expiresAt = Carbon::parse($blockData['expires_at']);
        $remainingMinutes = now()->diffInMinutes($expiresAt, false);

        if ($remainingMinutes <= 0) {
            Cache::forget("user_block:{$userId}");

            return [
                'blocked' => false,
                'reason' => null,
                'blocked_at' => null,
                'expires_at' => null,
                'remaining_minutes' => 0
            ];
        }

        return array_merge($blockData, [
            'remaining_minutes' => $remainingMinutes
        ]);
    }

    /**
     * Обробляє невдалу спробу входу.
     */
    public function handleFailedLogin(int $userId, string $ipAddress): bool
    {
        $failedAttempts = $this->incrementFailedAttempts($userId, $ipAddress);
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        $securitySettings = $user->security_settings ?? [];
        $maxAttempts = $securitySettings['max_failed_login_attempts'] ?? 5;
        $blockDuration = $securitySettings['block_duration_minutes'] ?? 30;

        if ($failedAttempts >= $maxAttempts) {
            $this->blockAccount(
                $userId,
                "Перевищено кількість невдалих спроб входу ({$failedAttempts})",
                $blockDuration
            );

            $this->resetFailedAttempts($userId, $ipAddress);

            return true;
        }

        return false;
    }

    /**
     * Скидає лічильник невдалих спроб входу.
     */
    public function resetFailedAttempts(int $userId, string $ipAddress): void
    {
        $cacheKey = "failed_logins:{$userId}:{$ipAddress}";
        Cache::forget($cacheKey);
    }

    /**
     * Збільшує лічильник невдалих спроб входу.
     */
    private function incrementFailedAttempts(int $userId, string $ipAddress): int
    {
        $cacheKey = "failed_logins:{$userId}:{$ipAddress}";
        $attempts = Cache::get($cacheKey, 0) + 1;

        Cache::put($cacheKey, $attempts, now()->addDay());

        return $attempts;
    }

    /**
     * Логує дії з блокування/розблокування.
     */
    private function logBlockAction(int $userId, string $action, array $details): void
    {
        try {
            UserActivityLog::create([
                'user_id' => $userId,
                'action' => 'account_' . $action,
                'entity_type' => 'user',
                'entity_id' => $userId,
                'details' => $details,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log account block action', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
}
