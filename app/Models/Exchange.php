<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeApiKey> $apiKeys
 * @property-read int|null $api_keys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TradingPair> $tradingPairs
 * @property-read int|null $trading_pairs_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange query()
 * @mixin \Eloquent
 */
class Exchange extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'is_active',
        'supported_features',
        'config',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'supported_features' => 'array',
        'config' => 'array',
    ];

    /**
     * Отримати всі API ключі для цієї біржі.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ExchangeApiKey::class);
    }

    /**
     * Отримати всі торгові пари для цієї біржі.
     */
    public function tradingPairs(): HasMany
    {
        return $this->hasMany(TradingPair::class);
    }

    /**
     * Перевірити, чи підтримує біржа певну функцію.
     *
     * @param string $feature
     * @return bool
     */
    public function supportsFeature(string $feature): bool
    {
        return in_array($feature, $this->supported_features ?? []);
    }
}
