<?php

namespace App\Interfaces;

interface RiskManagerInterface
{
    /**
     * Розрахувати оптимальний розмір позиції на основі розміру рахунку та ризику.
     *
     * @param float $accountBalance Баланс рахунку.
     * @param float $riskPercentage Відсоток ризику (від балансу).
     * @param float $entryPrice Ціна входу.
     * @param float $stopLossPrice Ціна стоп-лосу.
     * @param float|null $leverage Кредитне плече (null якщо не використовується).
     * @return float Розмір позиції у базовій валюті.
     */
    public function calculatePositionSize(
        float $accountBalance,
        float $riskPercentage,
        float $entryPrice,
        float $stopLossPrice,
        ?float $leverage = null
    ): float;

    /**
     * Розрахувати ціну стоп-лосу на основі відсотка ризику.
     *
     * @param float $entryPrice Ціна входу.
     * @param float $riskPercentage Відсоток ризику.
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @param float|null $leverage Кредитне плече (null якщо не використовується).
     * @return float Ціна стоп-лосу.
     */
    public function calculateStopLossPrice(
        float $entryPrice,
        float $riskPercentage,
        string $direction,
        ?float $leverage = null
    ): float;

    /**
     * Розрахувати ціну тейк-профіту на основі співвідношення ризик/прибуток.
     *
     * @param float $entryPrice Ціна входу.
     * @param float $stopLossPrice Ціна стоп-лосу.
     * @param float $riskRewardRatio Співвідношення ризик/прибуток.
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @return float Ціна тейк-профіту.
     */
    public function calculateTakeProfitPrice(
        float $entryPrice,
        float $stopLossPrice,
        float $riskRewardRatio,
        string $direction
    ): float;

    /**
     * Розрахувати співвідношення ризик/прибуток.
     *
     * @param float $entryPrice Ціна входу.
     * @param float $stopLossPrice Ціна стоп-лосу.
     * @param float $takeProfitPrice Ціна тейк-профіту.
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @return float Співвідношення ризик/прибуток.
     */
    public function calculateRiskRewardRatio(
        float $entryPrice,
        float $stopLossPrice,
        float $takeProfitPrice,
        string $direction
    ): float;

    /**
     * Оновити рівень стоп-лосу для трейлінг-стопу.
     *
     * @param float $currentPrice Поточна ціна.
     * @param float $entryPrice Ціна входу.
     * @param float $currentStopLoss Поточний рівень стоп-лосу.
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @param float $trailingDistance Відстань трейлінг-стопу у відсотках.
     * @param float|null $activationPercentage Відсоток від потенційного прибутку для активації (null для активації відразу).
     * @return float Новий рівень стоп-лосу.
     */
    public function updateTrailingStop(
        float $currentPrice,
        float $entryPrice,
        float $currentStopLoss,
        string $direction,
        float $trailingDistance,
        ?float $activationPercentage = null
    ): float;

    /**
     * Перевірити, чи варто закрити позицію за поточною ціною на основі стоп-лосу або тейк-профіту.
     *
     * @param float $currentPrice Поточна ціна.
     * @param float $stopLossPrice Ціна стоп-лосу.
     * @param float|null $takeProfitPrice Ціна тейк-профіту (null якщо не встановлено).
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @return bool Чи варто закрити позицію.
     */
    public function shouldClosePosition(
        float $currentPrice,
        float $stopLossPrice,
        ?float $takeProfitPrice,
        string $direction
    ): bool;

    /**
     * Розрахувати потенційний прибуток або збиток за поточною ціною.
     *
     * @param float $entryPrice Ціна входу.
     * @param float $currentPrice Поточна ціна.
     * @param float $positionSize Розмір позиції.
     * @param string $direction Напрямок позиції (buy/long або sell/short).
     * @param float|null $leverage Кредитне плече (null якщо не використовується).
     * @return float Потенційний прибуток або збиток.
     */
    public function calculatePotentialPnL(
        float $entryPrice,
        float $currentPrice,
        float $positionSize,
        string $direction,
        ?float $leverage = null
    ): float;
}
