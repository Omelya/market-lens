<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimiting
{
    /**
     * Обробити вхідний запит.
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $key = $this->resolveRequestSignature($request);

        $limits = $this->getLimitsForRoute($request);

        $maxAttempts = $limits['maxAttempts'];
        $decaySeconds = $limits['decaySeconds'];

        $response = $next($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttempts);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $this->addRateLimiterHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Отримати кількість лімітів для маршруту.
     */
    protected function getLimitsForRoute(Request $request): array
    {
        $uri = $request->route()?->uri();

        return match (true) {
            str_contains($uri, 'auth/login') || str_contains($uri, 'auth/register') => [
                'maxAttempts' => 5,
                'decaySeconds' => 60
            ],

            str_contains($uri, 'user/exchange-api-keys') => [
                'maxAttempts' => 20,
                'decaySeconds' => 60
            ],

            str_contains($uri, 'user/profile') ||
            str_contains($uri, 'user/change-password') => [
                'maxAttempts' => 10,
                'decaySeconds' => 60
            ],

            default => [
                'maxAttempts' => 60,
                'decaySeconds' => 60
            ],
        };
    }

    /**
     * Створити відповідь на перевищення кількості спроб.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'status' => 'error',
            'message' => 'Too many attempts',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => $retryAfter,
        ]);
    }

    /**
     * Додати заголовки лімітера до відповіді.
     */
    protected function addRateLimiterHeaders(
        Response $response,
        int $maxAttempts,
        int $remainingAttempts,
    ): Response {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }

    /**
     * Розрахувати кількість залишених спроб.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return RateLimiter::retriesLeft($key, $maxAttempts);
    }

    /**
     * Вирішити сигнатуру запиту для лімітера.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return sha1($user->id . '|' . $request->ip() . '|' . $request->route()?->uri());
        }

        return sha1($request->ip() . '|' . $request->route()?->uri());
    }
}
