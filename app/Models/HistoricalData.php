<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property-read \App\Models\TradingPair|null $tradingPair
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HistoricalData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HistoricalData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HistoricalData query()
 * @mixin \Eloquent
 */
class HistoricalData extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'trading_pair_id',
        'timeframe',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'open' => 'decimal:12',
        'high' => 'decimal:12',
        'low' => 'decimal:12',
        'close' => 'decimal:12',
        'volume' => 'decimal:12',
    ];

    /**
     * Отримати торгову пару, до якої належать історичні дані.
     */
    public function tradingPair(): BelongsTo
    {
        return $this->belongsTo(TradingPair::class);
    }

    /**
     * Розрахувати зміну ціни у відсотках.
     *
     * @return float
     */
    public function priceChangePercent(): float
    {
        if ($this->open == 0) {
            return 0;
        }

        return (($this->close - $this->open) / $this->open) * 100;
    }

    /**
     * Перевірити, чи свічка є бичачою (зеленою).
     *
     * @return bool
     */
    public function isBullish(): bool
    {
        return $this->close > $this->open;
    }

    /**
     * Перевірити, чи свічка є ведмежою (червоною).
     *
     * @return bool
     */
    public function isBearish(): bool
    {
        return $this->close < $this->open;
    }

    /**
     * Перевірити, чи свічка є доджі (ціна відкриття та закриття майже однакові).
     *
     * @param float $threshold Поріг різниці у відсотках
     * @return bool
     */
    public function isDoji(float $threshold = 0.1): bool
    {
        if ($this->open == 0) {
            return false;
        }

        $changePercent = abs(($this->close - $this->open) / $this->open) * 100;
        return $changePercent <= $threshold;
    }

    /**
     * Отримати розмір тіла свічки.
     *
     * @return float
     */
    public function bodySize(): float
    {
        return abs($this->close - $this->open);
    }

    /**
     * Отримати розмір верхньої тіні свічки.
     *
     * @return float
     */
    public function upperShadowSize(): float
    {
        return $this->high - max($this->open, $this->close);
    }

    /**
     * Отримати розмір нижньої тіні свічки.
     *
     * @return float
     */
    public function lowerShadowSize(): float
    {
        return min($this->open, $this->close) - $this->low;
    }

    /**
     * Отримати повний діапазон свічки (від мінімуму до максимуму).
     *
     * @return float
     */
    public function fullRange(): float
    {
        return $this->high - $this->low;
    }
}
