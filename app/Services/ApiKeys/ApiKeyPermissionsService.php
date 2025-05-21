<?php

namespace App\Services\ApiKeys;

use App\Models\ExchangeApiKey;

class ApiKeyPermissionsService
{
    public const PERMISSION_READ_BALANCE = 'balance_read';

    public const PERMISSION_READ_ORDERS = 'orders_read';

    public const PERMISSION_TRADING = 'trading';

    public const PERMISSION_FUTURES = 'futures';

    public const PERMISSION_MARGIN = 'margin';

    /**
     * Отримує доступні права для конкретної біржі.
     */
    public function getAvailablePermissions(string $exchangeSlug): array
    {
        $basePermissions = [
            self::PERMISSION_READ_BALANCE => [
                'name' => 'Перегляд балансу',
                'description' => 'Дозволяє читати баланс рахунку',
                'is_required' => true
            ],
            self::PERMISSION_READ_ORDERS => [
                'name' => 'Перегляд ордерів',
                'description' => 'Дозволяє читати історію та поточні ордери',
                'is_required' => false
            ],
            self::PERMISSION_TRADING => [
                'name' => 'Торгівля',
                'description' => 'Дозволяє створювати та скасовувати ордери',
                'is_required' => false
            ]
        ];

        switch ($exchangeSlug) {
            case 'binance':
            case 'bybit':
                $basePermissions[self::PERMISSION_FUTURES] = [
                    'name' => 'Ф\'ючерси',
                    'description' => 'Дозволяє торгувати на ф\'ючерсному ринку',
                    'is_required' => false
                ];

                $basePermissions[self::PERMISSION_MARGIN] = [
                    'name' => 'Маржинальна торгівля',
                    'description' => 'Дозволяє використовувати маржинальну торгівлю',
                    'is_required' => false
                ];
                break;
        }

        return $basePermissions;
    }

    /**
     * Встановлює дозволи для API ключа.
     */
    public function setPermissions(ExchangeApiKey $apiKey, array $permissions): bool
    {
        $availablePermissions = array_keys($this->getAvailablePermissions($apiKey->exchange->slug));

        $validPermissions = array_intersect($permissions, $availablePermissions);

        $requiredPermissions = array_keys(array_filter(
            $this->getAvailablePermissions($apiKey->exchange->slug),
            fn($perm) => $perm['is_required']
        ));

        $finalPermissions = array_unique(array_merge($validPermissions, $requiredPermissions));

        return $apiKey->update(['permissions' => $finalPermissions]);
    }
}
