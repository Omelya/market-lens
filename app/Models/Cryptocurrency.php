<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cryptocurrency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cryptocurrency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cryptocurrency query()
 * @mixin \Eloquent
 */
class Cryptocurrency extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'symbol',
        'name',
        'logo',
        'description',
        'is_active',
        'current_price',
        'market_cap',
        'volume_24h',
        'metadata',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'current_price' => 'decimal:12',
        'market_cap' => 'decimal:2',
        'volume_24h' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Отримати торгові пари, які включають цю криптовалюту.
     */
    public function tradingPairs()
    {
        return TradingPair::where('base_currency', $this->symbol)
            ->orWhere('quote_currency', $this->symbol)
            ->get();
    }

    /**
     * Отримати торгові пари, де ця криптовалюта є базовою.
     */
    public function basePairs()
    {
        return TradingPair::where('base_currency', $this->symbol)->get();
    }

    /**
     * Отримати торгові пари, де ця криптовалюта є котирувальною.
     */
    public function quotePairs()
    {
        return TradingPair::where('quote_currency', $this->symbol)->get();
    }

    /**
     * Оновити поточну ціну та інші маркет-дані криптовалюти.
     *
     * @param array $marketData
     * @return void
     */
    public function updateMarketData(array $marketData): void
    {
        $this->update([
            'current_price' => $marketData['price'] ?? $this->current_price,
            'market_cap' => $marketData['market_cap'] ?? $this->market_cap,
            'volume_24h' => $marketData['volume_24h'] ?? $this->volume_24h,
        ]);
    }
}
