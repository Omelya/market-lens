<?php

namespace App\Services\RiskManagement;

use App\Interfaces\RiskManagerInterface;
use App\Models\TradingPosition;

class RiskManagerService implements RiskManagerInterface
{
    /**
     * Розрахувати оптимальний розмір позиції на основі розміру рахунку та ризику.
     */
    public function calculatePositionSize(
        float $accountBalance,
        float $riskPercentage,
        float $entryPrice,
        float $stopLossPrice,
        ?float $leverage = null,
    ): float {
        $riskPercentage = min(100, max(0, $riskPercentage));

        $riskAmount = $accountBalance * ($riskPercentage / 100);

        if ($entryPrice <= 0 || $stopLossPrice <= 0) {
            return 0;
        }

        $priceDifference = abs($entryPrice - $stopLossPrice);
        $percentDifference = ($priceDifference / $entryPrice) * 100;

        if ($leverage !== null && $leverage > 1) {
            $percentDifference /= $leverage;
        }

        if ($percentDifference <= 0) {
            return 0;
        }

        return $riskAmount / $priceDifference;
    }

    /**
     * Розрахувати ціну стоп-лосу на основі відсотка ризику.
     */
    public function calculateStopLossPrice(
        float $entryPrice,
        float $riskPercentage,
        string $direction,
        ?float $leverage = null,
    ): float {
        $riskPercentage = min(100, max(0, $riskPercentage));

        if ($leverage !== null && $leverage > 1) {
            $riskPercentage *= $leverage;
        }

        $priceDifference = $entryPrice * ($riskPercentage / 100);

        if ($this->isLongPosition($direction)) {
            return $entryPrice - $priceDifference;
        }

        return $entryPrice + $priceDifference;
    }

    /**
     * Розрахувати ціну тейк-профіту на основі співвідношення ризик/прибуток.
     */
    public function calculateTakeProfitPrice(
        float $entryPrice,
        float $stopLossPrice,
        float $riskRewardRatio,
        string $direction,
    ): float {
        $riskRewardRatio = max(0.1, $riskRewardRatio);

        $riskDistance = abs($entryPrice - $stopLossPrice);

        $profitDistance = $riskDistance * $riskRewardRatio;

        if ($this->isLongPosition($direction)) {
            return $entryPrice + $profitDistance;
        }

        return $entryPrice - $profitDistance;
    }

    /**
     * Розрахувати співвідношення ризик/прибуток.
     */
    public function calculateRiskRewardRatio(
        float $entryPrice,
        float $stopLossPrice,
        float $takeProfitPrice,
        string $direction,
    ): float {
        $riskDistance = abs($entryPrice - $stopLossPrice);
        $profitDistance = abs($entryPrice - $takeProfitPrice);

        if ($riskDistance <= 0) {
            return 0;
        }

        $isValidSetup = $this->isLongPosition($direction)
            ? ($stopLossPrice < $entryPrice && $takeProfitPrice > $entryPrice)
            : ($stopLossPrice > $entryPrice && $takeProfitPrice < $entryPrice);

        if (!$isValidSetup) {
            return 0;
        }

        return $profitDistance / $riskDistance;
    }

    /**
     * Оновити рівень стоп-лосу для трейлінг-стопу.
     */
    public function updateTrailingStop(
        float $currentPrice,
        float $entryPrice,
        float $currentStopLoss,
        string $direction,
        float $trailingDistance,
        ?float $activationPercentage = null,
    ): float {
        $isProfit = $this->isLongPosition($direction)
            ? $currentPrice > $entryPrice
            : $currentPrice < $entryPrice;

        if (!$isProfit) {
            return $currentStopLoss;
        }

        $profitPercentage = $this->isLongPosition($direction)
            ? (($currentPrice - $entryPrice) / $entryPrice) * 100
            : (($entryPrice - $currentPrice) / $entryPrice) * 100;

        if ($activationPercentage !== null && $profitPercentage < $activationPercentage) {
            return $currentStopLoss;
        }

        $trailingStopPrice = $this->isLongPosition($direction)
            ? $currentPrice * (1 - $trailingDistance / 100)
            : $currentPrice * (1 + $trailingDistance / 100);

        if ($this->isLongPosition($direction)) {
            return max($currentStopLoss, $trailingStopPrice, $entryPrice);
        }

        return min($currentStopLoss, $trailingStopPrice, $entryPrice);
    }

    /**
     * Перевірити, чи варто закрити позицію за поточною ціною на основі стоп-лосу або тейк-профіту.
     */
    public function shouldClosePosition(
        float $currentPrice,
        float $stopLossPrice,
        ?float $takeProfitPrice,
        string $direction,
    ): bool {
        $isStopLossTriggered = $this->isLongPosition($direction)
            ? $currentPrice <= $stopLossPrice
            : $currentPrice >= $stopLossPrice;

        $isTakeProfitTriggered = $takeProfitPrice !== null && (
            $this->isLongPosition($direction)
                ? $currentPrice >= $takeProfitPrice
                : $currentPrice <= $takeProfitPrice
            );

        return $isStopLossTriggered || $isTakeProfitTriggered;
    }

    /**
     * Розрахувати потенційний прибуток або збиток за поточною ціною.
     */
    public function calculatePotentialPnL(
        float $entryPrice,
        float $currentPrice,
        float $positionSize,
        string $direction,
        ?float $leverage = null,
    ): float {
        $priceDifference = $this->isLongPosition($direction)
            ? $currentPrice - $entryPrice
            : $entryPrice - $currentPrice;

        $pnl = $priceDifference * $positionSize;

        if ($leverage !== null && $leverage > 1) {
            $pnl *= $leverage;
        }

        return $pnl;
    }

    protected function isLongPosition(string $direction): bool
    {
        $direction = strtolower($direction);

        return $direction === 'buy' || $direction === 'long' ||
            $direction === TradingPosition::DIRECTION_LONG;
    }
}
