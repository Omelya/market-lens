<?php

namespace App\Console\Commands;

use App\Jobs\FetchLastHistoricalDataJob;
use App\Models\TradingPair;
use App\Services\MarketData\MarketDataService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class LoadLastHistoricalDataCommand extends Command
{

    protected $signature = 'load:last-historical-data
        {--timeframe=1m : Таймфрейм (1m, 5m, 15m, 30m, 1h, 4h, 1d)}';

    protected $description = 'Завантаження останніх історичних даних із бірж';

    protected MarketDataService $marketDataService;

    public function __construct(MarketDataService $marketDataService)
    {
        parent::__construct();
        $this->marketDataService = $marketDataService;
    }

    public function handle(): void
    {
        $timeframe = $this->option('timeframe');

        $tradingPairs = $this->getTradingPairs();

        foreach ($tradingPairs as $tradingPair) {
            dispatch(new FetchLastHistoricalDataJob($tradingPair->id, $timeframe, 1))
                ->onQueue('market-data');
        }

        $this->info("Last historical data from $timeframe loaded");
    }

    private function getTradingPairs(): Collection
    {
        return TradingPair
            ::with('exchange')
            ->where('is_active', true)
            ->get();
    }
}
