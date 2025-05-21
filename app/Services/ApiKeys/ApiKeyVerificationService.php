<?php

namespace App\Services\ApiKeys;

use App\Interfaces\ExchangeInterface;
use App\Models\ExchangeApiKey;
use App\Services\Exchanges\ExchangeFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class ApiKeyVerificationService
{
    /**
     * Перевірити API ключ шляхом виконання різних тестових операцій.
     */
    public function verifyApiKey(int $apiKeyId): array
    {
        $apiKey = ExchangeApiKey::findOrFail($apiKeyId);

        $results = [
            'status' => 'success',
            'message' => 'API ключ успішно перевірено',
            'permissions' => [],
            'verified_at' => now(),
            'tests' => [],
        ];

        try {
            $exchange = ExchangeFactory::createWithApiKey($apiKeyId);

            $balanceResult = $this->testBalance($exchange);
            $results['tests']['balance'] = $balanceResult;

            $ordersResult = $this->testOrders($exchange);
            $results['tests']['orders'] = $ordersResult;

            if ($apiKey->trading_enabled) {
                $tradingResult = $this->testTrading($exchange, $apiKey);
                $results['tests']['trading'] = $tradingResult;
            }

            if ($apiKey->exchange->supportsFeature('futures')) {
                $futuresResult = $this->testFutures($exchange);
                $results['tests']['futures'] = $futuresResult;
            }

            $this->saveVerificationResults($apiKey, $results);
        } catch (Exception $e) {
            Log::error('API key verification error', [
                'api_key_id' => $apiKeyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Помилка перевірки API ключа: ' . $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Тестує доступ до балансу.
     */
    private function testBalance(ExchangeInterface $exchange): array
    {
        try {
            $balance = $exchange->getBalance();

            return [
                'status' => 'success',
                'message' => 'Доступ до балансу підтверджено',
                'permission' => 'balance_read',
                'data' => $this->summarizeBalance($balance)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Немає доступу до балансу: ' . $e->getMessage(),
                'permission' => 'balance_read',
                'data' => null
            ];
        }
    }

    /**
     * Тестує доступ до ордерів.
     */
    private function testOrders(ExchangeInterface $exchange): array
    {
        try {
            $orders = $exchange->getOpenOrders();
            return [
                'status' => 'success',
                'message' => 'Доступ до ордерів підтверджено',
                'permission' => 'orders_read',
                'data' => [
                    'orders_count' => count($orders)
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Немає доступу до ордерів: ' . $e->getMessage(),
                'permission' => 'orders_read',
                'data' => null
            ];
        }
    }

    /**
     * Тестує можливість торгівлі.
     */
    private function testTrading($exchange, ExchangeApiKey $apiKey): array
    {
        $tradingPair = $apiKey
            ->exchange
            ->tradingPairs()
            ->where('is_active', true)
            ->first();

        if (!$tradingPair) {
            return [
                'status' => 'skipped',
                'message' => 'Немає активних торгових пар для тестування',
                'permission' => 'trading',
                'data' => null
            ];
        }

        try {
            $symbol = $tradingPair->symbol;
            $marketInfo = $exchange->getMarketLimits($symbol);

            return [
                'status' => 'success',
                'message' => 'Доступ до торгівлі підтверджено',
                'permission' => 'trading',
                'data' => [
                    'market_info' => $marketInfo
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Немає доступу до торгівлі: ' . $e->getMessage(),
                'permission' => 'trading',
                'data' => null
            ];
        }
    }

    /**
     * Тестує доступ до фючерсів.
     */
    private function testFutures(ExchangeInterface $exchange): array
    {
        try {
            $hasAccess = method_exists($exchange, 'getFuturesBalance');

            if ($hasAccess) {
                $futuresBalance = $exchange->getFuturesBalance();

                return [
                    'status' => 'success',
                    'message' => 'Доступ до фючерсів підтверджено',
                    'permission' => 'futures',
                    'data' => [
                        'futures_balance' => $futuresBalance
                    ]
                ];
            }

            return [
                'status' => 'skipped',
                'message' => 'Тестування фючерсів пропущено для цієї біржі',
                'permission' => 'futures',
                'data' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Немає доступу до фючерсів: ' . $e->getMessage(),
                'permission' => 'futures',
                'data' => null
            ];
        }
    }

    /**
     * Збереження результатів перевірки API ключа.
     */
    private function saveVerificationResults(ExchangeApiKey $apiKey, array $results): void
    {
        $permissions = [];

        foreach ($results['tests'] as $testName => $testResult) {
            if ($testResult['status'] === 'success' && isset($testResult['permission'])) {
                $permissions[] = $testResult['permission'];
            }
        }

        $apiKey->update([
            'verified_at' => now(),
            'permissions' => $permissions,
            'permissions_data' => [
                'last_verified_at' => now()->toIso8601String(),
                'test_results' => $results['tests']
            ]
        ]);
    }

    /**
     * Узагальнення даних балансу.
     */
    private function summarizeBalance(array $balanceResult): array
    {
        $total = 0;
        $mainCurrencies = [];

        foreach ($balanceResult['total'] as $currency => $amount) {
            if ($amount > 0) {
                $mainCurrencies[$currency] = $amount;
            }

            if ($currency === 'USDT' || $currency === 'USDC' || $currency === 'USD') {
                $total += $amount;
            }
        }

        return [
            'total_balance' => $total,
            'main_currencies' => array_slice($mainCurrencies, 0, 5, true)
        ];
    }
}
