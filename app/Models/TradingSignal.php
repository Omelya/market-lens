<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingSignal extends Model
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
        'direction',
        'signal_type',
        'strength',
        'entry_price',
        'stop_loss',
        'take_profit',
        'indicators_data',
        'risk_reward_ratio',
        'success_probability',
        'metadata',
        'is_active',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'entry_price' => 'decimal:12',
        'stop_loss' => 'decimal:12',
        'take_profit' => 'decimal:12',
        'indicators_data' => 'array',
        'risk_reward_ratio' => 'decimal:2',
        'success_probability' => 'decimal:2',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Напрямки сигналів.
     */
    public const DIRECTION_BUY = 'buy';
    public const DIRECTION_SELL = 'sell';
    public const DIRECTION_NEUTRAL = 'neutral';

    /**
     * Типи сигналів.
     */
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_ML = 'ml';
    public const TYPE_COMBINED = 'combined';

    /**
     * Сили сигналів.
     */
    public const STRENGTH_WEAK = 'weak';
    public const STRENGTH_MEDIUM = 'medium';
    public const STRENGTH_STRONG = 'strong';

    /**
     * Отримати торгову пару, до якої належить сигнал.
     */
    public function tradingPair(): BelongsTo
    {
        return $this->belongsTo(TradingPair::class);
    }

    /**
     * Отримати позиції, відкриті на основі цього сигналу.
     */
    public function tradingPositions(): HasMany
    {
        return $this->hasMany(TradingPosition::class);
    }

    /**
     * Перевірити, чи сигнал є дійсним (в межах допустимого часу).
     *
     * @param int $validHours Кількість годин, протягом яких сигнал вважається дійсним
     * @return bool
     */
    public function isValid(int $validHours = 24): bool
    {
        return $this->timestamp->diffInHours(now()) <= $validHours && $this->is_active;
    }

    /**
     * Обчислити потенційний прибуток від сигналу.
     *
     * @return float|null
     */
    public function potentialProfit(): ?float
    {
        if ($this->entry_price === null || $this->take_profit === null) {
            return null;
        }

        if ($this->direction === self::DIRECTION_BUY) {
            return $this->take_profit - $this->entry_price;
        } elseif ($this->direction === self::DIRECTION_SELL) {
            return $this->entry_price - $this->take_profit;
        }

        return null;
    }

    /**
     * Обчислити потенційний збиток від сигналу.
     *
     * @return float|null
     */
    public function potentialLoss(): ?float
    {
        if ($this->entry_price === null || $this->stop_loss === null) {
            return null;
        }

        if ($this->direction === self::DIRECTION_BUY) {
            return $this->entry_price - $this->stop_loss;
        } elseif ($this->direction === self::DIRECTION_SELL) {
            return $this->stop_loss - $this->entry_price;
        }

        return null;
    }

    /**
     * Обчислити відношення ризик/прибуток.
     *
     * @return float|null
     */
    public function calculateRiskRewardRatio(): ?float
    {
        $potentialProfit = $this->potentialProfit();
        $potentialLoss = $this->potentialLoss();

        if ($potentialProfit === null || $potentialLoss === null || $potentialLoss == 0) {
            return null;
        }

        return $potentialProfit / $potentialLoss;
    }

    /**
     * Оновити статус сигналу на неактивний.
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
