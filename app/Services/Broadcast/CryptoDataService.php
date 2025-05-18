<?php

namespace App\Services\Broadcast;

use App\Events\CryptoPriceUpdated;
use App\Events\CryptoOrderBookUpdated;
use App\Events\CryptoKlineUpdated;
use App\Models\TradingPair;
use App\Services\Exchanges\ExchangeRegistry;
use Illuminate\Support\Facades\Log;

class CryptoDataService
{
    /**
     * Отримати та транслювати актуальні ціни для торгової пари.
     */
    public function broadcastPrice(string $exchangeSlug, string $symbol): bool
    {
        try {
            $exchange = ExchangeRegistry::getPublic($exchangeSlug);

            $ticker = $exchange->getTicker($symbol);

            event(new CryptoPriceUpdated($exchangeSlug, $symbol, $ticker));

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast price for {$exchangeSlug}:{$symbol}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Отримати та транслювати дані книги ордерів для торгової пари.
     */
    public function broadcastOrderBook(string $exchangeSlug, string $symbol, int $depth = 20): bool
    {
        try {
            $exchange = ExchangeRegistry::getPublic($exchangeSlug);

            $orderBook = $exchange->getOrderBook($symbol, $depth);

            event(new CryptoOrderBookUpdated($exchangeSlug, $symbol, $orderBook));

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast order book for {$exchangeSlug}:{$symbol}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Отримати та транслювати K-лінії для торгової пари.
     */
    public function broadcastKlines(string $exchangeSlug, string $symbol, string $timeframe, int $limit = 100): bool
    {
        try {
            $exchange = ExchangeRegistry::getPublic($exchangeSlug);

            $klines = $exchange->getOHLCV($symbol, $timeframe, null, $limit);

            event(new CryptoKlineUpdated($exchangeSlug, $symbol, $timeframe, $klines));

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast klines for {$exchangeSlug}:{$symbol}:{$timeframe}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Транслювати дані для всіх активних торгових пар.
     */
    public function broadcastAllActivePairs(): int
    {
        $count = 0;

        $tradingPairs = TradingPair::where('is_active', true)
            ->with('exchange')
            ->get();

        foreach ($tradingPairs as $pair) {
            try {
                $exchangeSlug = $pair->exchange->slug;
                $symbol = $pair->symbol;

                $this->broadcastPrice($exchangeSlug, $symbol);

                $this->broadcastOrderBook($exchangeSlug, $symbol);

                $count++;
            } catch (\Exception $e) {
                Log::error("Error broadcasting data for pair {$pair->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }
}
