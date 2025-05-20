<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuspiciousActivityMonitoring
{
    /**
     * Порогові значення для виявлення підозрілої активності
     */
    protected const THRESHOLDS = [
        'login_attempts' => 5,
        'profile_updates' => 10,
        'password_changes' => 3,
        'api_keys_added' => 5,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $checks = $this->determineChecks($request);

            if (!empty($checks)) {
                foreach ($checks as $check) {
                    if ($this->isSuspicious($user->id, $check)) {
                        return $this->blockRequest($check);
                    }
                }
            }

            $this->logDeviceIfNew($user->id, $request);
        }

        return $next($request);
    }

    /**
     * Визначити, які перевірки необхідно виконати для даного запиту.
     */
    protected function determineChecks(Request $request): array
    {
        $uri = $request->route()?->uri();
        $method = $request->method();

        return match (true) {
            str_contains($uri, 'auth/login') && $method === 'POST' => ['login_attempts'],
            str_contains($uri, 'user/profile') && $method === 'PUT' => ['profile_updates'],
            str_contains($uri, 'user/change-password') && $method === 'POST' => ['password_changes'],
            str_contains($uri, 'user/exchange-api-keys') && $method === 'POST' => ['api_keys_added'],
            default => [],
        };
    }

    /**
     * Перевірити, чи активність користувача підозріла.
     */
    protected function isSuspicious(int $userId, string $check): bool
    {
        $query = UserActivityLog::where('user_id', $userId);

        $query = match ($check) {
            'login_attempts' => $query->ofAction('login_failed')
                ->where('created_at', '>=', now()->subHour()),
            'profile_updates' => $query->ofAction('profile_update')
                ->where('created_at', '>=', now()->subHour()),
            'password_changes' => $query->ofAction('password_change')
                ->where('created_at', '>=', now()->subDay()),
            'api_keys_added' => $query->ofAction('api_key_added')
                ->where('created_at', '>=', now()->subDay()),
            default => $query,
        };

        $count = $query->count();

        return $count >= self::THRESHOLDS[$check];
    }

    /**
     * Заблокувати запит при виявленні підозрілої активності.
     */
    protected function blockRequest(string $check): Response
    {
        $messages = [
            'login_attempts' => 'Забагато невдалих спроб входу. Спробуйте пізніше.',
            'profile_updates' => 'Забагато оновлень профілю. Спробуйте пізніше.',
            'password_changes' => 'Забагато змін паролю. Спробуйте завтра.',
            'api_keys_added' => 'Забагато додавань API ключів. Спробуйте завтра.',
        ];

        return response()->json([
            'status' => 'error',
            'message' => $messages[$check] ?? 'Підозріла активність. Спробуйте пізніше.',
        ], 429);
    }

    /**
     * Логувати новий пристрій користувача.
     */
    protected function logDeviceIfNew(int $userId, Request $request): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $existingDevice = UserActivityLog
            ::where('user_id', $userId)
            ->where('ip_address', $ip)
            ->where('user_agent', $userAgent)
            ->exists();

        if (!$existingDevice) {
            UserActivityLog::create([
                'user_id' => $userId,
                'action' => 'new_device_detected',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'details' => [
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            // Можна також відправити сповіщення користувачу про новий пристрій
        }
    }
}
