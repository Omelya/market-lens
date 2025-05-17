<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingPosition extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'api_key_id',
        'trading_pair_id',
        'trading_signal_id',
        'position_type',
        'direction',
        'status',
        'entry_price',
        'size',
        'leverage',
        'entry_order_id',
        'opened_at',
        'exit_price',
        'exit_order_id',
        'closed_at',
        'stop_loss',
        'stop_loss_order_id',
        'take_profit',
        'take_profit_order_id',
        'trailing_stop',
        'trailing_stop_distance',
        'realized_pnl',
        'fee',
        'result',
        'metadata',
        'notes',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'entry_price' => 'decimal:12',
        'size' => 'decimal:12',
        'leverage' => 'decimal:2',
        'exit_price' => 'decimal:12',
        'stop_loss' => 'decimal:12',
        'take_profit' => 'decimal:12',
        'trailing_stop' => 'boolean',
        'trailing_stop_distance' => 'decimal:12',
        'realized_pnl' => 'decimal:12',
        'fee' => 'decimal:12',
        'metadata' => 'array',
    ];

    /**
     * Типи позицій.
     */
    public const TYPE_MANUAL = 'manual';
    public const TYPE_AUTO = 'auto';

    /**
     * Напрямки позицій.
     */
    public const DIRECTION_LONG = 'long';
    public const DIRECTION_SHORT = 'short';

    /**
     * Статуси позицій.
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_ERROR = 'error';

    /**
     * Результати позицій.
     */
    public const RESULT_PROFIT = 'profit';
    public const RESULT_LOSS = 'loss';
    public const RESULT_BREAKEVEN = 'breakeven';
    public const RESULT_UNKNOWN = 'unknown';

    /**
     * Отримати користувача, якому належить позиція.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отримати API ключ, який використовується для позиції.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ExchangeApiKey::class);
    }

    /**
     * Отримати торгову пару для позиції.
     */
    public function tradingPair(): BelongsTo
    {
        return $this->belongsTo(TradingPair::class);
    }

    /**
     * Отримати торговий сигнал, на основі якого відкрита позиція.
     */
    public function tradingSignal(): BelongsTo
    {
        return $this->belongsTo(TradingSignal::class);
    }

    /**
     * Перевірити, чи позиція відкрита.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Перевірити, чи позиція закрита.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Перевірити, чи позиція скасована.
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    /**
     * Перевірити, чи позиція має помилку.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Перевірити, чи позиція є довгою (лонг).
     *
     * @return bool
     */
    public function isLong(): bool
    {
        return $this->direction === self::DIRECTION_LONG;
    }

    /**
     * Перевірити, чи позиція є короткою (шорт).
     *
     * @return bool
     */
    public function isShort(): bool
    {
        return $this->direction === self::DIRECTION_SHORT;
    }

    /**
     * Обчислити нереалізований прибуток/збиток на основі поточної ціни.
     *
     * @param float $currentPrice
     * @return float|null
     */
    public function unrealizedPnl(float $currentPrice): ?float
    {
        if (!$this->isOpen() || $this->entry_price === null) {
            return null;
        }

        $priceDifference = $this->isLong()
            ? $currentPrice - $this->entry_price
            : $this->entry_price - $currentPrice;

        return $priceDifference * $this->size * $this->leverage;
    }

    /**
     * Обчислити відсоток прибутку/збитку на основі поточної ціни.
     *
     * @param float $currentPrice
     * @return float|null
     */
    public function unrealizedPnlPercent(float $currentPrice): ?float
    {
        if (!$this->isOpen() || $this->entry_price === null || $this->entry_price == 0) {
            return null;
        }

        $priceDifference = $this->isLong()
            ? $currentPrice - $this->entry_price
            : $this->entry_price - $currentPrice;

        return ($priceDifference / $this->entry_price) * 100 * $this->leverage;
    }

    /**
     * Закрити позицію на основі даних закриття.
     *
     * @param float $exitPrice
     * @param string|null $exitOrderId
     * @param float|null $realizedPnl
     * @param float|null $fee
     * @param string|null $result
     * @return bool
     */
    public function closePosition(
        float $exitPrice,
        string $exitOrderId = null,
        float $realizedPnl = null,
        float $fee = null,
        string $result = null
    ): bool {
        if (!$this->isOpen()) {
            return false;
        }

        if ($realizedPnl === null) {
            $priceDifference = $this->isLong()
                ? $exitPrice - $this->entry_price
                : $this->entry_price - $exitPrice;

            $realizedPnl = $priceDifference * $this->size * $this->leverage;
        }

        if ($result === null) {
            if ($realizedPnl > 0) {
                $result = self::RESULT_PROFIT;
            } elseif ($realizedPnl < 0) {
                $result = self::RESULT_LOSS;
            } else {
                $result = self::RESULT_BREAKEVEN;
            }
        }

        return $this->update([
            'exit_price' => $exitPrice,
            'exit_order_id' => $exitOrderId,
            'closed_at' => now(),
            'status' => self::STATUS_CLOSED,
            'realized_pnl' => $realizedPnl,
            'fee' => $fee,
            'result' => $result,
        ]);
    }

    /**
     * Скасувати позицію.
     *
     * @param string|null $reason
     * @return bool
     */
    public function cancelPosition(string $reason = null): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        $metadata = $this->metadata ?? [];
        $metadata['cancel_reason'] = $reason;

        return $this->update([
            'status' => self::STATUS_CANCELED,
            'closed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Оновити рівень стоп-лосу.
     *
     * @param float $newStopLoss
     * @param string|null $stopLossOrderId
     * @return bool
     */
    public function updateStopLoss(float $newStopLoss, string $stopLossOrderId = null): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        return $this->update([
            'stop_loss' => $newStopLoss,
            'stop_loss_order_id' => $stopLossOrderId ?? $this->stop_loss_order_id,
        ]);
    }

    /**
     * Оновити рівень тейк-профіту.
     *
     * @param float $newTakeProfit
     * @param string|null $takeProfitOrderId
     * @return bool
     */
    public function updateTakeProfit(float $newTakeProfit, string $takeProfitOrderId = null): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        return $this->update([
            'take_profit' => $newTakeProfit,
            'take_profit_order_id' => $takeProfitOrderId ?? $this->take_profit_order_id,
        ]);
    }
}
