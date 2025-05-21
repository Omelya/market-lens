<?php

namespace App\Jobs;

use App\Services\MarketData\MarketDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchLastHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected MarketDataService $marketDataService;

    public function __construct(
        protected int $pairId,
        protected string $timeframe,
        protected int $limit,
    ) {
        $this->marketDataService = new MarketDataService();
    }

    public function handle(): void
    {
        $this
            ->marketDataService
            ->fetchAndSaveHistoricalData($this->pairId, $this->timeframe, null, $this->limit);


        usleep(300000);
    }
}
