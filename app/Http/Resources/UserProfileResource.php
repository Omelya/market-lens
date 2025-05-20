<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'timezone' => $this->timezone,
            'notification_preferences' => $this->notification_preferences,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pending_email_change' => $this->whenLoaded('emailVerificationToken'),
            'api_keys_count' => $this->whenCounted('exchangeApiKeys'),
            'exchange_api_keys' => ExchangeApiKeyResource::collection($this->whenLoaded('exchangeApiKeys')),
            'recent_activity' => UserActivityLogResource::collection($this->whenLoaded('recentActivity')),
        ];
    }
}
