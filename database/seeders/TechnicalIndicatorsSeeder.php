<?php

namespace Database\Seeders;

use App\Models\TechnicalIndicator;
use Illuminate\Database\Seeder;

class TechnicalIndicatorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $indicators = [
            // Трендові індикатори
            [
                'name' => 'SMA',
                'category' => TechnicalIndicator::CATEGORY_TREND,
                'description' => 'Simple Moving Average (проста ковзна середня) - індикатор, що показує середнє значення ціни за певний період.',
                'default_parameters' => [
                    'length' => 20,
                    'source' => 'close'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'EMA',
                'category' => TechnicalIndicator::CATEGORY_TREND,
                'description' => 'Exponential Moving Average (експоненціальна ковзна середня) - надає більшої ваги останнім цінам.',
                'default_parameters' => [
                    'length' => 20,
                    'source' => 'close'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'MACD',
                'category' => TechnicalIndicator::CATEGORY_TREND,
                'description' => 'Moving Average Convergence Divergence - показує різницю між двома експоненціальними ковзними середніми.',
                'default_parameters' => [
                    'fast_length' => 12,
                    'slow_length' => 26,
                    'signal_length' => 9,
                    'source' => 'close'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'ADX',
                'category' => TechnicalIndicator::CATEGORY_TREND,
                'description' => 'Average Directional Index - вимірює силу тренду.',
                'default_parameters' => [
                    'length' => 14
                ],
                'is_active' => true,
            ],

            // Осцилятори
            [
                'name' => 'RSI',
                'category' => TechnicalIndicator::CATEGORY_OSCILLATOR,
                'description' => 'Relative Strength Index (індекс відносної сили) - вимірює швидкість і зміну цінових рухів.',
                'default_parameters' => [
                    'length' => 14,
                    'source' => 'close',
                    'overbought' => 70,
                    'oversold' => 30
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Stochastic',
                'category' => TechnicalIndicator::CATEGORY_OSCILLATOR,
                'description' => 'Stochastic Oscillator - порівнює ціну закриття з діапазоном цін за певний період.',
                'default_parameters' => [
                    'k_length' => 14,
                    'd_length' => 3,
                    'smooth' => 3,
                    'overbought' => 80,
                    'oversold' => 20
                ],
                'is_active' => true,
            ],
            [
                'name' => 'CCI',
                'category' => TechnicalIndicator::CATEGORY_OSCILLATOR,
                'description' => 'Commodity Channel Index - вимірює відхилення ціни від її середнього значення.',
                'default_parameters' => [
                    'length' => 20,
                    'constant' => 0.015
                ],
                'is_active' => true,
            ],

            // Об'ємні індикатори
            [
                'name' => 'OBV',
                'category' => TechnicalIndicator::CATEGORY_VOLUME,
                'description' => 'On-Balance Volume - пов\'язує об\'єм з ціновими змінами.',
                'default_parameters' => [],
                'is_active' => true,
            ],
            [
                'name' => 'CMF',
                'category' => TechnicalIndicator::CATEGORY_VOLUME,
                'description' => 'Chaikin Money Flow - вимірює кількість грошового потоку за певний період.',
                'default_parameters' => [
                    'length' => 20
                ],
                'is_active' => true,
            ],

            // Індикатори волатильності
            [
                'name' => 'Bollinger Bands',
                'category' => TechnicalIndicator::CATEGORY_VOLATILITY,
                'description' => 'Bollinger Bands - вимірює волатильність ринку.',
                'default_parameters' => [
                    'length' => 20,
                    'std_dev' => 2,
                    'source' => 'close'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'ATR',
                'category' => TechnicalIndicator::CATEGORY_VOLATILITY,
                'description' => 'Average True Range - вимірює волатильність ринку.',
                'default_parameters' => [
                    'length' => 14
                ],
                'is_active' => true,
            ],

            // Індикатори підтримки/опору
            [
                'name' => 'Fibonacci Retracement',
                'category' => TechnicalIndicator::CATEGORY_SUPPORT_RESISTANCE,
                'description' => 'Fibonacci Retracement - використовує числа Фібоначчі для визначення потенційних рівнів підтримки та опору.',
                'default_parameters' => [
                    'levels' => [0.236, 0.382, 0.5, 0.618, 0.786]
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pivot Points',
                'category' => TechnicalIndicator::CATEGORY_SUPPORT_RESISTANCE,
                'description' => 'Pivot Points - використовується для визначення загальних рівнів тренду ринку.',
                'default_parameters' => [
                    'type' => 'standard' // standard, fibonacci, camarilla, woodie
                ],
                'is_active' => true,
            ],
        ];

        foreach ($indicators as $indicator) {
            TechnicalIndicator::updateOrCreate(
                ['name' => $indicator['name']],
                $indicator
            );
        }
    }
}
