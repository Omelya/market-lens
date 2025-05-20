<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property int $exchange_api_key_id
 * @property string $action
 * @property array<array-key, mixed>|null $details
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExchangeApiKey $apiKey
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereExchangeApiKeyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExchangeApiKeyLog whereUserAgent($value)
 * @mixin \Eloquent
 */
class ExchangeApiKeyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange_api_key_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ExchangeApiKey::class, 'exchange_api_key_id');
    }
}
