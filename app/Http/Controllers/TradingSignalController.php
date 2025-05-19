<?php

namespace App\Http\Controllers;

use App\Models\TradingPair;
use App\Models\TradingSignal;
use App\Services\Analysis\PatternAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradingSignalController extends Controller
{
    protected PatternAnalysisService $analysisService;

    public function __construct(PatternAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    public function index(Request $request): JsonResponse
    {
        $direction = $request->input('direction');
        $timeframe = $request->input('timeframe');
        $exchange = $request->input('exchange');
        $limit = (int) $request->input('limit', 50);

        $query = TradingSignal
            ::with('tradingPair.exchange')
            ->where('is_active', true)
            ->orderBy('timestamp', 'desc');

        if ($direction) {
            $query->where('direction', $direction);
        }

        if ($timeframe) {
            $query->where('timeframe', $timeframe);
        }

        if ($exchange) {
            $query->whereHas('tradingPair.exchange', function ($q) use ($exchange) {
                $q->where('slug', $exchange);
            });
        }

        $signals = $query->limit($limit)->get();

        return response()->json(['data' => $signals]);
    }

    public function show(int $id): JsonResponse
    {
        $signal = TradingSignal::with('tradingPair.exchange')->findOrFail($id);

        return response()
            ->json(['data' => $signal]);
    }

    public function getSignalsForPair(Request $request, int $tradingPairId): JsonResponse
    {
        $timeframe = $request->input('timeframe');
        $limit = (int) $request->input('limit', 20);

        $query = TradingSignal::where('trading_pair_id', $tradingPairId)
            ->where('is_active', true)
            ->orderBy('timestamp', 'desc');

        if ($timeframe) {
            $query->where('timeframe', $timeframe);
        }

        $signals = $query->limit($limit)->get();

        return response()->json([
            'trading_pair_id' => $tradingPairId,
            'timeframe' => $timeframe,
            'data' => $signals,
        ]);
    }

    public function analyzePatterns(Request $request, int $tradingPairId): JsonResponse
    {
        $timeframe = $request->input('timeframe', '1d');
        $limit = (int) $request->input('limit', 100);
        $generateSignals = $request->boolean('generate_signals', true);

        $result = $this
            ->analysisService
            ->analyzePatterns(
                $tradingPairId,
                $timeframe,
                $generateSignals,
                $limit,
            );

        return response()
            ->json($result);
    }

    public function deactivate(int $id): JsonResponse
    {
        $signal = TradingSignal::findOrFail($id);
        $signal->is_active = false;
        $signal->save();

        return response()->json([
            'message' => 'Сигнал деактивовано.',
            'data' => $signal,
        ]);
    }

    public function availablePairs(Request $request): JsonResponse
    {
        $exchange = $request->input('exchange');

        $query = TradingPair::with('exchange')
            ->where('is_active', true);

        if ($exchange) {
            $query->whereHas('exchange', function ($q) use ($exchange) {
                $q->where('slug', $exchange);
            });
        }

        $pairs = $query->get();

        return response()
            ->json(['data' => $pairs]);
    }

    public function getStatistics(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        $totalActive = TradingSignal::where('is_active', true)->count();

        $directionStats = TradingSignal::where('is_active', true)
            ->select('direction', DB::raw('count(*) as count'))
            ->groupBy('direction')
            ->get()
            ->pluck('count', 'direction')
            ->toArray();

        $strengthStats = TradingSignal::where('is_active', true)
            ->select('strength', DB::raw('count(*) as count'))
            ->groupBy('strength')
            ->get()
            ->pluck('count', 'strength')
            ->toArray();

        $timeframeStats = TradingSignal::where('is_active', true)
            ->select('timeframe', DB::raw('count(*) as count'))
            ->groupBy('timeframe')
            ->get()
            ->pluck('count', 'timeframe')
            ->toArray();

        $recentSignals = TradingSignal::where('timestamp', '>=', now()->subDays($days))
            ->count();

        $topPairs = TradingSignal::where('is_active', true)
            ->select('trading_pair_id', DB::raw('count(*) as count'))
            ->with('tradingPair')
            ->groupBy('trading_pair_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'trading_pair_id' => $item->trading_pair_id,
                    'symbol' => $item->tradingPair->symbol,
                    'exchange' => $item->tradingPair->exchange->name,
                    'count' => $item->count,
                ];
            });

        return response()->json([
            'status' => 'success',
            'total_active' => $totalActive,
            'direction_stats' => $directionStats,
            'strength_stats' => $strengthStats,
            'timeframe_stats' => $timeframeStats,
            'recent_signals' => $recentSignals,
            'top_pairs' => $topPairs,
        ]);
    }

    public function getTopRiskRewardSignals(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);

        $signals = TradingSignal::with('tradingPair.exchange')
            ->where('is_active', true)
            ->orderByDesc('risk_reward_ratio')
            ->limit($limit)
            ->get();

        return response()
            ->json(['data' => $signals]);
    }

    public function getTopProbabilitySignals(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 10);

        $signals = TradingSignal
            ::with('tradingPair.exchange')
            ->where('is_active', true)
            ->orderByDesc('success_probability')
            ->limit($limit)
            ->get();

        return response()
            ->json(['data' => $signals]);
    }

    public function updateAllSignals(Request $request): JsonResponse
    {
        $timeframe = $request->input('timeframe', '1d');
        $exchange = $request->input('exchange');
        $limit = (int) $request->input('limit', 100);

        $query = TradingPair
            ::with('exchange')
            ->where('is_active', true);

        if ($exchange) {
            $query->whereHas('exchange', function ($q) use ($exchange) {
                $q->where('slug', $exchange);
            });
        }

        $tradingPairs = $query->get();

        $results = [];
        $totalPatterns = 0;
        $totalSignals = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($tradingPairs as $tradingPair) {
            try {
                $result = $this->analysisService->analyzePatterns(
                    $tradingPair->id,
                    $timeframe,
                    true,
                    $limit
                );

                if ($result['status'] === 'success') {
                    $successCount++;
                    $totalPatterns += $result['patterns_count'];
                    $totalSignals += $result['signals_count'];

                    $results[] = [
                        'trading_pair_id' => $tradingPair->id,
                        'symbol' => $tradingPair->symbol,
                        'exchange' => $tradingPair->exchange->name,
                        'patterns_count' => $result['patterns_count'],
                        'signals_count' => $result['signals_count'],
                        'status' => 'success',
                    ];
                } else {
                    $errorCount++;

                    $results[] = [
                        'trading_pair_id' => $tradingPair->id,
                        'symbol' => $tradingPair->symbol,
                        'exchange' => $tradingPair->exchange->name,
                        'error' => $result['message'],
                        'status' => 'error',
                    ];
                }
            } catch (\Exception $e) {
                $errorCount++;

                $results[] = [
                    'trading_pair_id' => $tradingPair->id,
                    'symbol' => $tradingPair->symbol,
                    'exchange' => $tradingPair->exchange->name,
                    'error' => $e->getMessage(),
                    'status' => 'error',
                ];
            }
        }

        return response()->json([
            'message' => "Оновлено сигнали для {$successCount} пар. Помилок: {$errorCount}.",
            'total_pairs' => count($tradingPairs),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'total_patterns' => $totalPatterns,
            'total_signals' => $totalSignals,
            'results' => $results,
        ]);
    }
}
