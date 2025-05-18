<?php

namespace App\Console\Commands;

use App\Services\Broadcast\CryptoDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BroadcastCryptoDataCommand extends Command
{
    protected $signature = 'crypto:broadcast
                           {--exchange= : Slug біржі (all для всіх)}
                           {--symbol= : Символ торгової пари (all для всіх активних пар)}
                           {--interval=5 : Інтервал між оновленнями в секундах}';

    protected $description = 'Broadcast cryptocurrency data through WebSockets';

    protected CryptoDataService $dataService;

    public function __construct(CryptoDataService $dataService)
    {
        parent::__construct();
        $this->dataService = $dataService;
    }

    public function handle(): void
    {
        $exchange = $this->option('exchange');
        $symbol = $this->option('symbol');
        $interval = (int) $this->option('interval');

        $this->info("Starting cryptocurrency data broadcast");
        $this->info("Press Ctrl+C to stop");

        while (true) {
            try {
                if ($exchange && $exchange !== 'all' && $symbol && $symbol !== 'all') {
                    $this->info("Broadcasting data for {$exchange}:{$symbol}");

                    $this->dataService->broadcastPrice($exchange, $symbol);
                    $this->dataService->broadcastOrderBook($exchange, $symbol);
                } else {
                    $count = $this->dataService->broadcastAllActivePairs();

                    $this->info("Broadcasted data for {$count} trading pairs");
                }

                sleep($interval);
            } catch (\Exception $e) {
                $this->error("Error during broadcasting: {$e->getMessage()}");
                Log::error("Broadcasting error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                sleep(5);
            }
        }
    }
}
