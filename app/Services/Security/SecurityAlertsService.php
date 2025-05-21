<?php

namespace App\Services\Security;

use App\Models\ExchangeApiKey;
use App\Models\ExchangeApiKeyLog;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SecurityAlert;

class SecurityAlertsService
{
    /**
     * Створює сповіщення про підозрілу активність.
     */
    public function createSecurityAlert(int $userId, string $alertType, array $details = []): bool
    {
        try {
            $user = User::findOrFail($userId);

            $shouldSendAlert = $this->shouldSendAlert($user, $alertType);

            if (!$shouldSendAlert) {
                return false;
            }

            $this->logSecurityAlert($userId, $alertType, $details);

            $this->sendEmailAlert($user, $alertType, $details);

            return $this->handleSpecificAlertType($user, $alertType, $details);

        } catch (\Exception $e) {
            Log::error('Failed to create security alert', [
                'user_id' => $userId,
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Перевіряє, чи потрібно відправляти сповіщення.
     */
    private function shouldSendAlert(User $user, string $alertType): bool
    {
        $securitySettings = $user->security_settings ?? [];
        $notificationPreferences = $user->notification_preferences ?? [];

        if (isset($notificationPreferences['security_alerts']) && $notificationPreferences['security_alerts'] === false) {
            return false;
        }

        return match ($alertType) {
            'new_login' => $securitySettings['login_notifications'] ?? true,
            'api_key_usage' => $notificationPreferences['api_key_usage_notifications'] ?? false,
            'suspicious_activity', 'account_blocked', 'password_changed', 'email_changed' => true,
            default => true,
        };
    }

    /**
     * Записує сповіщення в журнал безпеки.
     */
    private function logSecurityAlert(int $userId, string $alertType, array $details): void
    {
        UserActivityLog::create([
            'user_id' => $userId,
            'action' => 'security_alert_' . $alertType,
            'entity_type' => 'user',
            'entity_id' => $userId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Відправляє сповіщення електронною поштою.
     */
    private function sendEmailAlert(User $user, string $alertType, array $details): void
    {
        $alertInfo = $this->getAlertInfo($alertType, $details);

        try {
            Mail::to($user->email)
                ->send(new SecurityAlert($user, $alertInfo, $details));
        } catch (\Exception $e) {
            Log::error('Failed to send security alert email', [
                'user_id' => $user->id,
                'alert_type' => $alertType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Обробляє специфічні дії для різних типів сповіщень.
     */
    private function handleSpecificAlertType(User $user, string $alertType, array $details): bool
    {
        switch ($alertType) {
            case 'suspicious_activity':
                $securitySettings = $user->security_settings ?? [];

                if (isset($securitySettings['block_suspicious_ip']) && $securitySettings['block_suspicious_ip']) {
                    $blockService = app(AccountBlockService::class);
                    $blockDuration = $securitySettings['block_duration_minutes'] ?? 30;

                    $blockService->blockAccount(
                        $user->id,
                        'Автоматичне блокування через підозрілу активність',
                        $blockDuration
                    );
                }

                break;

            case 'api_key_usage':
                if (isset($details['api_key_id'], $details['high_risk']) && $details['high_risk']) {
                    $this->deactivateApiKeyTemporarily($details['api_key_id']);
                }

                break;
        }

        return true;
    }

    /**
     * Отримує інформацію про сповіщення.
     */
    private function getAlertInfo(string $alertType, array $details): array
    {
        $alertsInfo = [
            'new_login' => [
                'subject' => 'Новий вхід до вашого облікового запису',
                'message' => 'Виявлено новий вхід до вашого облікового запису з нового пристрою або локації.',
                'critical' => false
            ],
            'suspicious_activity' => [
                'subject' => 'Підозріла активність у вашому обліковому записі',
                'message' => 'Виявлено підозрілу активність у вашому обліковому записі. Перевірте деталі та вживіть необхідні заходи.',
                'critical' => true
            ],
            'account_blocked' => [
                'subject' => 'Ваш обліковий запис заблоковано',
                'message' => 'Ваш обліковий запис було заблоковано через підозрілу активність або перевищення кількості невдалих спроб входу.',
                'critical' => true
            ],
            'password_changed' => [
                'subject' => 'Ваш пароль було змінено',
                'message' => 'Ваш пароль було успішно змінено. Якщо це були не ви, негайно зверніться до служби підтримки.',
                'critical' => true
            ],
            'email_changed' => [
                'subject' => 'Ваш email було змінено',
                'message' => 'Вашу електронну адресу було змінено. Якщо це були не ви, негайно зверніться до служби підтримки.',
                'critical' => true
            ],
            'api_key_usage' => [
                'subject' => 'Використання API ключа',
                'message' => 'Виявлено використання вашого API ключа.',
                'critical' => false
            ]
        ];

        return $alertsInfo[$alertType] ?? [
            'subject' => 'Сповіщення безпеки',
            'message' => 'Виявлено потенційно важливу подію, пов\'язану з безпекою вашого облікового запису.',
            'critical' => false
        ];
    }

    /**
     * Тимчасово деактивує API ключ.
     */
    private function deactivateApiKeyTemporarily(int $apiKeyId): void
    {
        try {
            $apiKey = ExchangeApiKey::find($apiKeyId);

            if ($apiKey) {
                $apiKey->update([
                    'is_active' => false,
                    'permissions_data' => array_merge($apiKey->permissions_data ?? [], [
                        'temporarily_deactivated' => true,
                        'deactivated_at' => now()->toIso8601String(),
                        'reason' => 'Автоматична деактивація через підозрілу активність'
                    ])
                ]);

                ExchangeApiKeyLog::create([
                    'exchange_api_key_id' => $apiKeyId,
                    'action' => 'temporarily_deactivated',
                    'details' => [
                        'reason' => 'Автоматична деактивація через підозрілу активність',
                        'deactivated_at' => now()->toIso8601String()
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to temporarily deactivate API key', [
                'api_key_id' => $apiKeyId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
