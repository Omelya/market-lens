<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 *
 *
 * @property-read \App\Models\Exchange|null $exchange
 * @property-write mixed $api_key
 * @property-write mixed $api_secret
 * @property mixed $passphrase
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TradingPosition> $tradingPositions
 * @property-read int|null $trading_positions_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey query()
 * @property int $id
 * @property int $user_id
 * @property int $exchange_id
 * @property string $name
 * @property bool $is_test_net
 * @property bool $trading_enabled
 * @property bool $is_active
 * @property array<array-key, mixed>|null $permissions
 * @property string|null $permissions_data
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property string|null $verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereApiSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereExchangeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereIsTestNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey wherePassphrase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey wherePermissionsData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereTradingEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKey whereVerifiedAt($value)
 * @mixin \Eloquent
 */
class ExchangeApiKey extends Model
{
    use HasFactory;

    /**
     * Атрибути, які можна масово призначати.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'exchange_id',
        'name',
        'api_key',
        'api_secret',
        'passphrase',
        'is_test_net',
        'trading_enabled',
        'is_active',
        'permissions',
        'last_used_at',
    ];

    /**
     * Атрибути, які повинні бути перетворені.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_test_net' => 'boolean',
        'trading_enabled' => 'boolean',
        'is_active' => 'boolean',
        'permissions' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * Атрибути, які повинні бути приховані при серіалізації.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'api_key',
        'api_secret',
        'passphrase',
    ];

    /**
     * Отримати користувача, якому належить ключ API.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Отримати біржу, до якої належить ключ API.
     */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    /**
     * Отримати всі позиції, відкриті з використанням цього ключа API.
     */
    public function tradingPositions(): HasMany
    {
        return $this->hasMany(TradingPosition::class);
    }

    /**
     * Зашифрувати API ключ перед збереженням.
     *
     * @param string $value
     * @return void
     */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    /**
     * Зашифрувати API секрет перед збереженням.
     *
     * @param string $value
     * @return void
     */
    public function setApiSecretAttribute($value): void
    {
        $this->attributes['api_secret'] = Crypt::encryptString($value);
    }

    /**
     * Зашифрувати парольну фразу перед збереженням.
     *
     * @param string|null $value
     * @return void
     */
    public function setPassphraseAttribute($value): void
    {
        $this->attributes['passphrase'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Розшифрувати API ключ.
     *
     * @return string
     */
    public function getDecryptedApiKey(): string
    {
        return Crypt::decryptString($this->attributes['api_key']);
    }

    /**
     * Розшифрувати API секрет.
     *
     * @return string
     */
    public function getDecryptedApiSecret(): string
    {
        return Crypt::decryptString($this->attributes['api_secret']);
    }

    /**
     * Розшифрувати парольну фразу.
     *
     * @return string|null
     */
    public function getDecryptedPassphrase(): ?string
    {
        return $this->attributes['passphrase']
            ? Crypt::decryptString($this->attributes['passphrase'])
            : null;
    }

    /**
     * Оновити час останнього використання.
     *
     * @return void
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
