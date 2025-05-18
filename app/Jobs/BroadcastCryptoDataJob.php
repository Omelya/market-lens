<?php

namespace App\Jobs;

use App\Services\Broadcast\CryptoDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BroadcastCryptoDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $exchange;
    protected ?string $symbol;

    public function __construct(?string $exchange = null, ?string $symbol = null)
    {
        $this->exchange = $exchange;
        $this->symbol = $symbol;
    }

    public function handle(CryptoDataService $dataService): void
    {
        try {
            if ($this->exchange && $this->exchange !== 'all' && $this->symbol && $this->symbol !== 'all') {
                Log::info("Broadcasting data for {$this->exchange}:{$this->symbol}");

                $dataService->broadcastPrice($this->exchange, $this->symbol);
                $dataService->broadcastOrderBook($this->exchange, $this->symbol);
            } else {
                $count = $dataService->broadcastAllActivePairs();

                Log::info("Broadcasted data for {$count} trading pairs");
            }

            self::dispatch($this->exchange, $this->symbol)
                ->delay(now()->addSeconds(5))
                ->onQueue('broadcasts');

        } catch (\Exception $e) {
            Log::error("Broadcasting job error", [
                'exchange' => $this->exchange,
                'symbol' => $this->symbol,
                'error' => $e->getMessage()
            ]);

            self::dispatch($this->exchange, $this->symbol)
                ->delay(now()->addSeconds(10))
                ->onQueue('broadcasts');
        }
    }
}
