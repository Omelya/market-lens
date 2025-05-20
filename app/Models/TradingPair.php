<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property-read \App\Models\Exchange|null $exchange
 * @property-read Collection<int, \App\Models\HistoricalData> $historicalData
 * @property-read int|null $historical_data_count
 * @property-read Collection<int, \App\Models\IndicatorValue> $indicatorValues
 * @property-read int|null $indicator_values_count
 * @property-read Collection<int, \App\Models\TradingPosition> $tradingPositions
 * @property-read int|null $trading_positions_count
 * @property-read Collection<int, \App\Models\TradingSignal> $tradingSignals
 * @property-read int|null $trading_signals_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair query()
 * @property int $id
 * @property int $exchange_id
 * @property string $symbol
 * @property string $base_currency
 * @property string $quote_currency
 * @property numeric|null $min_order_size
 * @property numeric|null $max_order_size
 * @property numeric|null $price_precision
 * @property numeric|null $size_precision
 * @property numeric|null $maker_fee
 * @property numeric|null $taker_fee
 * @property bool $is_active
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereBaseCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereExchangeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereMakerFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereMaxOrderSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereMinOrderSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair wherePricePrecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereQuoteCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereSizePrecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereTakerFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingPair whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
