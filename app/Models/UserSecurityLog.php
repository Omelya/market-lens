<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'details',
        'is_suspicious',
        'device_id',
        'session_id'
    ];

    protected $casts = [
        'details' => 'array',
        'is_suspicious' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeSuspicious($query, bool $suspicious = true)
    {
        return $query->where('is_suspicious', $suspicious);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeFromSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeFromDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }
}
