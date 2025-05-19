<?php

namespace App\Console\Commands;

use App\Models\TechnicalIndicator;
use App\Models\TradingPair;
use App\Services\Indicators\TechnicalIndicatorService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CalculateIndicatorsCommand extends Command
{
    protected $signature = 'indicators:calculate
                           {--exchange= : Slug біржі (all для всіх)}
                           {--pair= : ID торгової пари або символ (all для всіх)}
                           {--indicator= : ID або назва індикатора (all для всіх)}
                           {--timeframe=1d : Таймфрейм для розрахунку}
                           {--limit=100 : Кількість останніх свічок для розрахунку}';

    protected $description = 'Розрахувати технічні індикатори для торгових пар';

    protected TechnicalIndicatorService $indicatorService;

    public function __construct(TechnicalIndicatorService $indicatorService)
    {
        parent::__construct();
        $this->indicatorService = $indicatorService;
    }

    public function handle(): void
    {
        $exchange = $this->option('exchange');
        $pair = $this->option('pair');
        $indicator = $this->option('indicator');
        $timeframe = $this->option('timeframe');
        $limit = (int) $this->option('limit');

        $tradingPairs = $this->getTradingPairs($exchange, $pair);
        $indicators = $this->getIndicators($indicator);

        $totalPairs = count($tradingPairs);
        $totalIndicators = count($indicators);
        $this->info("Розпочато розрахунок {$totalIndicators} індикаторів для {$totalPairs} торгових пар");

        $successCount = 0;
        $errorCount = 0;

        foreach ($tradingPairs as $tradingPair) {
            $this->info("Обробка пари: {$tradingPair->symbol} ({$tradingPair->exchange->name})");

            foreach ($indicators as $indicatorModel) {
                $this->line("  Розрахунок: {$indicatorModel->name}");

                try {
                    $result = $this
                        ->indicatorService
                        ->calculateIndicator(
                            $indicatorModel->id,
                            $tradingPair->id,
                            $timeframe,
                            $indicatorModel->default_parameters ?? [],
                            $limit,
                        );

                    if ($result['status'] === 'success') {
                        $successCount++;
                        $this->line("    ✅ Успішно розраховано {$result['data_count']} значень");
                    } else {
                        $errorCount++;
                        $this->warn("    ⚠️ Помилка: {$result['message']}");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("    ❌ Виключення: {$e->getMessage()}");

                    Log::error('Помилка розрахунку індикатора', [
                        'indicator' => $indicatorModel->name,
                        'trading_pair' => $tradingPair->symbol,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        $this->info("Завершено! Успішно: {$successCount}, Помилок: {$errorCount}");
    }

    protected function getTradingPairs(?string $exchange, ?string $pair): Collection
    {
        $query = TradingPair
            ::with('exchange')
            ->where('is_active', true);

        if ($exchange && $exchange !== 'all') {
            $query->whereHas('exchange', function ($q) use ($exchange) {
                $q->where('slug', $exchange);
            });
        }

        if ($pair && $pair !== 'all') {
            if (is_numeric($pair)) {
                $query->where('id', $pair);
            } else {
                $query->where('symbol', $pair);
            }
        }

        return $query->get();
    }

    protected function getIndicators(?string $indicator): Collection
    {
        $query = TechnicalIndicator::where('is_active', true);

        if ($indicator && $indicator !== 'all') {
            if (is_numeric($indicator)) {
                $query->where('id', $indicator);
            } else {
                $query->where('name', $indicator);
            }
        }

        return $query->get();
    }
}
