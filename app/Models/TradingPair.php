<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingPair extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'exchange_id',
        'symbol',
        'base_currency',
        'quote_currency',
        'min_order_size',
        'max_order_size',
        'price_precision',
        'size_precision',
        'maker_fee',
        'taker_fee',
        'is_active',
        'metadata',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_order_size' => 'decimal:12',
        'max_order_size' => 'decimal:12',
        'price_precision' => 'decimal:12',
        'size_precision' => 'decimal:12',
        'maker_fee' => 'decimal:6',
        'taker_fee' => 'decimal:6',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Отримати біржу, до якої належить торгова пара.
     */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    /**
     * Отримати історичні дані для цієї торгової пари.
     */
    public function historicalData(): HasMany
    {
        return $this->hasMany(HistoricalData::class);
    }

    /**
     * Отримати значення індикаторів для цієї торгової пари.
     */
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    /**
     * Отримати торгові сигнали для цієї торгової пари.
     */
    public function tradingSignals(): HasMany
    {
        return $this->hasMany(TradingSignal::class);
    }

    /**
     * Отримати торгові позиції для цієї торгової пари.
     */
    public function tradingPositions(): HasMany
    {
        return $this->hasMany(TradingPosition::class);
    }

    /**
     * Отримати базову криптовалюту для цієї торгової пари.
     */
    public function baseCryptocurrency(): Cryptocurrency
    {
        return Cryptocurrency::where('symbol', $this->base_currency)->first();
    }

    /**
     * Отримати котирувальну криптовалюту для цієї торгової пари.
     */
    public function quoteCryptocurrency(): Cryptocurrency
    {
        return Cryptocurrency::where('symbol', $this->quote_currency)->first();
    }

    /**
     * Отримати останні історичні дані для певного таймфрейму.
     *
     * @param string $timeframe
     * @param int $limit
     * @return Collection
     */
    public function getLatestHistoricalData(string $timeframe, int $limit = 100): Collection
    {
        return $this->historicalData()
            ->where('timeframe', $timeframe)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }

    /**
     * Отримати останні торгові сигнали для певного таймфрейму.
     *
     * @param string $timeframe
     * @param int $limit
     * @return Collection
     */
    public function getLatestTradingSignals(string $timeframe, int $limit = 10): Collection
    {
        return $this->tradingSignals()
            ->where('timeframe', $timeframe)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();
    }
}
