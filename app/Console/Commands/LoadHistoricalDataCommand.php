<?php

namespace App\Console\Commands;

use App\Models\TradingPair;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class LoadHistoricalDataCommand extends Command
{
    private const TO_MILLISECONDS = 1000;

    protected $signature = 'load:historical-data
                           {exchange? : Slug біржі (all для всіх)}
                           {--pair= : ID торгової пари або символ (all для всіх)}
                           {--start-date= : Дата початку вибірки (дата в форматі Y-m-d)}
                           {--timeframe= : Таймфрейм для вибірки свічок (all для всіх)}
                           {--limit=1000 : Кількість останніх свічок для вибірки}';

    protected $description = 'Завантаження історичних даних із бірж';

    protected MarketDataService $marketDataService;

    public function __construct(MarketDataService $marketDataService)
    {
        parent::__construct();
        $this->marketDataService = $marketDataService;
    }

    public function handle(): void
    {
        $exchange = $this->argument('exchange') ?? 'all';
        $pair = $this->option('pair') ?? 'all';
        $time = $this->option('start-date') ?? Carbon::now()->subDay()->format('Y-m-d');
        $timeframe = $this->option('timeframe') ?? 'all';
        $limit = $this->option('limit');

        $fromTime = Carbon::createFromFormat('Y-m-d', $time)?->unix() * self::TO_MILLISECONDS;

        $tradingPairs = $this->getTradingPairs($exchange, $pair);

        $totalPairs = count($tradingPairs);
        $this->info("Розпочато завантаження історичних даних для {$totalPairs} торгових пар");

        $successCount = 0;
        $errorCount = 0;

        foreach ($tradingPairs as $tradingPair) {
            try {
                $this
                    ->marketDataService
                    ->fetchAndSaveHistoricalData($tradingPair->id, $timeframe, $fromTime, $limit);

                $this->info("Завантажено історичні дані для {$tradingPair->symbol}");

                $successCount++;
            } catch (\Exception $e) {

                $this->error("Помилка завантаження історичних даних для {$tradingPair->symbol}: {$e->getMessage()}");

                $errorCount++;
            }

            $this->info("Завершено! Успішно: {$successCount}, Помилок: {$errorCount}");
        }
    }

    private function getTradingPairs(string $exchange, string $pair): Collection
    {
        $query = TradingPair
            ::with('exchange')
            ->where('is_active', true);

        if ($exchange !== 'all') {
            $query->whereHas('exchange', function ($q) use ($exchange) {
                $q->where('slug', $exchange);
            });
        }

        if (is_numeric($pair)) {
            $query->where('id', $pair);
        } else if ($pair !== 'all') {
            $query->where('symbol', $pair);
        }

        return $query->get();
    }
}
