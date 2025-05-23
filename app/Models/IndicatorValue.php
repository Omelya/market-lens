<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property-read \App\Models\TechnicalIndicator|null $technicalIndicator
 * @property-read \App\Models\TradingPair|null $tradingPair
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue query()
 * @property int $id
 * @property int $technical_indicator_id
 * @property int $trading_pair_id
 * @property string $timeframe
 * @property \Illuminate\Support\Carbon $timestamp
 * @property array<array-key, mixed> $parameters
 * @property array<array-key, mixed> $values
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereTechnicalIndicatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereTimeframe($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereTradingPairId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndicatorValue whereValues($value)
 * @mixin \Eloquent
 */
class IndicatorValue extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'technical_indicator_id',
        'trading_pair_id',
        'timeframe',
        'timestamp',
        'parameters',
        'values',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'parameters' => 'array',
        'values' => 'array',
    ];

    /**
     * Отримати технічний індикатор, до якого належать значення.
     */
    public function technicalIndicator(): BelongsTo
    {
        return $this->belongsTo(TechnicalIndicator::class);
    }

    /**
     * Отримати торгову пару, до якої належать значення.
     */
    public function tradingPair(): BelongsTo
    {
        return $this->belongsTo(TradingPair::class);
    }

    /**
     * Отримати основне значення індикатора.
     *
     * @param string|null $key Ключ значення, якщо індикатор має кілька значень
     * @return float|null
     */
    public function getValue(string $key = null): ?float
    {
        if ($key === null) {
            // Для індикаторів з одним значенням
            if (isset($this->values['value'])) {
                return $this->values['value'];
            }

            // Якщо немає ключа 'value', повертаємо перше значення
            $values = $this->values;
            return reset($values);
        }

        // Для індикаторів з кількома значеннями (наприклад, MACD)
        return $this->values[$key] ?? null;
    }

    /**
     * Перевірити, чи перевищує значення індикатора заданий рівень.
     *
     * @param float $level
     * @param string|null $key
     * @return bool
     */
    public function isAboveLevel(float $level, string $key = null): bool
    {
        $value = $this->getValue($key);
        return $value !== null && $value > $level;
    }

    /**
     * Перевірити, чи нижче значення індикатора заданого рівня.
     *
     * @param float $level
     * @param string|null $key
     * @return bool
     */
    public function isBelowLevel(float $level, string $key = null): bool
    {
        $value = $this->getValue($key);
        return $value !== null && $value < $level;
    }

    /**
     * Перевірити, чи знаходиться значення індикатора між заданими рівнями.
     *
     * @param float $lowerLevel
     * @param float $upperLevel
     * @param string|null $key
     * @return bool
     */
    public function isBetweenLevels(float $lowerLevel, float $upperLevel, string $key = null): bool
    {
        $value = $this->getValue($key);
        return $value !== null && $value >= $lowerLevel && $value <= $upperLevel;
    }
}
