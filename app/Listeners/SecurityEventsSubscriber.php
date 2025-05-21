<?php

namespace App\Listeners;

use App\Models\UserSecurityLog;
use App\Services\Security\AccountBlockService;
use App\Services\Security\DeviceDetector;
use App\Services\Security\SecurityAlertsService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class SecurityEventsSubscriber
{
    protected DeviceDetector $deviceDetector;
    protected AccountBlockService $blockService;
    protected SecurityAlertsService $alertsService;

    public function __construct(
        DeviceDetector $deviceDetector,
        AccountBlockService $blockService,
        SecurityAlertsService $alertsService
    ) {
        $this->deviceDetector = $deviceDetector;
        $this->blockService = $blockService;
        $this->alertsService = $alertsService;
    }

    /**
     * Обробляє подію успішного входу.
     */
    public function handleLogin(Login $event): void
    {
        try {
            $user = $event->user;

            if (!$user) {
                return;
            }

            $request = request();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $deviceId = $this->deviceDetector->generateDeviceId($userAgent, $ipAddress);
            $deviceInfo = $this->deviceDetector->getDeviceInfo($userAgent);

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $ipAddress
            ]);

            $this->blockService->resetFailedAttempts($user->id, $ipAddress);

            UserSecurityLog::create([
                'user_id' => $user->id,
                'event_type' => 'login',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_id' => $deviceId,
                'session_id' => $request->session()->getId(),
                'details' => [
                    'device_info' => $deviceInfo,
                    'login_time' => now()->toIso8601String()
                ],
                'is_suspicious' => false
            ]);

            $isNewDevice = $this->deviceDetector->isNewDevice($user->id, $deviceId);

            if ($isNewDevice) {
                UserSecurityLog::create([
                    'user_id' => $user->id,
                    'event_type' => 'new_device_login',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'device_id' => $deviceId,
                    'session_id' => $request->session()->getId(),
                    'details' => [
                        'device_info' => $deviceInfo,
                        'login_time' => now()->toIso8601String()
                    ],
                    'is_suspicious' => true
                ]);

                $this->alertsService->createSecurityAlert($user->id, 'new_login', [
                    'device_info' => $deviceInfo,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'login_time' => now()->toIso8601String()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling login event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Обробляє подію невдалого входу.
     */
    public function handleFailedLogin(Failed $event): void
    {
        try {
            $user = $event->user;

            if (!$user) {
                return;
            }

            $request = request();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $deviceId = $this->deviceDetector->generateDeviceId($userAgent, $ipAddress);
            $deviceInfo = $this->deviceDetector->getDeviceInfo($userAgent);

            UserSecurityLog::create([
                'user_id' => $user->id,
                'event_type' => 'login_failed',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_id' => $deviceId,
                'session_id' => $request->session()->getId(),
                'details' => [
                    'device_info' => $deviceInfo,
                    'login_time' => now()->toIso8601String(),
                    'credentials' => [
                        'email' => $event->credentials['email'] ?? null
                    ]
                ],
                'is_suspicious' => true
            ]);

            $isBlocked = $this->blockService->handleFailedLogin($user->id, $ipAddress);

            if ($isBlocked) {
                $this->alertsService->createSecurityAlert($user->id, 'account_blocked', [
                    'reason' => 'Перевищено кількість невдалих спроб входу',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'block_time' => now()->toIso8601String()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling failed login event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Обробляє подію виходу.
     */
    public function handleLogout(Logout $event): void
    {
        try {
            $user = $event->user;

            if (!$user) {
                return;
            }

            $request = request();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $sessionId = $request->session()->getId();

            UserSecurityLog::create([
                'user_id' => $user->id,
                'event_type' => 'logout',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'details' => [
                    'logout_time' => now()->toIso8601String()
                ],
                'is_suspicious' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling logout event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Обробляє подію зміни паролю.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        try {
            $user = $event->user;

            if (!$user) {
                return;
            }

            $request = request();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            UserSecurityLog::create([
                'user_id' => $user->id,
                'event_type' => 'password_reset',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'details' => [
                    'reset_time' => now()->toIso8601String()
                ],
                'is_suspicious' => false
            ]);

            $this->alertsService->createSecurityAlert($user->id, 'password_changed', [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'reset_time' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling password reset event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Реєструє слухачів для подій системи безпеки.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailedLogin',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }
}
