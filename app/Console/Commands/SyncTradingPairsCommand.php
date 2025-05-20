<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\TradingPair;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Console\Command;

class SyncTradingPairsCommand extends Command
{
    protected $signature = 'trading-pairs:sync
                            {exchange? : Slug біржі (all для всіх)}
                            {--limit=100 : Кількість пар для синхронізації}
                            {--active-only : Синхронізувати тільки активні пари}';

    protected $description = 'Синхронізувати торгові пари з біржами';

    public function handle(): void
    {
        $exchangeSlug = $this->argument('exchange');
        $limit = (int) $this->option('limit');
        $activeOnly = $this->option('active-only');

        if ($exchangeSlug && $exchangeSlug !== 'all') {
            $this->syncExchangePairs($exchangeSlug, $limit, $activeOnly);
        } else {
            $exchanges = Exchange::where('is_active', true)->get();

            $this->info("Синхронізація торгових пар для {$exchanges->count()} бірж");

            foreach ($exchanges as $exchange) {
                $this->syncExchangePairs($exchange->slug, $limit, $activeOnly);
            }
        }
    }

    protected function syncExchangePairs(string $exchangeSlug, int $limit, bool $activeOnly): void
    {
        $this->info("Синхронізація пар для біржі '{$exchangeSlug}'");

        try {
            $exchange = Exchange::where('slug', $exchangeSlug)->first();

            if (!$exchange) {
                $this->error("Біржа з slug '{$exchangeSlug}' не знайдена");
                return;
            }

            $this->info("Отримання даних з API біржі '{$exchangeSlug}'");

            $exchangeApi = ExchangeFactory::createPublic($exchangeSlug);
            $markets = $exchangeApi->getMarkets();

            if (empty($markets)) {
                $this->error("Не вдалося отримати торгові пари з біржі '{$exchangeSlug}'");
                return;
            }

            $this->info("Отримано " . count($markets) . " пар з біржі");

            $count = 0;
            $newCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            if (isset(array_values($markets)[0]['info']['volume'])) {
                usort($markets, function($a, $b) {
                    return ($b['info']['volume'] ?? 0) <=> ($a['info']['volume'] ?? 0);
                });
            }

            foreach ($markets as $symbol => $market) {
                if ($count >= $limit) {
                    break;
                }

                if ($activeOnly && !($market['active'] ?? false)) {
                    $skippedCount++;
                    continue;
                }

                $baseCurrency = $market['base'] ?? '';
                $quoteCurrency = $market['quote'] ?? '';

                if (empty($baseCurrency) || empty($quoteCurrency)) {
                    $this->warn("Пропуск пари '{$symbol}': відсутня базова або котирувальна валюта");
                    $skippedCount++;
                    continue;
                }

                $tradingPair = TradingPair::where('exchange_id', $exchange->id)
                    ->where('symbol', $symbol)
                    ->first();

                if ($tradingPair) {
                    $tradingPair->update([
                        'min_order_size' => $market['limits']['amount']['min'] ?? $tradingPair->min_order_size,
                        'max_order_size' => $market['limits']['amount']['max'] ?? $tradingPair->max_order_size,
                        'price_precision' => $market['precision']['price'] ?? $tradingPair->price_precision,
                        'size_precision' => $market['precision']['amount'] ?? $tradingPair->size_precision,
                        'is_active' => $market['active'] ?? $tradingPair->is_active,
                    ]);

                    $updatedCount++;
                } else {
                    TradingPair::create([
                        'exchange_id' => $exchange->id,
                        'symbol' => $symbol,
                        'base_currency' => $baseCurrency,
                        'quote_currency' => $quoteCurrency,
                        'min_order_size' => $market['limits']['amount']['min'] ?? 0,
                        'max_order_size' => $market['limits']['amount']['max'] ?? null,
                        'price_precision' => $market['precision']['price'] ?? 8,
                        'size_precision' => $market['precision']['amount'] ?? 8,
                        'maker_fee' => $market['maker'] ?? 0.001,
                        'taker_fee' => $market['taker'] ?? 0.001,
                        'is_active' => $market['active'] ?? true,
                    ]);

                    $newCount++;
                }

                $count++;
            }

            $this->info("Синхронізацію завершено для біржі '{$exchangeSlug}'");
            $this->info("Додано: {$newCount}, Оновлено: {$updatedCount}, Пропущено: {$skippedCount}");

        } catch (\Exception $e) {
            $this->error("Помилка під час синхронізації пар для біржі '{$exchangeSlug}': " . $e->getMessage());
        }
    }
}
