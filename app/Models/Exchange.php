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
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property string|null $description
 * @property bool $is_active
 * @property array<array-key, mixed>|null $supported_features
 * @property array<array-key, mixed>|null $config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereSupportedFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exchange whereUpdatedAt($value)
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
        return in_array($feature, $this->supported_features ?? [], true);
    }
}
