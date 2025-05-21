<?php

namespace App\Http\Controllers;

use App\Models\ExchangeApiKeyLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiKeyStatisticsController extends Controller
{
    public function getApiKeyStatistics(int $id): JsonResponse
    {
        $apiKey = Auth::user()
            ?->exchangeApiKeys()
            ->with('exchange')
            ->findOrFail($id);

        $usage = $this->getApiKeyUsage($apiKey->id);
        $actions = $this->getApiKeyActions($apiKey->id);
        $lastActivity = $this->getLastActivity($apiKey->id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'api_key' => [
                    'id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'exchange' => $apiKey->exchange->name,
                    'is_active' => $apiKey->is_active,
                    'created_at' => $apiKey->created_at,
                    'last_used_at' => $apiKey->last_used_at,
                    'verified_at' => $apiKey->verified_at,
                ],
                'usage' => $usage,
                'actions' => $actions,
                'last_activity' => $lastActivity,
            ]
        ]);
    }

    public function getAllApiKeysStatistics(): JsonResponse
    {
        $apiKeys = Auth
            ::user()
            ?->exchangeApiKeys()
            ->with('exchange')
            ->get();

        $statistics = [];

        foreach ($apiKeys as $apiKey) {
            $usage = $this->getApiKeyUsage($apiKey->id);

            $statistics[] = [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'exchange' => $apiKey->exchange->name,
                'is_active' => $apiKey->is_active,
                'last_used_at' => $apiKey->last_used_at,
                'usage' => [
                    'total_uses' => $usage['total_uses'],
                    'last_24h' => $usage['daily']['count'],
                ],
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    public function getUsageHistory(int $id, Request $request): JsonResponse
    {
        $apiKey = Auth
            ::user()
            ?->exchangeApiKeys()
            ->findOrFail($id);

        $period = $request->input('period', 'daily');
        $limit = (int) $request->input('limit', 30);

        $history = match ($period) {
            'hourly' => $this->getHourlyUsageHistory($apiKey->id, $limit),
            'weekly' => $this->getWeeklyUsageHistory($apiKey->id, $limit),
            'monthly' => $this->getMonthlyUsageHistory($apiKey->id, $limit),
            default => $this->getDailyUsageHistory($apiKey->id, $limit),
        };

        return response()->json([
            'status' => 'success',
            'data' => [
                'api_key_id' => $apiKey->id,
                'period' => $period,
                'history' => $history
            ]
        ]);
    }

    public function getApiKeyUsageLogs(int $id, Request $request): JsonResponse
    {
        $apiKey = Auth
            ::user()
            ?->exchangeApiKeys()
            ->findOrFail($id);

        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);
        $action = $request->input('action');

        $query = ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKey->id)
            ->orderBy('created_at', 'desc');

        if ($action) {
            $query->where('action', $action);
        }

        $logs = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    private function getApiKeyUsage(int $apiKeyId): array
    {
        $now = now();

        $totalUses = ExchangeApiKeyLog::where('exchange_api_key_id', $apiKeyId)->count();

        $dailyUses = ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        $weeklyUses = ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->where('created_at', '>=', $now->copy()->subWeek())
            ->count();

        $monthlyUses = ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->where('created_at', '>=', $now->copy()->subMonth())
            ->count();

        return [
            'total_uses' => $totalUses,
            'daily' => [
                'count' => $dailyUses,
                'percentage' => $totalUses > 0 ? round(($dailyUses / $totalUses) * 100, 2) : 0
            ],
            'weekly' => [
                'count' => $weeklyUses,
                'percentage' => $totalUses > 0 ? round(($weeklyUses / $totalUses) * 100, 2) : 0
            ],
            'monthly' => [
                'count' => $monthlyUses,
                'percentage' => $totalUses > 0 ? round(($monthlyUses / $totalUses) * 100, 2) : 0
            ]
        ];
    }

    private function getApiKeyActions(int $apiKeyId): array
    {
        return ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    private function getLastActivity(int $apiKeyId): array
    {
        return ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKeyId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function getHourlyUsageHistory(int $apiKeyId, int $limit): array
    {
        $now = now();
        $history = [];

        for ($i = 0; $i < $limit; $i++) {
            $startHour = $now->copy()->subHours($i);
            $endHour = $now->copy()->subHours($i - 1);

            $count = ExchangeApiKeyLog
                ::where('exchange_api_key_id', $apiKeyId)
                ->where('created_at', '>=', $startHour)
                ->where('created_at', '<', $endHour)
                ->count();

            $history[] = [
                'period' => $startHour->format('Y-m-d H:00'),
                'count' => $count
            ];
        }

        return array_reverse($history);
    }

    private function getDailyUsageHistory(int $apiKeyId, int $limit): array
    {
        $now = now();
        $history = [];

        for ($i = 0; $i < $limit; $i++) {
            $day = $now->copy()->subDays($i);

            $count = ExchangeApiKeyLog
                ::where('exchange_api_key_id', $apiKeyId)
                ->whereDate('created_at', $day->toDateString())
                ->count();

            $history[] = [
                'period' => $day->format('Y-m-d'),
                'count' => $count
            ];
        }

        return array_reverse($history);
    }

    private function getWeeklyUsageHistory(int $apiKeyId, int $limit): array
    {
        $now = now();
        $history = [];

        for ($i = 0; $i < $limit; $i++) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $count = ExchangeApiKeyLog
                ::where('exchange_api_key_id', $apiKeyId)
                ->where('created_at', '>=', $weekStart)
                ->where('created_at', '<=', $weekEnd)
                ->count();

            $history[] = [
                'period' => $weekStart->format('Y-m-d') . ' to ' . $weekEnd->format('Y-m-d'),
                'count' => $count
            ];
        }

        return array_reverse($history);
    }

    private function getMonthlyUsageHistory(int $apiKeyId, int $limit): array
    {
        $now = now();
        $history = [];

        for ($i = 0; $i < $limit; $i++) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $count = ExchangeApiKeyLog
                ::where('exchange_api_key_id', $apiKeyId)
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<=', $monthEnd)
                ->count();

            $history[] = [
                'period' => $monthStart->format('Y-m'),
                'count' => $count
            ];
        }

        return array_reverse($history);
    }
}
