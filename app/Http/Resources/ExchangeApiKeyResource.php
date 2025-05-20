<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'exchange_id' => $this->exchange_id,
            'exchange' => [
                'id' => $this->whenLoaded('exchange', fn() => $this->exchange->id),
                'name' => $this->whenLoaded('exchange', fn() => $this->exchange->name),
                'slug' => $this->whenLoaded('exchange', fn() => $this->exchange->slug),
                'logo' => $this->whenLoaded('exchange', fn() => $this->exchange->logo),
            ],
            'name' => $this->name,
            'api_key' => $this->maskSensitiveData($this->api_key),
            'has_api_secret' => !empty($this->api_secret),
            'has_passphrase' => !empty($this->passphrase),
            'is_test_net' => $this->is_test_net,
            'trading_enabled' => $this->trading_enabled,
            'is_active' => $this->is_active,
            'permissions' => $this->permissions,
            'permissions_data' => $this->permissions_data,
            'last_used_at' => $this->last_used_at,
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function maskSensitiveData(string $data): string
    {
        if (strlen($data) <= 8) {
            return str_repeat('*', strlen($data));
        }

        return substr($data, 0, 4) . str_repeat('*', strlen($data) - 8) . substr($data, -4);
    }
}
