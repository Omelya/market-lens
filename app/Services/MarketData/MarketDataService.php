<?php

namespace App\Services\MarketData;

use App\Models\Exchange;
use App\Models\HistoricalData;
use App\Models\TradingPair;
use App\Services\Exchanges\ExchangeRegistry;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    /**
     * Отримати та зберегти історичні дані для торгової пари.
     *
     * @param int $tradingPairId ID торгової пари.
     * @param string $timeframe Таймфрейм (1m, 5m, 15m, 30m, 1h, 4h, 1d).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість свічок.
     * @return array
     */
    public function fetchAndSaveHistoricalData(int $tradingPairId, string $timeframe, ?int $since = null, ?int $limit = 1000): array
    {
        try {
            $tradingPair = TradingPair::with('exchange')->findOrFail($tradingPairId);

            $exchange = ExchangeRegistry::getPublic($tradingPair->exchange->slug);

            $ohlcvData = $exchange->getOHLCV($tradingPair->symbol, $timeframe, $since, $limit);

            $savedCount = 0;
            $duplicateCount = 0;

            foreach ($ohlcvData as $candle) {
                $exists = HistoricalData
                    ::where('trading_pair_id', $tradingPairId)
                    ->where('timeframe', $timeframe)
                    ->where('timestamp', date('Y-m-d H:i:s', $candle['timestamp'] / 1000))
                    ->exists();

                if (!$exists) {
                    HistoricalData::create([
                        'trading_pair_id' => $tradingPairId,
                        'timeframe' => $timeframe,
                        'timestamp' => date('Y-m-d H:i:s', $candle['timestamp'] / 1000),
                        'open' => $candle['open'],
                        'high' => $candle['high'],
                        'low' => $candle['low'],
                        'close' => $candle['close'],
                        'volume' => $candle['volume'],
                    ]);

                    $savedCount++;
                } else {
                    $duplicateCount++;
                }
            }

            return [
                'status' => 'success',
                'message' => "Saved {$savedCount} candles, {$duplicateCount} duplicates skipped",
                'trading_pair' => $tradingPair->symbol,
                'timeframe' => $timeframe,
                'data_count' => count($ohlcvData),
                'saved_count' => $savedCount,
                'duplicate_count' => $duplicateCount,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch historical data', [
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
            ];
        }
    }

    /**
     * Отримати останні історичні дані для торгової пари.
     *
     * @param int $tradingPairId ID торгової пари.
     * @param string $timeframe Таймфрейм (1m, 5m, 15m, 30m, 1h, 4h, 1d).
     * @param int $limit Кількість свічок.
     * @return array
     */
    public function getLatestHistoricalData(int $tradingPairId, string $timeframe, int $limit = 100): array
    {
        try {
            $historicalData = HistoricalData
                ::where('trading_pair_id', $tradingPairId)
                ->where('timeframe', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->sortBy('timestamp')
                ->values()
                ->toArray();

            return [
                'status' => 'success',
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'data_count' => count($historicalData),
                'data' => $historicalData,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get latest historical data', [
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
            ];
        }
    }

    /**
     * Отримати та зберегти останні тікери для всіх торгових пар.
     *
     * @return array
     */
    public function fetchAndSaveAllTickers(): array
    {
        try {
            $results = [];

            $exchanges = Exchange
                ::select(['slug'])
                ->get();

            foreach ($exchanges as $exchangeSlug) {
                $exchange = ExchangeRegistry::getPublic($exchangeSlug);
                $tickers = $exchange->getTickers();

                $tradingPairs = TradingPair
                    ::whereHas('exchange', function ($query) use ($exchangeSlug) {
                        $query->where('slug', $exchangeSlug);
                    })
                    ->get();

                foreach ($tradingPairs as $tradingPair) {
                    if (isset($tickers[$tradingPair->symbol])) {
                        $ticker = $tickers[$tradingPair->symbol];

                        $tradingPair->update([
                            'last_price' => $ticker['last'] ?? $ticker['close'] ?? null,
                            'bid_price' => $ticker['bid'] ?? null,
                            'ask_price' => $ticker['ask'] ?? null,
                            'volume_24h' => $ticker['baseVolume'] ?? null,
                            'price_change_24h' => $ticker['change'] ?? null,
                            'price_change_percentage_24h' => $ticker['percentage'] ?? null,
                            'last_updated_at' => now(),
                        ]);

                        $results[] = [
                            'exchange' => $exchangeSlug,
                            'symbol' => $tradingPair->symbol,
                            'last_price' => $ticker['last'] ?? $ticker['close'] ?? null,
                        ];
                    }
                }
            }

            return [
                'status' => 'success',
                'message' => 'All tickers updated',
                'data_count' => count($results),
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch and save tickers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Отримати глибину ринку (ордербук) для торгової пари.
     *
     * @param int $tradingPairId ID торгової пари.
     * @param int $limit Глибина ордербуку.
     * @return array
     */
    public function getOrderBook(int $tradingPairId, int $limit = 100): array
    {
        try {
            $tradingPair = TradingPair::with('exchange')->findOrFail($tradingPairId);

            $exchange = ExchangeRegistry::getPublic($tradingPair->exchange->slug);

            $orderBook = $exchange->getOrderBook($tradingPair->symbol, $limit);

            return [
                'status' => 'success',
                'trading_pair' => $tradingPair->symbol,
                'timestamp' => $orderBook['timestamp'],
                'datetime' => $orderBook['datetime'],
                'bids_count' => count($orderBook['bids']),
                'asks_count' => count($orderBook['asks']),
                'bids' => $orderBook['bids'],
                'asks' => $orderBook['asks'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get order book', [
                'trading_pair_id' => $tradingPairId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trading_pair_id' => $tradingPairId,
            ];
        }
    }

    /**
     * Отримати всі доступні таймфрейми для біржі.
     *
     * @param string $exchangeSlug Slug біржі.
     * @return array
     */
    public function getAvailableTimeframes(string $exchangeSlug): array
    {
        try {
            $exchange = ExchangeRegistry::getPublic($exchangeSlug);

            $timeframes = $exchange->getTimeframes();

            return [
                'status' => 'success',
                'exchange' => $exchangeSlug,
                'timeframes' => $timeframes,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get available timeframes', [
                'exchange' => $exchangeSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'exchange' => $exchangeSlug,
            ];
        }
    }
}
