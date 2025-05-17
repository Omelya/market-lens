<?php

namespace Database\Seeders;

use App\Models\Exchange;
use App\Models\TradingPair;
use Illuminate\Database\Seeder;

class TradingPairsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Отримуємо ID бірж
        $binanceId = Exchange::where('slug', 'binance')->first()?->id;
        $bybitId = Exchange::where('slug', 'bybit')->first()?->id;
        $whitebitId = Exchange::where('slug', 'whitebit')->first()?->id;

        // Перевіряємо, чи існують біржі
        if (!$binanceId || !$bybitId || !$whitebitId) {
            $this->command->error('Exchanges not found. Please seed exchanges first.');
            return;
        }

        $tradingPairs = [
            // Binance Trading Pairs
            [
                'exchange_id' => $binanceId,
                'symbol' => 'BTCUSDT',
                'base_currency' => 'BTC',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.00001,
                'max_order_size' => 1000.0,
                'price_precision' => 0.01,
                'size_precision' => 0.00001,
                'maker_fee' => 0.001,
                'taker_fee' => 0.001,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => true,
                    'max_leverage' => 125,
                    'order_types' => ['LIMIT', 'MARKET', 'STOP_LOSS', 'TAKE_PROFIT']
                ]
            ],
            [
                'exchange_id' => $binanceId,
                'symbol' => 'ETHUSDT',
                'base_currency' => 'ETH',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.0001,
                'max_order_size' => 10000.0,
                'price_precision' => 0.01,
                'size_precision' => 0.0001,
                'maker_fee' => 0.001,
                'taker_fee' => 0.001,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => true,
                    'max_leverage' => 100,
                    'order_types' => ['LIMIT', 'MARKET', 'STOP_LOSS', 'TAKE_PROFIT']
                ]
            ],
            [
                'exchange_id' => $binanceId,
                'symbol' => 'SOLUSDT',
                'base_currency' => 'SOL',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.01,
                'max_order_size' => 50000.0,
                'price_precision' => 0.001,
                'size_precision' => 0.01,
                'maker_fee' => 0.001,
                'taker_fee' => 0.001,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => true,
                    'max_leverage' => 20,
                    'order_types' => ['LIMIT', 'MARKET', 'STOP_LOSS', 'TAKE_PROFIT']
                ]
            ],

            // Bybit Trading Pairs
            [
                'exchange_id' => $bybitId,
                'symbol' => 'BTCUSDT',
                'base_currency' => 'BTC',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.0001,
                'max_order_size' => 1000.0,
                'price_precision' => 0.1,
                'size_precision' => 0.0001,
                'maker_fee' => 0.0001,
                'taker_fee' => 0.0006,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => true,
                    'max_leverage' => 100,
                    'order_types' => ['LIMIT', 'MARKET', 'CONDITIONAL']
                ]
            ],
            [
                'exchange_id' => $bybitId,
                'symbol' => 'ETHUSDT',
                'base_currency' => 'ETH',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.001,
                'max_order_size' => 10000.0,
                'price_precision' => 0.01,
                'size_precision' => 0.001,
                'maker_fee' => 0.0001,
                'taker_fee' => 0.0006,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => true,
                    'max_leverage' => 100,
                    'order_types' => ['LIMIT', 'MARKET', 'CONDITIONAL']
                ]
            ],

            // WhiteBit Trading Pairs
            [
                'exchange_id' => $whitebitId,
                'symbol' => 'BTC_USDT',
                'base_currency' => 'BTC',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.0001,
                'max_order_size' => 100.0,
                'price_precision' => 0.01,
                'size_precision' => 0.0001,
                'maker_fee' => 0.001,
                'taker_fee' => 0.001,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => false,
                    'order_types' => ['LIMIT', 'MARKET']
                ]
            ],
            [
                'exchange_id' => $whitebitId,
                'symbol' => 'ETH_USDT',
                'base_currency' => 'ETH',
                'quote_currency' => 'USDT',
                'min_order_size' => 0.001,
                'max_order_size' => 1000.0,
                'price_precision' => 0.01,
                'size_precision' => 0.001,
                'maker_fee' => 0.001,
                'taker_fee' => 0.001,
                'is_active' => true,
                'metadata' => [
                    'leverage_available' => false,
                    'order_types' => ['LIMIT', 'MARKET']
                ]
            ],
        ];

        foreach ($tradingPairs as $pair) {
            TradingPair::updateOrCreate(
                [
                    'exchange_id' => $pair['exchange_id'],
                    'symbol' => $pair['symbol']
                ],
                $pair
            );
        }
    }
}
