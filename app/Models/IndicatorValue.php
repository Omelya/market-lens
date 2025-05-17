<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
