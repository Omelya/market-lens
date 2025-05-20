<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<array-key, mixed>|null $details
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog ofAction(string $action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog ofEntityId(int $entityId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog ofEntityType(string $entityType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog recent(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserActivityLog whereUserId($value)
 * @mixin \Eloquent
 */
class UserActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeOfEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeOfEntityId($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
