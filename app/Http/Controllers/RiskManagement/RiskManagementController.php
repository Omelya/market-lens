<?php

namespace App\Http\Controllers\RiskManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskManagement\CalculatePositionSizeRequest;
use App\Http\Requests\RiskManagement\RiskStrategyRequest;
use App\Http\Requests\RiskManagement\UpdateTrailingStopRequest;
use App\Models\RiskManagementStrategy;
use App\Models\TradingPosition;
use App\Services\RiskManagement\RiskManagerService;
use App\Services\RiskManagement\TrailingStopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RiskManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $strategies = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $strategies,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $strategy = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $strategy,
        ]);
    }

    public function store(RiskStrategyRequest $request): JsonResponse
    {
        $strategy = new RiskManagementStrategy($request->validated());
        $strategy->user_id = Auth::id();
        $strategy->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Стратегію управління ризиками створено',
            'data' => $strategy,
        ], 201);
    }

    public function update(RiskStrategyRequest $request, int $id): JsonResponse
    {
        $strategy = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $strategy->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Стратегію управління ризиками оновлено',
            'data' => $strategy,
        ]);
    }

    public function destroy(int $id): void
    {
        $strategy = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $strategy->delete();

        response()->noContent();
    }

    public function activate(int $id): JsonResponse
    {
        $strategy = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $strategy->activate();

        return response()->json([
            'status' => 'success',
            'message' => 'Стратегію управління ризиками активовано',
            'data' => $strategy,
        ]);
    }

    public function deactivate(int $id): JsonResponse
    {
        $strategy = RiskManagementStrategy
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $strategy->deactivate();

        return response()->json([
            'status' => 'success',
            'message' => 'Стратегію управління ризиками деактивовано',
            'data' => $strategy,
        ]);
    }

    public function calculatePositionSize(
        CalculatePositionSizeRequest $request,
        RiskManagerService $riskManager,
    ): JsonResponse {
        $positionSize = $riskManager->calculatePositionSize(
            $request->account_balance,
            $request->risk_percentage,
            $request->entry_price,
            $request->stop_loss_price,
            $request->leverage
        );

        $takeProfitPrice = null;

        if ($request->has('risk_reward_ratio')) {
            $takeProfitPrice = $riskManager->calculateTakeProfitPrice(
                $request->entry_price,
                $request->stop_loss_price,
                $request->risk_reward_ratio,
                $request->direction
            );
        }

        $riskAmount = $request->account_balance * ($request->risk_percentage / 100);

        return response()->json([
            'status' => 'success',
            'data' => [
                'position_size' => $positionSize,
                'take_profit_price' => $takeProfitPrice,
                'risk_amount' => $riskAmount,
                'potential_loss' => $riskManager->calculatePotentialPnL(
                    $request->entry_price,
                    $request->stop_loss_price,
                    $positionSize,
                    $request->direction,
                    $request->leverage
                ),
                'potential_profit' => $takeProfitPrice
                    ? $riskManager
                        ->calculatePotentialPnL(
                            $request->entry_price,
                            $takeProfitPrice,
                            $positionSize,
                            $request->direction,
                            $request->leverage,
                        )
                    : null,
            ],
        ]);
    }

    public function activateTrailingStop(
        UpdateTrailingStopRequest $request,
        TrailingStopService $trailingStopService,
        int $id,
    ): JsonResponse {
        $position = TradingPosition
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $result = $trailingStopService->activateTrailingStop(
            $position,
            $request->trailing_distance,
            $request->activation_percentage
        );

        return response()->json([
            'status' => $result['status'] === 'success' ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    public function deactivateTrailingStop(
        TrailingStopService $trailingStopService,
        int $id,
    ): JsonResponse {
        $position = TradingPosition
            ::where('user_id', Auth::id())
            ->findOrFail($id);

        $result = $trailingStopService->deactivateTrailingStop($position);

        return response()->json([
            'status' => $result['status'] === 'success' ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    public function updateAllTrailingStops(TrailingStopService $trailingStopService): JsonResponse
    {
        $result = $trailingStopService->updateAllTrailingStops();

        return response()->json([
            'status' => 'success',
            'message' => "Оновлено {$result['success']} трейлінг-стопів, помилок: {$result['error']}",
            'data' => $result,
        ]);
    }

    public function calculatePotentialPnL(
        Request $request,
        RiskManagerService $riskManager,
    ): JsonResponse {
        $request->validate([
            'entry_price' => 'required|numeric|gt:0',
            'current_price' => 'required|numeric|gt:0',
            'position_size' => 'required|numeric|gt:0',
            'direction' => 'required|string|in:buy,sell,long,short',
            'leverage' => 'nullable|numeric|min:1',
        ]);

        $pnl = $riskManager->calculatePotentialPnL(
            $request->entry_price,
            $request->current_price,
            $request->position_size,
            $request->direction,
            $request->leverage,
        );

        $percentagePnL = (abs($pnl) / ($request->entry_price * $request->position_size)) * 100;

        if ($pnl < 0) {
            $percentagePnL = -$percentagePnL;
        }

        if ($request->leverage) {
            $percentagePnL *= $request->leverage;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'pnl' => $pnl,
                'percentage_pnl' => $percentagePnL,
                'is_profit' => $pnl >= 0,
            ],
        ]);
    }
}
