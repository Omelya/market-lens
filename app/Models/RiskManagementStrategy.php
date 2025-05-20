<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $description
 * @property float $risk_percentage
 * @property float $risk_reward_ratio
 * @property bool $use_trailing_stop
 * @property float|null $trailing_stop_activation
 * @property float|null $trailing_stop_distance
 * @property float|null $max_risk_per_trade
 * @property int|null $max_concurrent_trades
 * @property float|null $max_daily_drawdown
 * @property bool $is_active
 * @property array|null $parameters
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TradingPosition> $tradingPositions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int|null $trading_positions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereMaxConcurrentTrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereMaxDailyDrawdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereMaxRiskPerTrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereRiskPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereRiskRewardRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereTrailingStopActivation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereTrailingStopDistance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereUseTrailingStop($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskManagementStrategy whereUserId($value)
 * @mixin \Eloquent
 */
class RiskManagementStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'risk_percentage',
        'risk_reward_ratio',
        'use_trailing_stop',
        'trailing_stop_activation',
        'trailing_stop_distance',
        'max_risk_per_trade',
        'max_concurrent_trades',
        'max_daily_drawdown',
        'is_active',
        'parameters',
    ];

    protected $casts = [
        'risk_percentage' => 'float',
        'risk_reward_ratio' => 'float',
        'use_trailing_stop' => 'boolean',
        'trailing_stop_activation' => 'float',
        'trailing_stop_distance' => 'float',
        'max_risk_per_trade' => 'float',
        'max_concurrent_trades' => 'integer',
        'max_daily_drawdown' => 'float',
        'is_active' => 'boolean',
        'parameters' => 'array',
    ];

    protected $attributes = [
        'risk_percentage' => 1.0,
        'risk_reward_ratio' => 2.0,
        'use_trailing_stop' => false,
        'is_active' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tradingPositions(): HasMany
    {
        return $this->hasMany(TradingPosition::class, 'risk_strategy_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Перевірити, чи перевищено максимальну кількість одночасних торгів.
     */
    public function isMaxConcurrentTradesExceeded(): bool
    {
        if ($this->max_concurrent_trades === null) {
            return false;
        }

        $activePositionsCount = $this->tradingPositions()
            ->where('status', TradingPosition::STATUS_OPEN)
            ->count();

        return $activePositionsCount >= $this->max_concurrent_trades;
    }

    /**
     * Перевірити, чи перевищено максимальний денний збиток.
     */
    public function isDailyDrawdownExceeded(float $accountBalance): bool
    {
        if ($this->max_daily_drawdown === null) {
            return false;
        }

        $today = now()->startOfDay();
        $dailyLoss = $this->tradingPositions()
            ->where('status', TradingPosition::STATUS_CLOSED)
            ->where('result', TradingPosition::RESULT_LOSS)
            ->where('closed_at', '>=', $today)
            ->sum('realized_pnl');

        return abs($dailyLoss) > ($accountBalance * ($this->max_daily_drawdown / 100));
    }

    /**
     * Отримати значення параметра стратегії.
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        if (!$this->parameters) {
            return $default;
        }

        return $this->parameters[$key] ?? $default;
    }

    /**
     * Встановити значення параметра стратегії.
     */
    public function setParameter(string $key, mixed $value): bool
    {
        $parameters = $this->parameters ?? [];
        $parameters[$key] = $value;

        return $this->update(['parameters' => $parameters]);
    }
}
