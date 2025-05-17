<?php

namespace Database\Seeders;

use App\Models\Cryptocurrency;
use Illuminate\Database\Seeder;

class CryptocurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cryptocurrencies = [
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'logo' => 'bitcoin.png',
                'description' => 'Bitcoin - перша і найбільша за ринковою капіталізацією криптовалюта.',
                'is_active' => true,
                'current_price' => 70000.00,
                'market_cap' => 1350000000000.00,
                'volume_24h' => 25000000000.00,
                'metadata' => [
                    'launch_date' => '2009-01-03',
                    'consensus_mechanism' => 'Proof of Work',
                    'max_supply' => 21000000,
                    'circulating_supply' => 19000000,
                ]
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'logo' => 'ethereum.png',
                'description' => 'Ethereum - платформа для створення децентралізованих додатків і смарт-контрактів.',
                'is_active' => true,
                'current_price' => 3500.00,
                'market_cap' => 420000000000.00,
                'volume_24h' => 15000000000.00,
                'metadata' => [
                    'launch_date' => '2015-07-30',
                    'consensus_mechanism' => 'Proof of Stake',
                    'max_supply' => null,
                    'circulating_supply' => 120000000,
                ]
            ],
            [
                'symbol' => 'USDT',
                'name' => 'Tether',
                'logo' => 'tether.png',
                'description' => 'Tether - стейблкойн, прив\'язаний до долара США.',
                'is_active' => true,
                'current_price' => 1.00,
                'market_cap' => 95000000000.00,
                'volume_24h' => 60000000000.00,
                'metadata' => [
                    'launch_date' => '2014-10-06',
                    'consensus_mechanism' => 'N/A',
                    'max_supply' => null,
                    'circulating_supply' => 95000000000,
                ]
            ],
            [
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'logo' => 'usdc.png',
                'description' => 'USD Coin - стейблкойн, прив\'язаний до долара США, створений консорціумом Circle і Coinbase.',
                'is_active' => true,
                'current_price' => 1.00,
                'market_cap' => 30000000000.00,
                'volume_24h' => 5000000000.00,
                'metadata' => [
                    'launch_date' => '2018-09-26',
                    'consensus_mechanism' => 'N/A',
                    'max_supply' => null,
                    'circulating_supply' => 30000000000,
                ]
            ],
            [
                'symbol' => 'BNB',
                'name' => 'Binance Coin',
                'logo' => 'bnb.png',
                'description' => 'Binance Coin - нативний токен біржі Binance і мережі BNB Chain.',
                'is_active' => true,
                'current_price' => 580.00,
                'market_cap' => 85000000000.00,
                'volume_24h' => 2000000000.00,
                'metadata' => [
                    'launch_date' => '2017-07-08',
                    'consensus_mechanism' => 'Proof of Staked Authority',
                    'max_supply' => 200000000,
                    'circulating_supply' => 150000000,
                ]
            ],
            [
                'symbol' => 'SOL',
                'name' => 'Solana',
                'logo' => 'solana.png',
                'description' => 'Solana - високопродуктивна блокчейн-платформа з низькими комісіями та високою швидкістю транзакцій.',
                'is_active' => true,
                'current_price' => 140.00,
                'market_cap' => 60000000000.00,
                'volume_24h' => 3000000000.00,
                'metadata' => [
                    'launch_date' => '2020-03-16',
                    'consensus_mechanism' => 'Proof of History + Proof of Stake',
                    'max_supply' => null,
                    'circulating_supply' => 430000000,
                ]
            ],
        ];

        foreach ($cryptocurrencies as $crypto) {
            Cryptocurrency::updateOrCreate(
                ['symbol' => $crypto['symbol']],
                $crypto
            );
        }
    }
}
