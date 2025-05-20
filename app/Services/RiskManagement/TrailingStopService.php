<?php

namespace App\Services\RiskManagement;

use App\Interfaces\RiskManagerInterface;
use App\Models\TradingPosition;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

class TrailingStopService
{
    protected RiskManagerInterface $riskManager;

    public function __construct(RiskManagerInterface $riskManager)
    {
        $this->riskManager = $riskManager;
    }

    /**
     * Оновити трейлінг-стопи для всіх активних позицій.
     */
    public function updateAllTrailingStops(): array
    {
        $results = [];

        $positions = TradingPosition::where('status', TradingPosition::STATUS_OPEN)
            ->where('trailing_stop', true)
            ->with(['tradingPair.exchange', 'apiKey', 'riskStrategy'])
            ->get();

        foreach ($positions as $position) {
            try {
                $result = $this->updatePositionTrailingStop($position);
                $results[] = $result;
            } catch (\Exception $e) {
                Log::error('Помилка оновлення трейлінг-стопу', [
                    'position_id' => $position->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results[] = [
                    'position_id' => $position->id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'total' => count($positions),
            'success' => count(array_filter($results, fn($r) => $r['status'] === 'success')),
            'error' => count(array_filter($results, fn($r) => $r['status'] === 'error')),
            'results' => $results,
        ];
    }

    /**
     * Оновити трейлінг-стоп для конкретної позиції.
     */
    public function updatePositionTrailingStop(TradingPosition $position): array
    {
        if (!$position->isOpen() || !$position->trailing_stop) {
            return [
                'position_id' => $position->id,
                'status' => 'error',
                'message' => 'Позиція закрита або трейлінг-стоп не активований',
            ];
        }

        try {
            $exchange = ExchangeFactory::createWithApiKey($position->api_key_id);

            $ticker = $exchange->getTicker($position->tradingPair->symbol);
            $currentPrice = $ticker['last'] ?? $ticker['close'] ?? null;

            if (!$currentPrice) {
                return [
                    'position_id' => $position->id,
                    'status' => 'error',
                    'message' => 'Не вдалося отримати поточну ціну',
                ];
            }

            $trailingDistance = $position->trailing_stop_distance;
            $activationPercentage = $position->riskStrategy?->trailing_stop_activation;

            if (!$trailingDistance && $position->riskStrategy) {
                $trailingDistance = $position->riskStrategy->trailing_stop_distance;
            }

            if (!$trailingDistance) {
                return [
                    'position_id' => $position->id,
                    'status' => 'error',
                    'message' => 'Не встановлена відстань трейлінг-стопу',
                ];
            }

            $newStopLoss = $this->riskManager->updateTrailingStop(
                $currentPrice,
                $position->entry_price,
                $position->stop_loss,
                $position->direction,
                $trailingDistance,
                $activationPercentage
            );

            if ($newStopLoss == $position->stop_loss) {
                return [
                    'position_id' => $position->id,
                    'status' => 'info',
                    'message' => 'Рівень стоп-лосу залишився без змін',
                    'stop_loss' => $newStopLoss,
                ];
            }

            if ($position->stop_loss_order_id) {
                $exchange->cancelOrder($position->tradingPair->symbol, $position->stop_loss_order_id);

                $stopLossOrder = $exchange->createOrder(
                    $position->tradingPair->symbol,
                    'stop_loss',
                    $position->isLong() ? 'sell' : 'buy',
                    $position->size,
                    $newStopLoss
                );

                $position->stop_loss_order_id = $stopLossOrder['id'] ?? null;
            }

            $position->stop_loss = $newStopLoss;
            $position->save();

            return [
                'position_id' => $position->id,
                'status' => 'success',
                'message' => 'Рівень стоп-лосу оновлено',
                'previous_stop_loss' => $position->getOriginal('stop_loss'),
                'new_stop_loss' => $newStopLoss,
                'current_price' => $currentPrice,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка оновлення трейлінг-стопу для позиції', [
                'position_id' => $position->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'position_id' => $position->id,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Активувати трейлінг-стоп для конкретної позиції.
     *
     * @param TradingPosition $position Торгова позиція.
     * @param float $trailingDistance Відстань трейлінг-стопу у відсотках.
     * @param float|null $activationPercentage Відсоток від потенційного прибутку для активації (null для активації відразу).
     * @return array Результат активації.
     */
    public function activateTrailingStop(
        TradingPosition $position,
        float $trailingDistance,
        ?float $activationPercentage = null,
    ): array {
        if (!$position->isOpen()) {
            return [
                'position_id' => $position->id,
                'status' => 'error',
                'message' => 'Позиція закрита',
            ];
        }

        $position->trailing_stop = true;
        $position->trailing_stop_distance = $trailingDistance;
        $position->save();

        return $this->updatePositionTrailingStop($position);
    }

    public function deactivateTrailingStop(TradingPosition $position): array
    {
        if (!$position->isOpen()) {
            return [
                'position_id' => $position->id,
                'status' => 'error',
                'message' => 'Позиція закрита',
            ];
        }

        $position->trailing_stop = false;
        $position->save();

        return [
            'position_id' => $position->id,
            'status' => 'success',
            'message' => 'Трейлінг-стоп деактивовано',
        ];
    }
}
