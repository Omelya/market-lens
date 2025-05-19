<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property-read Collection<int, \App\Models\IndicatorValue> $indicatorValues
 * @property-read int|null $indicator_values_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalIndicator newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalIndicator newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalIndicator query()
 * @mixin \Eloquent
 */
class TechnicalIndicator extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'default_parameters',
        'is_active',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'default_parameters' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Категорії індикаторів.
     */
    public const CATEGORY_TREND = 'trend';
    public const CATEGORY_OSCILLATOR = 'oscillator';
    public const CATEGORY_VOLUME = 'volume';
    public const CATEGORY_VOLATILITY = 'volatility';
    public const CATEGORY_SUPPORT_RESISTANCE = 'support_resistance';
    public const CATEGORY_OTHER = 'other';

    /**
     * Отримати всі значення для цього індикатора.
     */
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    /**
     * Список доступних категорій.
     *
     * @return array
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_TREND => 'Trend',
            self::CATEGORY_OSCILLATOR => 'Oscillator',
            self::CATEGORY_VOLUME => 'Volume',
            self::CATEGORY_VOLATILITY => 'Volatility',
            self::CATEGORY_SUPPORT_RESISTANCE => 'Support/Resistance',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Злиття заданих параметрів із параметрами за замовчуванням.
     *
     * @param array $parameters
     * @return array
     */
    public function mergeWithDefaultParameters(array $parameters): array
    {
        return array_merge($this->default_parameters ?? [], $parameters);
    }

    /**
     * Отримати значення індикатора для певної торгової пари та таймфрейму.
     *
     * @param int $tradingPairId
     * @param string $timeframe
     * @param array $parameters
     * @param int $limit
     * @return Collection
     */
    public function getValuesForTradingPair(int $tradingPairId, string $timeframe, array $parameters = [], int $limit = 100): Collection
    {
        $mergedParameters = $this->mergeWithDefaultParameters($parameters);
        $paramJson = json_encode($mergedParameters);

        return $this->indicatorValues()
            ->where('trading_pair_id', $tradingPairId)
            ->where('timeframe', $timeframe)
            ->where('parameters', $paramJson)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }
}
