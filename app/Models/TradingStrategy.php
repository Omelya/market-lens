<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy query()
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property array<array-key, mixed>|null $trading_pairs
 * @property array<array-key, mixed>|null $timeframes
 * @property array<array-key, mixed>|null $indicators
 * @property array<array-key, mixed>|null $entry_rules
 * @property array<array-key, mixed>|null $exit_rules
 * @property numeric|null $risk_per_trade
 * @property numeric|null $max_open_positions
 * @property numeric|null $max_daily_drawdown
 * @property string $execution_mode
 * @property bool $notifications_enabled
 * @property int $total_trades
 * @property int $winning_trades
 * @property int $losing_trades
 * @property numeric $win_rate
 * @property numeric $average_profit
 * @property numeric $average_loss
 * @property numeric $profit_factor
 * @property numeric $total_profit
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereAverageLoss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereAverageProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereEntryRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereExecutionMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereExitRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereIndicators($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereLosingTrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereMaxDailyDrawdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereMaxOpenPositions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereNotificationsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereProfitFactor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereRiskPerTrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereTimeframes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereTotalProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereTotalTrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereTradingPairs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereWinRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradingStrategy whereWinningTrades($value)
 * @mixin \Eloquent
 */
class TradingStrategy extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_active',
        'trading_pairs',
        'timeframes',
        'indicators',
        'entry_rules',
        'exit_rules',
        'risk_per_trade',
        'max_open_positions',
        'max_daily_drawdown',
        'execution_mode',
        'notifications_enabled',
        'total_trades',
        'winning_trades',
        'losing_trades',
        'win_rate',
        'average_profit',
        'average_loss',
        'profit_factor',
        'total_profit',
        'metadata',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'trading_pairs' => 'array',
        'timeframes' => 'array',
        'indicators' => 'array',
        'entry_rules' => 'array',
        'exit_rules' => 'array',
        'risk_per_trade' => 'decimal:2',
        'max_open_positions' => 'decimal:2',
        'max_daily_drawdown' => 'decimal:2',
        'notifications_enabled' => 'boolean',
        'win_rate' => 'decimal:2',
        'average_profit' => 'decimal:2',
        'average_loss' => 'decimal:2',
        'profit_factor' => 'decimal:2',
        'total_profit' => 'decimal:12',
        'metadata' => 'array',
    ];

    /**
     * Режими виконання.
     */
    public const MODE_MANUAL = 'manual';
    public const MODE_SEMI_AUTO = 'semi_auto';
    public const MODE_AUTO = 'auto';

    /**
     * Отримати користувача, якому належить стратегія.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Перевірити, чи стратегія активна.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Активувати стратегію.
     *
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Деактивувати стратегію.
     *
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Отримати торгові пари для стратегії як об'єкти моделей.
     *
     * @return Collection
     */
    public function getTradingPairs(): Collection
    {
        if (empty($this->trading_pairs)) {
            return Collection::make();
        }

        return TradingPair::whereIn('id', $this->trading_pairs)->get();
    }

    /**
     * Оновити статистику стратегії на основі нової позиції.
     *
     * @param TradingPosition $position
     * @return bool
     */
    public function updateStatistics(TradingPosition $position): bool
    {
        if (!$position->isClosed()) {
            return false;
        }

        $totalTrades = $this->total_trades + 1;
        $winningTrades = $this->winning_trades;
        $losingTrades = $this->losing_trades;
        $totalProfit = $this->total_profit + $position->realized_pnl;

        if ($position->result === TradingPosition::RESULT_PROFIT) {
            $winningTrades++;
        } elseif ($position->result === TradingPosition::RESULT_LOSS) {
            $losingTrades++;
        }

        $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;

        $averageProfit = $winningTrades > 0
            ? $this->calculateAverageProfit($position, $this->average_profit, $this->winning_trades, $winningTrades)
            : $this->average_profit;

        $averageLoss = $losingTrades > 0
            ? $this->calculateAverageLoss($position, $this->average_loss, $this->losing_trades, $losingTrades)
            : $this->average_loss;

        $profitFactor = abs($averageLoss) > 0 ? abs($averageProfit / $averageLoss) : 0;

        return $this->update([
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'win_rate' => $winRate,
            'average_profit' => $averageProfit,
            'average_loss' => $averageLoss,
            'profit_factor' => $profitFactor,
            'total_profit' => $totalProfit,
        ]);
    }

    /**
     * Розрахувати середній прибуток.
     *
     * @param TradingPosition $position
     * @param float $currentAverageProfit
     * @param int $currentWinningTrades
     * @param int $newWinningTrades
     * @return float
     */
    private function calculateAverageProfit(
        TradingPosition $position,
        float $currentAverageProfit,
        int $currentWinningTrades,
        int $newWinningTrades
    ): float {
        if ($position->result !== TradingPosition::RESULT_PROFIT || $position->realized_pnl <= 0) {
            return $currentAverageProfit;
        }

        if ($currentWinningTrades === 0) {
            return $position->realized_pnl;
        }

        $totalProfit = $currentAverageProfit * $currentWinningTrades + $position->realized_pnl;
        return $totalProfit / $newWinningTrades;
    }

    /**
     * Розрахувати середній збиток.
     *
     * @param TradingPosition $position
     * @param float $currentAverageLoss
     * @param int $currentLosingTrades
     * @param int $newLosingTrades
     * @return float
     */
    private function calculateAverageLoss(
        TradingPosition $position,
        float $currentAverageLoss,
        int $currentLosingTrades,
        int $newLosingTrades
    ): float {
        if ($position->result !== TradingPosition::RESULT_LOSS || $position->realized_pnl >= 0) {
            return $currentAverageLoss;
        }

        if ($currentLosingTrades === 0) {
            return $position->realized_pnl;
        }

        $totalLoss = $currentAverageLoss * $currentLosingTrades + $position->realized_pnl;
        return $totalLoss / $newLosingTrades;
    }
}
