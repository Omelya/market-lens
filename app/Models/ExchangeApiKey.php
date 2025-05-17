<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
