<?php

namespace App\Console\Commands;

use App\Models\TradingPair;
use App\Services\Analysis\PatternAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AnalyzePatternsCommand extends Command
{
    protected $signature = 'patterns:analyze
                           {--exchange= : Slug біржі (all для всіх)}
                           {--pair= : ID торгової пари або символ (all для всіх)}
                           {--timeframe=1d : Таймфрейм для аналізу}
                           {--limit=100 : Кількість останніх свічок для аналізу}
                           {--generate-signals : Генерувати торгові сигнали}';

    protected $description = 'Аналізувати цінові паттерни для торгових пар';

    protected PatternAnalysisService $analysisService;

    protected int $successCount = 0;

    protected int $errorCount = 0;

    protected int $patterns = 0;

    protected int $tradingSignals = 0;

    public function __construct(PatternAnalysisService $analysisService)
    {
        parent::__construct();
        $this->analysisService = $analysisService;
    }

    public function handle(): void
    {
        $exchange = $this->option('exchange');
        $pair = $this->option('pair');
        $timeframe = $this->option('timeframe');
        $limit = (int) $this->option('limit');
        $generateSignals = $this->option('generate-signals');

        $tradingPairs = $this->getTradingPairs($exchange, $pair);

        $totalPairs = count($tradingPairs);
        $this->info("Розпочато аналіз паттернів для {$totalPairs} торгових пар");

        $timeframes = explode(',', $timeframe);

        foreach ($timeframes as $timeframe) {
            $this->analysePairs($tradingPairs, $timeframe, $limit, $generateSignals);
        }

        $this->newLine();
        $this->info("Завершено! Успішно: {$this->successCount}, Помилок: {$this->errorCount}");
        $this->info("Всього знайдено: {$this->patterns} паттернів, {$this->tradingSignals} сигналів");
    }

    protected function analysePairs(
        Collection $tradingPairs,
        string $timeframe,
        int $limit,
        bool $generateSignals,
    ): void
    {
        foreach ($tradingPairs as $tradingPair) {
            $this->info("Аналіз пари: {$tradingPair->symbol} ({$tradingPair->exchange->name})");

            try {
                $result = $this
                    ->analysisService
                    ->analyzePatterns(
                        $tradingPair->id,
                        $timeframe,
                        $generateSignals,
                        $limit,
                    );

                if ($result['status'] === 'success') {
                    $this->successCount++;
                    $this->patterns += $result['patterns_count'];
                    $this->tradingSignals += $result['signals_count'];

                    $this->line("  ✅ Знайдено {$result['patterns_count']} паттернів");

                    if ($generateSignals) {
                        $this->line("  ✅ Згенеровано {$result['signals_count']} торгових сигналів");
                    }

                    if (!empty($result['patterns'])) {
                        $this->line("  📊 Останні паттерни:");

                        $latestPatterns = array_slice($result['patterns'], 0, 5);

                        foreach ($latestPatterns as $pattern) {
                            $emoji = match ($pattern['type']) {
                                'bullish' => '🟢',
                                'bearish' => '🔴',
                                default => '⚪',
                            };

                            $strength = match ($pattern['strength']) {
                                'strong' => 'сильний',
                                'medium' => 'середній',
                                'weak' => 'слабкий',
                            };

                            $this->line("    {$emoji} {$pattern['name']} ({$strength}): {$pattern['description']}");
                        }
                    }

                    if ($generateSignals && !empty($result['signals'])) {
                        $this->line("  🚦 Торгові сигнали:");

                        foreach ($result['signals'] as $signal) {
                            $direction = $signal->direction === 'buy'
                                ? '📈 КУПИТИ'
                                : '📉 ПРОДАТИ';

                            $strength = match ($signal->strength) {
                                'strong' => 'сильний',
                                'medium' => 'середній',
                                'weak' => 'слабкий',
                            };

                            $this->line("    {$direction} при ціні {$signal->entry_price} ({$strength})");
                            $this->line("      SL: {$signal->stop_loss}, TP: {$signal->take_profit}, RR: {$signal->risk_reward_ratio}");
                        }
                    }
                } else {
                    $this->errorCount++;
                    $this->warn("  ⚠️ Помилка: {$result['message']}");
                }
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->error("  ❌ Виключення: {$e->getMessage()}");

                Log::error('Помилка аналізу паттернів', [
                    'trading_pair' => $tradingPair->symbol,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
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
}
