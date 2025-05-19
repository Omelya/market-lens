<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateIndicatorRequest;
use App\Models\TechnicalIndicator;
use App\Models\TradingPair;
use App\Services\Indicators\TechnicalIndicatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicalIndicatorController extends Controller
{
    protected TechnicalIndicatorService $indicatorService;

    public function __construct(TechnicalIndicatorService $indicatorService)
    {
        $this->indicatorService = $indicatorService;
    }

    public function index(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $query = TechnicalIndicator::query();

        if ($category) {
            $query->where('category', $category);
        }

        $indicators = $query
            ->where('is_active', true)
            ->get();

        return response()->json([
            'data' => $indicators,
            'categories' => TechnicalIndicator::categories(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $indicator = TechnicalIndicator::findOrFail($id);

        return response()
            ->json(['data' => $indicator]);
    }

    public function calculate(CalculateIndicatorRequest $request): JsonResponse
    {
        $indicatorId = $request->input('indicator_id');
        $tradingPairId = $request->input('trading_pair_id');
        $timeframe = $request->input('timeframe');
        $parameters = $request->input('parameters', []);
        $limit = $request->input('limit', 100);

        $result = $this
            ->indicatorService
            ->calculateIndicator(
                $indicatorId,
                $tradingPairId,
                $timeframe,
                $parameters,
                $limit,
            );

        return response()->json($result);
    }

    public function calculateAll(Request $request, int $tradingPairId): JsonResponse
    {
        $timeframe = $request->input('timeframe', '1d');
        $limit = $request->input('limit', 100);

        $result = $this
            ->indicatorService
            ->calculateAllIndicators(
                $tradingPairId,
                $timeframe,
                $limit,
            );

        return response()
            ->json($result);
    }

    public function latest(Request $request, int $indicatorId, int $tradingPairId): JsonResponse
    {
        $timeframe = $request->input('timeframe', '1d');
        $parameters = $request->input('parameters', []);
        $limit = $request->input('limit', 100);

        $result = $this
            ->indicatorService
            ->getLatestIndicatorValues(
                $indicatorId,
                $tradingPairId,
                $timeframe,
                $parameters,
                $limit,
            );

        return response()
            ->json($result);
    }

    public function availablePairs(Request $request): JsonResponse
    {
        $exchange = $request->input('exchange');

        $query = TradingPair
            ::with('exchange')
            ->where('is_active', true);

        if ($exchange) {
            $query->whereHas('exchange', function ($query) use ($exchange) {
                $query->where('slug', $exchange);
            });
        }

        $pairs = $query->get();

        return response()
            ->json(['data' => $pairs]);
    }

    public function availableTimeframes(int $tradingPairId): JsonResponse
    {
        $tradingPair = TradingPair
            ::with('exchange')
            ->findOrFail($tradingPairId);

        $timeframes = [
            'binance' => ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '8h', '12h', '1d', '3d', '1w', '1M'],
            'bybit' => ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '12h', '1d', '1w', '1M'],
            'whitebit' => ['1m', '5m', '15m', '30m', '1h', '4h', '12h', '1d', '1w'],
        ];

        $exchangeSlug = $tradingPair->exchange->slug;
        $availableTimeframes = $timeframes[$exchangeSlug] ?? ['1m', '5m', '15m', '30m', '1h', '4h', '1d'];

        return response()
            ->json(['data' => $availableTimeframes]);
    }
}
