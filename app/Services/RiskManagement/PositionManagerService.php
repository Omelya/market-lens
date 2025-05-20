<?php

namespace App\Services\RiskManagement;

use App\Interfaces\ExchangeInterface;
use App\Interfaces\RiskManagerInterface;
use App\Models\RiskManagementStrategy;
use App\Models\TradingPair;
use App\Models\TradingPosition;
use App\Models\User;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

class PositionManagerService
{
    protected RiskManagerInterface $riskManager;

    public function __construct(RiskManagerInterface $riskManager)
    {
        $this->riskManager = $riskManager;
    }

    /**
     * Відкрити нову позицію з урахуванням ризик-менеджменту.
     *
     * @param User $user Користувач.
     * @param int $apiKeyId ID API-ключа.
     * @param int $tradingPairId ID торгової пари.
     * @param string $direction Напрямок (buy/long або sell/short).
     * @param float $entryPrice Ціна входу.
     * @param float $stopLossPrice Ціна стоп-лосу.
     * @param float|null $takeProfitPrice Ціна тейк-профіту (null для автоматичного розрахунку).
     * @param int|null $riskStrategyId ID стратегії ризик-менеджменту (null для використання значень за замовчуванням).
     * @param float|null $positionSize Розмір позиції (null для автоматичного розрахунку).
     * @param float|null $leverage Кредитне плече (null якщо не використовується).
     * @param array $options Додаткові параметри.
     * @return array Результат відкриття позиції.
     */
    public function openPosition(
        User $user,
        int $apiKeyId,
        int $tradingPairId,
        string $direction,
        float $entryPrice,
        float $stopLossPrice,
        ?float $takeProfitPrice = null,
        ?int $riskStrategyId = null,
        ?float $positionSize = null,
        ?float $leverage = null,
        array $options = []
    ): array {
        try {
            $tradingPair = TradingPair::with('exchange')->findOrFail($tradingPairId);

            $exchange = ExchangeFactory::createWithApiKey($apiKeyId);

            $balance = $exchange->getBalance();
            $accountBalance = $balance['total'][$tradingPair->quote_currency] ?? 0;

            $riskStrategy = $riskStrategyId
                ? RiskManagementStrategy::findOrFail($riskStrategyId)
                : null;

            if ($riskStrategy) {
                if (!$riskStrategy->isActive()) {
                    return [
                        'status' => 'error',
                        'message' => 'Стратегія ризик-менеджменту неактивна',
                    ];
                }

                if ($riskStrategy->isMaxConcurrentTradesExceeded()) {
                    return [
                        'status' => 'error',
                        'message' => 'Перевищено максимальну кількість одночасних торгів',
                    ];
                }

                if ($riskStrategy->isDailyDrawdownExceeded($accountBalance)) {
                    return [
                        'status' => 'error',
                        'message' => 'Перевищено максимальний денний збиток',
                    ];
                }
            }

            if ($positionSize === null) {
                $riskPercentage = $riskStrategy->risk_percentage ?? 1.0;

                $positionSize = $this->riskManager->calculatePositionSize(
                    $accountBalance,
                    $riskPercentage,
                    $entryPrice,
                    $stopLossPrice,
                    $leverage,
                );
            }

            if ($takeProfitPrice === null && $riskStrategy) {
                $riskRewardRatio = $riskStrategy->risk_reward_ratio ?? 2.0;

                $takeProfitPrice = $this->riskManager->calculateTakeProfitPrice(
                    $entryPrice,
                    $stopLossPrice,
                    $riskRewardRatio,
                    $direction,
                );
            }

            if ($positionSize < $tradingPair->min_order_size) {
                return [
                    'status' => 'error',
                    'message' => "Розмір позиції ({$positionSize}) менше мінімального ({$tradingPair->min_order_size})",
                ];
            }

            if ($tradingPair->max_order_size && $positionSize > $tradingPair->max_order_size) {
                return [
                    'status' => 'error',
                    'message' => "Розмір позиції ({$positionSize}) більше максимального ({$tradingPair->max_order_size})",
                ];
            }

            if ($riskStrategy && $riskStrategy->max_risk_per_trade) {
                $riskAmount = $accountBalance * ($riskStrategy->risk_percentage / 100);

                if ($riskAmount > $riskStrategy->max_risk_per_trade) {
                    $riskAmount = $riskStrategy->max_risk_per_trade;

                    $positionSize = $this
                        ->riskManager
                        ->calculatePositionSize(
                            $accountBalance,
                            ($riskAmount / $accountBalance) * 100,
                            $entryPrice,
                            $stopLossPrice,
                            $leverage,
                        );
                }
            }

            if ($leverage !== null && $leverage > 1) {
                $exchange->setLeverage($leverage, $tradingPair->symbol);
            }

            $entryOrderSide = $this->isLongPosition($direction) ? 'buy' : 'sell';

            $entryOrder = $exchange->createOrder(
                $tradingPair->symbol,
                $options['order_type'] ?? 'limit',
                $entryOrderSide,
                $positionSize,
                $entryPrice,
                $options['order_params'] ?? [],
            );

            $stopLossOrder = null;

            if ($options['create_stop_loss'] ?? true) {
                $stopLossOrderSide = $this->isLongPosition($direction) ? 'sell' : 'buy';

                $stopLossOrder = $exchange->createOrder(
                    $tradingPair->symbol,
                    'stop_loss',
                    $stopLossOrderSide,
                    $positionSize,
                    $stopLossPrice,
                    $options['stop_loss_params'] ?? [],
                );
            }

            $takeProfitOrder = null;

            if (($options['create_take_profit'] ?? true) && $takeProfitPrice !== null) {
                $takeProfitOrderSide = $this->isLongPosition($direction) ? 'sell' : 'buy';

                $takeProfitOrder = $exchange->createOrder(
                    $tradingPair->symbol,
                    'take_profit',
                    $takeProfitOrderSide,
                    $positionSize,
                    $takeProfitPrice,
                    $options['take_profit_params'] ?? []
                );
            }

            $position = new TradingPosition();
            $position->user_id = $user->id;
            $position->api_key_id = $apiKeyId;
            $position->trading_pair_id = $tradingPairId;
            $position->risk_strategy_id = $riskStrategyId;
            $position->position_type = $options['position_type'] ?? TradingPosition::TYPE_MANUAL;
            $position->direction = $this->isLongPosition($direction) ? TradingPosition::DIRECTION_LONG : TradingPosition::DIRECTION_SHORT;
            $position->status = TradingPosition::STATUS_OPEN;
            $position->entry_price = $entryPrice;
            $position->size = $positionSize;
            $position->leverage = $leverage;
            $position->entry_order_id = $entryOrder['id'] ?? null;
            $position->opened_at = now();
            $position->stop_loss = $stopLossPrice;
            $position->stop_loss_order_id = $stopLossOrder['id'] ?? null;
            $position->take_profit = $takeProfitPrice;
            $position->take_profit_order_id = $takeProfitOrder['id'] ?? null;
            $position->trailing_stop = $riskStrategy ? $riskStrategy->use_trailing_stop : false;
            $position->trailing_stop_distance = $riskStrategy ? $riskStrategy->trailing_stop_distance : null;
            $position->save();

            return [
                'status' => 'success',
                'message' => 'Позицію відкрито успішно',
                'position_id' => $position->id,
                'entry_order_id' => $entryOrder['id'] ?? null,
                'stop_loss_order_id' => $stopLossOrder['id'] ?? null,
                'take_profit_order_id' => $takeProfitOrder['id'] ?? null,
                'position' => $position,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка відкриття позиції', [
                'user_id' => $user->id,
                'api_key_id' => $apiKeyId,
                'trading_pair_id' => $tradingPairId,
                'direction' => $direction,
                'entry_price' => $entryPrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Закрити існуючу позицію.
     *
     * @param TradingPosition $position Торгова позиція.
     * @param float|null $exitPrice Ціна виходу (null для поточної ринкової ціни).
     * @param array $options Додаткові параметри.
     * @return array Результат закриття позиції.
     */
    public function closePosition(
        TradingPosition $position,
        ?float $exitPrice = null,
        array $options = []
    ): array {
        if (!$position->isOpen()) {
            return [
                'status' => 'error',
                'message' => 'Позиція вже закрита',
            ];
        }

        try {
            $exchange = ExchangeFactory::createWithApiKey($position->api_key_id);

            $tradingPair = $position->tradingPair;

            if ($exitPrice === null) {
                $ticker = $exchange->getTicker($tradingPair->symbol);
                $exitPrice = $ticker['last'] ?? $ticker['close'] ?? null;

                if (!$exitPrice) {
                    return [
                        'status' => 'error',
                        'message' => 'Не вдалося отримати поточну ціну',
                    ];
                }
            }

            $this->cancelPositionOrders($position, $exchange);

            $exitOrderSide = $position->isLong() ? 'sell' : 'buy';

            $exitOrder = $exchange->createOrder(
                $tradingPair->symbol,
                $options['order_type'] ?? 'market',
                $exitOrderSide,
                $position->size,
                $exitPrice,
                $options['order_params'] ?? [],
            );

            // Розрахунок прибутку/збитку
            $pnl = $this->riskManager->calculatePotentialPnL(
                $position->entry_price,
                $exitPrice,
                $position->size,
                $position->direction,
                $position->leverage,
            );

            if ($pnl > 0) {
                $result = TradingPosition::RESULT_PROFIT;
            } elseif ($pnl < 0) {
                $result = TradingPosition::RESULT_LOSS;
            } else {
                $result = TradingPosition::RESULT_BREAKEVEN;
            }

            $position->exit_price = $exitPrice;
            $position->exit_order_id = $exitOrder['id'] ?? null;
            $position->closed_at = now();
            $position->status = TradingPosition::STATUS_CLOSED;
            $position->realized_pnl = $pnl;
            $position->fee = $exitOrder['fee']['cost'] ?? null;
            $position->result = $result;
            $position->save();

            return [
                'status' => 'success',
                'message' => 'Позицію закрито успішно',
                'position_id' => $position->id,
                'exit_order_id' => $exitOrder['id'] ?? null,
                'realized_pnl' => $pnl,
                'result' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка закриття позиції', [
                'position_id' => $position->id,
                'exit_price' => $exitPrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Скасувати всі відкриті ордери для позиції.
     *
     * @param TradingPosition $position Торгова позиція.
     * @param ExchangeInterface $exchange Інтерфейс біржі.
     * @return array Результати скасування ордерів.
     */
    protected function cancelPositionOrders(TradingPosition $position, ExchangeInterface $exchange): array
    {
        $results = [];
        $symbol = $position->tradingPair->symbol;

        if ($position->stop_loss_order_id) {
            try {
                $result = $exchange->cancelOrder($symbol, $position->stop_loss_order_id);
                $results['stop_loss'] = $result;
            } catch (\Exception $e) {
                Log::error('Помилка скасування ордера стоп-лосу', [
                    'position_id' => $position->id,
                    'order_id' => $position->stop_loss_order_id,
                    'error' => $e->getMessage(),
                ]);

                $results['stop_loss'] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($position->take_profit_order_id) {
            try {
                $result = $exchange->cancelOrder($symbol, $position->take_profit_order_id);
                $results['take_profit'] = $result;
            } catch (\Exception $e) {
                Log::error('Помилка скасування ордера тейк-профіту', [
                    'position_id' => $position->id,
                    'order_id' => $position->take_profit_order_id,
                    'error' => $e->getMessage(),
                ]);

                $results['take_profit'] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Оновити стоп-лос для позиції.
     *
     * @param TradingPosition $position Торгова позиція.
     * @param float $newStopLossPrice Нова ціна стоп-лосу.
     * @param array $options Додаткові параметри.
     * @return array Результат оновлення стоп-лосу.
     */
    public function updateStopLoss(
        TradingPosition $position,
        float $newStopLossPrice,
        array $options = [],
    ): array {
        if (!$position->isOpen()) {
            return [
                'status' => 'error',
                'message' => 'Позиція закрита',
            ];
        }

        try {
            $exchange = ExchangeFactory::createWithApiKey($position->api_key_id);

            $tradingPair = $position->tradingPair;

            if ($newStopLossPrice === $position->stop_loss) {
                return [
                    'status' => 'info',
                    'message' => 'Ціна стоп-лосу не змінилася',
                ];
            }

            if ($position->stop_loss_order_id) {
                try {
                    $exchange->cancelOrder($tradingPair->symbol, $position->stop_loss_order_id);
                } catch (\Exception $e) {
                    Log::error('Помилка скасування ордера стоп-лосу', [
                        'position_id' => $position->id,
                        'order_id' => $position->stop_loss_order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $stopLossOrderSide = $position->isLong() ? 'sell' : 'buy';

            $stopLossOrder = $exchange->createOrder(
                $tradingPair->symbol,
                'stop_loss',
                $stopLossOrderSide,
                $position->size,
                $newStopLossPrice,
                $options['stop_loss_params'] ?? []
            );

            $position->stop_loss = $newStopLossPrice;
            $position->stop_loss_order_id = $stopLossOrder['id'] ?? null;
            $position->save();

            return [
                'status' => 'success',
                'message' => 'Стоп-лос оновлено успішно',
                'position_id' => $position->id,
                'previous_stop_loss' => $position->getOriginal('stop_loss'),
                'new_stop_loss' => $newStopLossPrice,
                'stop_loss_order_id' => $stopLossOrder['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка оновлення стоп-лосу', [
                'position_id' => $position->id,
                'new_stop_loss_price' => $newStopLossPrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Оновити тейк-профіт для позиції.
     *
     * @param TradingPosition $position Торгова позиція.
     * @param float $newTakeProfitPrice Нова ціна тейк-профіту.
     * @param array $options Додаткові параметри.
     * @return array Результат оновлення тейк-профіту.
     */
    public function updateTakeProfit(
        TradingPosition $position,
        float $newTakeProfitPrice,
        array $options = [],
    ): array {
        if (!$position->isOpen()) {
            return [
                'status' => 'error',
                'message' => 'Позиція закрита',
            ];
        }

        try {
            $exchange = ExchangeFactory::createWithApiKey($position->api_key_id);

            $tradingPair = $position->tradingPair;

            if ($newTakeProfitPrice === $position->take_profit) {
                return [
                    'status' => 'info',
                    'message' => 'Ціна тейк-профіту не змінилася',
                ];
            }

            if ($position->take_profit_order_id) {
                try {
                    $exchange->cancelOrder($tradingPair->symbol, $position->take_profit_order_id);
                } catch (\Exception $e) {
                    Log::error('Помилка скасування ордера тейк-профіту', [
                        'position_id' => $position->id,
                        'order_id' => $position->take_profit_order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $takeProfitOrderSide = $position->isLong() ? 'sell' : 'buy';

            $takeProfitOrder = $exchange->createOrder(
                $tradingPair->symbol,
                'take_profit',
                $takeProfitOrderSide,
                $position->size,
                $newTakeProfitPrice,
                $options['take_profit_params'] ?? []
            );

            $position->take_profit = $newTakeProfitPrice;
            $position->take_profit_order_id = $takeProfitOrder['id'] ?? null;
            $position->save();

            return [
                'status' => 'success',
                'message' => 'Тейк-профіт оновлено успішно',
                'position_id' => $position->id,
                'previous_take_profit' => $position->getOriginal('take_profit'),
                'new_take_profit' => $newTakeProfitPrice,
                'take_profit_order_id' => $takeProfitOrder['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка оновлення тейк-профіту', [
                'position_id' => $position->id,
                'new_take_profit_price' => $newTakeProfitPrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function isLongPosition(string $direction): bool
    {
        $direction = strtolower($direction);

        return $direction === 'buy' || $direction === 'long' ||
            $direction === TradingPosition::DIRECTION_LONG;
    }
}
