<?php

namespace App\Http\Controllers\RiskManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskManagement\OpenPositionRequest;
use App\Http\Requests\RiskManagement\UpdatePositionRequest;
use App\Models\TradingPosition;
use App\Services\RiskManagement\PositionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $tradingPairId = $request->input('trading_pair_id');
        $apiKeyId = $request->input('api_key_id');
        $limit = $request->input('limit', 50);

        $query = TradingPosition::where('user_id', Auth::id())
            ->with(['tradingPair.exchange', 'apiKey', 'riskStrategy']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($tradingPairId) {
            $query->where('trading_pair_id', $tradingPairId);
        }

        if ($apiKeyId) {
            $query->where('api_key_id', $apiKeyId);
        }

        $positions = $query->orderBy('opened_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $positions,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $position = TradingPosition::where('user_id', Auth::id())
            ->with(['tradingPair.exchange', 'apiKey', 'riskStrategy'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $position,
        ]);
    }

    public function store(OpenPositionRequest $request, PositionManagerService $positionManager): JsonResponse
    {
        $user = Auth::user();

        $result = $positionManager->openPosition(
            $user,
            $request->api_key_id,
            $request->trading_pair_id,
            $request->direction,
            $request->entry_price,
            $request->stop_loss_price,
            $request->take_profit_price,
            $request->risk_strategy_id,
            $request->position_size,
            $request->leverage,
            [
                'order_type' => $request->input('order_type', 'limit'),
                'position_type' => $request->input('position_type', TradingPosition::TYPE_MANUAL),
                'create_stop_loss' => $request->input('create_stop_loss', true),
                'create_take_profit' => $request->input('create_take_profit', true),
                'order_params' => $request->input('order_params', []),
                'stop_loss_params' => $request->input('stop_loss_params', []),
                'take_profit_params' => $request->input('take_profit_params', []),
            ]
        );

        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'message' => 'Позицію відкрито успішно',
                'data' => $result,
            ], 201);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => $result,
        ], 400);
    }

    public function close(int $id, Request $request, PositionManagerService $positionManager): JsonResponse
    {
        $position = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_OPEN)
            ->findOrFail($id);

        $exitPrice = $request->input('exit_price'); // Null для використання поточної ринкової ціни
        $orderType = $request->input('order_type', 'market');

        $result = $positionManager->closePosition(
            $position,
            $exitPrice,
            [
                'order_type' => $orderType,
                'order_params' => $request->input('order_params', []),
            ]
        );

        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'message' => 'Позицію закрито успішно',
                'data' => $result,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => $result,
        ], 400);
    }

    public function updateStopLoss(int $id, UpdatePositionRequest $request, PositionManagerService $positionManager): JsonResponse
    {
        $position = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_OPEN)
            ->findOrFail($id);

        $result = $positionManager->updateStopLoss(
            $position,
            $request->stop_loss_price,
            [
                'stop_loss_params' => $request->input('stop_loss_params', []),
            ]
        );

        if ($result['status'] === 'success' || $result['status'] === 'info') {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => $result,
        ], 400);
    }

    public function updateTakeProfit(int $id, UpdatePositionRequest $request, PositionManagerService $positionManager): JsonResponse
    {
        $position = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_OPEN)
            ->findOrFail($id);

        $result = $positionManager->updateTakeProfit(
            $position,
            $request->take_profit_price,
            [
                'take_profit_params' => $request->input('take_profit_params', []),
            ]
        );

        if ($result['status'] === 'success' || $result['status'] === 'info') {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => $result,
        ], 400);
    }

    public function getStatistics(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        $totalPositions = TradingPosition::where('user_id', Auth::id())
            ->count();

        $openPositions = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_OPEN)
            ->count();

        $closedPositions = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->count();

        $profitPositions = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->where('result', TradingPosition::RESULT_PROFIT)
            ->count();

        $lossPositions = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->where('result', TradingPosition::RESULT_LOSS)
            ->count();

        $totalPnl = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->sum('realized_pnl');

        $recentPnl = TradingPosition::where('user_id', Auth::id())
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->where('closed_at', '>=', $startDate)
            ->sum('realized_pnl');

        $winRate = $closedPositions > 0
            ? ($profitPositions / $closedPositions) * 100
            : 0;

        $positionsByPair = TradingPosition::where('user_id', Auth::id())
            ->selectRaw('trading_pair_id, COUNT(*) as count')
            ->with('tradingPair')
            ->groupBy('trading_pair_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'trading_pair_id' => $item->trading_pair_id,
                    'symbol' => $item->tradingPair->symbol,
                    'exchange' => $item->tradingPair->exchange->name,
                    'count' => $item->count,
                ];
            });

        $positionsByDirection = TradingPosition::where('user_id', Auth::id())
            ->selectRaw('direction, COUNT(*) as count')
            ->groupBy('direction')
            ->get()
            ->pluck('count', 'direction')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_positions' => $totalPositions,
                'open_positions' => $openPositions,
                'closed_positions' => $closedPositions,
                'profit_positions' => $profitPositions,
                'loss_positions' => $lossPositions,
                'win_rate' => $winRate,
                'total_pnl' => $totalPnl,
                'recent_pnl' => $recentPnl,
                'positions_by_pair' => $positionsByPair,
                'positions_by_direction' => $positionsByDirection,
            ],
        ]);
    }
}
