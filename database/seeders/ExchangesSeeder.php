<?php

namespace Database\Seeders;

use App\Models\Exchange;
use Illuminate\Database\Seeder;

class ExchangesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exchanges = [
            [
                'name' => 'Binance',
                'slug' => 'binance',
                'logo' => 'binance.png',
                'description' => 'Binance - одна з найбільших криптовалютних бірж у світі за обсягом торгів.',
                'is_active' => true,
                'supported_features' => [
                    'spot', 'futures', 'margin', 'websocket', 'rest_api', 'historical_data'
                ],
                'config' => [
                    'base_url' => 'https://api.binance.com',
                    'websocket_url' => 'wss://stream.binance.com:9443/ws',
                    'rate_limits' => [
                        'requests_per_minute' => 1200,
                        'orders_per_second' => 10,
                        'orders_per_day' => 200000
                    ]
                ]
            ],
            [
                'name' => 'Bybit',
                'slug' => 'bybit',
                'logo' => 'bybit.png',
                'description' => 'Bybit - криптовалютна біржа, що спеціалізується на ф\'ючерсній торгівлі.',
                'is_active' => true,
                'supported_features' => [
                    'spot', 'futures', 'margin', 'websocket', 'rest_api', 'historical_data'
                ],
                'config' => [
                    'base_url' => 'https://api.bybit.com',
                    'websocket_url' => 'wss://stream.bybit.com/spot/ws',
                    'rate_limits' => [
                        'requests_per_minute' => 600,
                        'orders_per_second' => 5,
                        'orders_per_day' => 100000
                    ]
                ]
            ],
            [
                'name' => 'WhiteBit',
                'slug' => 'whitebit',
                'logo' => 'whitebit.png',
                'description' => 'WhiteBit - Європейська криптовалютна біржа з фокусом на безпеці та зручності.',
                'is_active' => true,
                'supported_features' => [
                    'spot', 'websocket', 'rest_api', 'historical_data'
                ],
                'config' => [
                    'base_url' => 'https://whitebit.com/api',
                    'websocket_url' => 'wss://api.whitebit.com/ws',
                    'rate_limits' => [
                        'requests_per_minute' => 300,
                        'orders_per_second' => 3,
                        'orders_per_day' => 50000
                    ]
                ]
            ]
        ];

        foreach ($exchanges as $exchange) {
            Exchange::updateOrCreate(
                ['slug' => $exchange['slug']],
                $exchange
            );
        }
    }
}
