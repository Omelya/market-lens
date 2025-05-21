<?php

namespace App\Services\Analysis;

use App\Models\HistoricalData;
use App\Models\IndicatorValue;
use App\Models\TechnicalIndicator;
use App\Models\TradingPair;
use App\Models\TradingSignal;
use App\Services\Indicators\TechnicalIndicatorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class PatternAnalysisService
{
    protected TechnicalIndicatorService $indicatorService;

    public function __construct(TechnicalIndicatorService $indicatorService)
    {
        $this->indicatorService = $indicatorService;
    }

    public function analyzePatterns(
        int $tradingPairId,
        string $timeframe = '1d',
        bool $generateSignals = true,
        int $limit = 100
    ): array {
        try {
            $historicalData = HistoricalData
                ::where('trading_pair_id', $tradingPairId)
                ->where('timeframe', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->sortBy('timestamp')
                ->values();

            if ($historicalData->isEmpty()) {
                return [
                    'status' => 'error',
                    'message' => 'Недостатньо історичних даних для аналізу.',
                    'trading_pair_id' => $tradingPairId,
                    'timeframe' => $timeframe,
                ];
            }

            $indicatorValues = $this->getIndicatorValuesForAnalysis($tradingPairId, $timeframe, $limit);

            $patterns = $this->findPatterns($historicalData, $indicatorValues);

            if ($generateSignals && !empty($patterns)) {
                $signals = $this->generateTradingSignals($tradingPairId, $timeframe, $patterns, $historicalData);
                $signalsCount = count($signals);
            } else {
                $signals = [];
                $signalsCount = 0;
            }

            return [
                'status' => 'success',
                'message' => 'Аналіз паттернів виконано.',
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'patterns_count' => count($patterns),
                'signals_count' => $signalsCount,
                'patterns' => $patterns,
                'signals' => $signals,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка аналізу паттернів', [
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

    protected function getIndicatorValuesForAnalysis(int $tradingPairId, string $timeframe, int $limit): array
    {
        $result = [];

        $indicators = TechnicalIndicator::where('is_active', true)->get();

        foreach ($indicators as $indicator) {
            $values = IndicatorValue::where('technical_indicator_id', $indicator->id)
                ->where('trading_pair_id', $tradingPairId)
                ->where('timeframe', $timeframe)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->sortBy('timestamp')
                ->values();

            if ($values->isEmpty()) {
                $calcResult = $this
                    ->indicatorService
                    ->calculateIndicator(
                        $indicator->id,
                        $tradingPairId,
                        $timeframe,
                        $indicator->default_parameters ?? [],
                        $limit,
                    );

                if ($calcResult['status'] === 'success') {
                    $values = collect($calcResult['data']);
                }
            }

            if (!$values->isEmpty()) {
                $result[$indicator->name] = $values;
            }
        }

        return $result;
    }

    protected function generateTradingSignals(
        int $tradingPairId,
        string $timeframe,
        array $patterns,
        Collection $historicalData,
    ): array
    {
        $signals = [];

        TradingPair::findOrFail($tradingPairId);

        $latestTimestamp = $historicalData->last()->timestamp;

        $latestPatterns = array_filter($patterns, function ($pattern) use ($latestTimestamp, $timeframe) {
            $patternTime = strtotime($pattern['timestamp']);
            $latestTime = strtotime($latestTimestamp);
            $diffSeconds = $latestTime - $patternTime;

            $candleDuration = $this->getCandleDurationInSeconds($timeframe);

            return $diffSeconds <= ($candleDuration * 3);
        });

        if (empty($latestPatterns)) {
            return $signals;
        }

        $bullishPatterns = array_filter($latestPatterns, static fn($p) => $p['type'] === 'bullish');
        $bearishPatterns = array_filter($latestPatterns, static fn($p) => $p['type'] === 'bearish');

        $latestCandle = $historicalData->last();

        if (count($bullishPatterns) >= 2) {
            $strongPatterns = array_filter($bullishPatterns, static fn($p) => $p['strength'] === 'strong');
            $strength = count($strongPatterns) > 0 ? 'strong' : 'medium';

            $signal = $this->createTradingSignal(
                $tradingPairId,
                $timeframe,
                'buy',
                'technical',
                $strength,
                $latestCandle->close,
                $this->calculateStopLoss($latestCandle, true, 3),
                $this->calculateTakeProfit($latestCandle, true, 2),
                array_values($bullishPatterns),
            );

            $signals[] = $signal;
        }

        if (count($bearishPatterns) >= 2) {
            $strongPatterns = array_filter($bearishPatterns, fn($p) => $p['strength'] === 'strong');
            $strength = count($strongPatterns) > 0 ? 'strong' : 'medium';

            $signal = $this->createTradingSignal(
                $tradingPairId,
                $timeframe,
                'sell',
                'technical',
                $strength,
                $latestCandle->close,
                $this->calculateStopLoss($latestCandle, false, 3),
                $this->calculateTakeProfit($latestCandle, false, 2),
                array_values($bearishPatterns),
            );

            $signals[] = $signal;
        }

        return $signals;
    }

    protected function createTradingSignal(
        int $tradingPairId,
        string $timeframe,
        string $direction,
        string $signalType,
        string $strength,
        float $entryPrice,
        float $stopLoss,
        float $takeProfit,
        array $patterns
    ): TradingSignal {
        $patternNames = array_map(static fn($p) => $p['name'], $patterns);
        $indicatorsData = [];

        foreach ($patterns as $pattern) {
            $indicatorsData[] = [
                'pattern_name' => $pattern['name'],
                'pattern_type' => $pattern['type'],
                'pattern_strength' => $pattern['strength'],
                'indicators' => $pattern['indicators'] ?? [],
            ];
        }

        $riskAmount = abs($entryPrice - $stopLoss);
        $rewardAmount = abs($takeProfit - $entryPrice);
        $riskRewardRatio = ($riskAmount > 0) ? ($rewardAmount / $riskAmount) : 0;

        $successProbability = 0;

        switch ($strength) {
            case 'strong':
                $successProbability = 0.7;
                break;
            case 'medium':
                $successProbability = 0.55;
                break;
            case 'weak':
                $successProbability = 0.4;
                break;
        }

        $signal = new TradingSignal();
        $signal->trading_pair_id = $tradingPairId;
        $signal->timeframe = $timeframe;
        $signal->timestamp = now();
        $signal->direction = $direction;
        $signal->signal_type = $signalType;
        $signal->strength = $strength;
        $signal->entry_price = $entryPrice;
        $signal->stop_loss = $stopLoss;
        $signal->take_profit = $takeProfit;
        $signal->indicators_data = $indicatorsData;
        $signal->risk_reward_ratio = $riskRewardRatio;
        $signal->success_probability = $successProbability;
        $signal->metadata = [
            'patterns' => $patternNames,
            'generation_time' => now()->toDateTimeString(),
        ];

        $signal->is_active = true;

        $signal->save();

        return $signal;
    }

    protected function calculateStopLoss(HistoricalData $candle, bool $isBuy, int $atrMultiplier = 3): float
    {
        $range = $candle->high - $candle->low;

        if ($isBuy) {
            return max(0, $candle->close - ($range * $atrMultiplier / 10));
        }

        return $candle->close + ($range * $atrMultiplier / 10);
    }

    protected function calculateTakeProfit(
        HistoricalData $candle,
        bool $isBuy,
        int $riskRewardRatio = 2,
    ): float
    {
        $range = $candle->high - $candle->low;

        if ($isBuy) {
            return $candle->close + ($range * $riskRewardRatio / 5);
        }

        return max(0, $candle->close - ($range * $riskRewardRatio / 5));
    }

    protected function getCandleDurationInSeconds(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 60,
            '3m' => 60 * 3,
            '5m' => 60 * 5,
            '15m' => 60 * 15,
            '30m' => 60 * 30,
            '1h' => 60 * 60,
            '2h' => 60 * 60 * 2,
            '4h' => 60 * 60 * 4,
            '6h' => 60 * 60 * 6,
            '8h' => 60 * 60 * 8,
            '12h' => 60 * 60 * 12,
            '3d' => 60 * 60 * 24 * 3,
            '1w' => 60 * 60 * 24 * 7,
            '1M' => 60 * 60 * 24 * 30,
            default => 60 * 60 * 24,
        };
    }

    protected function findPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        // Пошук паттернів на основі ковзних середніх (MA)
        $maPatterns = $this->findMAPatterns($historicalData, $indicatorValues);
        $patterns = array_merge($patterns, $maPatterns);

        // Пошук паттернів на основі RSI
        $rsiPatterns = $this->findRSIPatterns($historicalData, $indicatorValues);
        $patterns = array_merge($patterns, $rsiPatterns);

        // Пошук паттернів на основі MACD
        $macdPatterns = $this->findMACDPatterns($historicalData, $indicatorValues);
        $patterns = array_merge($patterns, $macdPatterns);

        // Пошук паттернів на основі Bollinger Bands
        $bollingerPatterns = $this->findBollingerPatterns($historicalData, $indicatorValues);
        $patterns = array_merge($patterns, $bollingerPatterns);

        // Пошук паттернів на основі Stochastic
        $stochasticPatterns = $this->findStochasticPatterns($historicalData, $indicatorValues);
        $patterns = array_merge($patterns, $stochasticPatterns);

        // Пошук свічкових паттернів
        $candlePatterns = $this->findCandlePatterns($historicalData);

        return array_merge($patterns, $candlePatterns);
    }

    protected function findMAPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        if (!isset($indicatorValues['SMA']) && !isset($indicatorValues['EMA'])) {
            return $patterns;
        }

        $maType = isset($indicatorValues['EMA']) ? 'EMA' : 'SMA';
        $maValues = $indicatorValues[$maType];

        for ($i = 1; $i < count($historicalData) && $i < count($maValues); $i++) {
            $currentCandle = $historicalData[$i];
            $previousCandle = $historicalData[$i - 1];

            $currentMA = $maValues[$i]->values['value'] ?? null;
            $previousMA = $maValues[$i - 1]->values['value'] ?? null;

            if ($currentMA === null || $previousMA === null) {
                continue;
            }

            if ($previousCandle->close < $previousMA && $currentCandle->close > $currentMA) {
                $patterns[] = [
                    'name' => 'Golden Cross',
                    'type' => 'bullish',
                    'description' => "Ціна перетнула {$maType} знизу вгору",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'ma_type' => $maType,
                        'ma_value' => $currentMA,
                        'close' => $currentCandle->close,
                    ],
                ];
            }

            if ($previousCandle->close > $previousMA && $currentCandle->close < $currentMA) {
                $patterns[] = [
                    'name' => 'Death Cross',
                    'type' => 'bearish',
                    'description' => "Ціна перетнула {$maType} згори вниз",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'ma_type' => $maType,
                        'ma_value' => $currentMA,
                        'close' => $currentCandle->close,
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function findRSIPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        if (!isset($indicatorValues['RSI'])) {
            return $patterns;
        }

        $rsiValues = $indicatorValues['RSI'];

        for ($i = 1; $i < count($historicalData) && $i < count($rsiValues); $i++) {
            $currentCandle = $historicalData[$i];
            $currentRSI = $rsiValues[$i]->values['value'] ?? null;
            $previousRSI = $rsiValues[$i - 1]->values['value'] ?? null;
            $overbought = $rsiValues[$i]->values['overbought'] ?? 70;
            $oversold = $rsiValues[$i]->values['oversold'] ?? 30;

            if ($currentRSI === null || $previousRSI === null) {
                continue;
            }

            if ($previousRSI < $oversold && $currentRSI > $oversold) {
                $patterns[] = [
                    'name' => 'RSI Oversold Exit',
                    'type' => 'bullish',
                    'description' => "RSI вийшов із зони перепроданості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'rsi' => $currentRSI,
                        'oversold' => $oversold,
                    ],
                ];
            }

            if ($previousRSI > $overbought && $currentRSI < $overbought) {
                $patterns[] = [
                    'name' => 'RSI Overbought Exit',
                    'type' => 'bearish',
                    'description' => "RSI вийшов із зони перекупленості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'rsi' => $currentRSI,
                        'overbought' => $overbought,
                    ],
                ];
            }

            if ($i >= 5 && $currentRSI > $previousRSI && $currentCandle->low < $historicalData[$i - 1]->low) {
                $patterns[] = [
                    'name' => 'RSI Bullish Divergence',
                    'type' => 'bullish',
                    'description' => "Бичача дивергенція RSI (ціни нижче, RSI вище)",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'rsi_current' => $currentRSI,
                        'rsi_previous' => $previousRSI,
                    ],
                ];
            }

            if ($i >= 5 && $currentRSI < $previousRSI && $currentCandle->high > $historicalData[$i - 1]->high) {
                $patterns[] = [
                    'name' => 'RSI Bearish Divergence',
                    'type' => 'bearish',
                    'description' => "Ведмежа дивергенція RSI (ціни вище, RSI нижче)",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'rsi_current' => $currentRSI,
                        'rsi_previous' => $previousRSI,
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function findMACDPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        if (!isset($indicatorValues['MACD'])) {
            return $patterns;
        }

        $macdValues = $indicatorValues['MACD'];

        for ($i = 1; $i < count($historicalData) && $i < count($macdValues); $i++) {
            $currentCandle = $historicalData[$i];
            $currentMACD = $macdValues[$i]->values['macd'] ?? null;
            $currentSignal = $macdValues[$i]->values['signal'] ?? null;
            $currentHistogram = $macdValues[$i]->values['histogram'] ?? null;
            $previousMACD = $macdValues[$i - 1]->values['macd'] ?? null;
            $previousSignal = $macdValues[$i - 1]->values['signal'] ?? null;
            $previousHistogram = $macdValues[$i - 1]->values['histogram'] ?? null;

            if ($currentMACD === null || $currentSignal === null || $previousMACD === null || $previousSignal === null) {
                continue;
            }

            if ($previousMACD < $previousSignal && $currentMACD > $currentSignal) {
                $patterns[] = [
                    'name' => 'MACD Bullish Crossover',
                    'type' => 'bullish',
                    'description' => "MACD перетнув сигнальну лінію знизу вгору",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'macd' => $currentMACD,
                        'signal' => $currentSignal,
                    ],
                ];
            }

            if ($previousMACD > $previousSignal && $currentMACD < $currentSignal) {
                $patterns[] = [
                    'name' => 'MACD Bearish Crossover',
                    'type' => 'bearish',
                    'description' => "MACD перетнув сигнальну лінію згори вниз",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'macd' => $currentMACD,
                        'signal' => $currentSignal,
                    ],
                ];
            }

            if ($previousHistogram !== null && $currentHistogram !== null &&
                $previousHistogram < 0 && $currentHistogram > 0) {
                $patterns[] = [
                    'name' => 'MACD Histogram Direction Change (Bullish)',
                    'type' => 'bullish',
                    'description' => "Гістограма MACD змінила напрямок з негативного на позитивний",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'weak',
                    'indicators' => [
                        'histogram' => $currentHistogram,
                        'previous_histogram' => $previousHistogram,
                    ],
                ];
            }

            if ($previousHistogram !== null && $currentHistogram !== null &&
                $previousHistogram > 0 && $currentHistogram < 0) {
                $patterns[] = [
                    'name' => 'MACD Histogram Direction Change (Bearish)',
                    'type' => 'bearish',
                    'description' => "Гістограма MACD змінила напрямок з позитивного на негативний",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'weak',
                    'indicators' => [
                        'histogram' => $currentHistogram,
                        'previous_histogram' => $previousHistogram,
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function findBollingerPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        if (!isset($indicatorValues['Bollinger Bands'])) {
            return $patterns;
        }

        $bbValues = $indicatorValues['Bollinger Bands'];

        for ($i = 1; $i < count($historicalData) && $i < count($bbValues); $i++) {
            $currentCandle = $historicalData[$i];
            $previousCandle = $historicalData[$i - 1];

            $currentUpper = $bbValues[$i]->values['upper'] ?? null;
            $currentMiddle = $bbValues[$i]->values['middle'] ?? null;
            $currentLower = $bbValues[$i]->values['lower'] ?? null;

            $previousUpper = $bbValues[$i - 1]->values['upper'] ?? null;
            $previousLower = $bbValues[$i - 1]->values['lower'] ?? null;

            if ($currentUpper === null || $currentMiddle === null || $currentLower === null ||
                $previousUpper === null || $previousLower === null) {
                continue;
            }

            if ($previousCandle->low <= $previousLower && $currentCandle->close > $currentLower) {
                $patterns[] = [
                    'name' => 'Bollinger Bands Bounce (Lower)',
                    'type' => 'bullish',
                    'description' => "Ціна відскочила від нижньої границі Bollinger Bands",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'lower_band' => $currentLower,
                        'close' => $currentCandle->close,
                    ],
                ];
            }

            if ($previousCandle->high >= $previousUpper && $currentCandle->close < $currentUpper) {
                $patterns[] = [
                    'name' => 'Bollinger Bands Bounce (Upper)',
                    'type' => 'bearish',
                    'description' => "Ціна відскочила від верхньої границі Bollinger Bands",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'upper_band' => $currentUpper,
                        'close' => $currentCandle->close,
                    ],
                ];
            }

            if ($previousCandle->close < $previousUpper && $currentCandle->close > $currentUpper) {
                $patterns[] = [
                    'name' => 'Bollinger Bands Breakout (Upper)',
                    'type' => 'bullish',
                    'description' => "Ціна пробила верхню границю Bollinger Bands",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'upper_band' => $currentUpper,
                        'close' => $currentCandle->close,
                    ],
                ];
            }

            if ($previousCandle->close > $previousLower && $currentCandle->close < $currentLower) {
                $patterns[] = [
                    'name' => 'Bollinger Bands Breakout (Lower)',
                    'type' => 'bearish',
                    'description' => "Ціна пробила нижню границю Bollinger Bands",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'lower_band' => $currentLower,
                        'close' => $currentCandle->close,
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function findStochasticPatterns(Collection $historicalData, array $indicatorValues): array
    {
        $patterns = [];

        if (!isset($indicatorValues['Stochastic'])) {
            return $patterns;
        }

        $stochValues = $indicatorValues['Stochastic'];

        for ($i = 1; $i < count($historicalData) && $i < count($stochValues); $i++) {
            $currentCandle = $historicalData[$i];
            $currentK = $stochValues[$i]->values['k'] ?? null;
            $currentD = $stochValues[$i]->values['d'] ?? null;
            $previousK = $stochValues[$i - 1]->values['k'] ?? null;
            $previousD = $stochValues[$i - 1]->values['d'] ?? null;
            $overbought = $stochValues[$i]->values['overbought'] ?? 80;
            $oversold = $stochValues[$i]->values['oversold'] ?? 20;

            if ($currentK === null || $currentD === null || $previousK === null || $previousD === null) {
                continue;
            }

            if ($previousK < $previousD && $currentK > $currentD && $currentK < $oversold) {
                $patterns[] = [
                    'name' => 'Stochastic Bullish Crossover (Oversold)',
                    'type' => 'bullish',
                    'description' => "Stochastic перетнув лінію %D знизу вгору в зоні перепроданості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'k' => $currentK,
                        'd' => $currentD,
                        'oversold' => $oversold,
                    ],
                ];
            }

            if ($previousK > $previousD && $currentK < $currentD && $currentK > $overbought) {
                $patterns[] = [
                    'name' => 'Stochastic Bearish Crossover (Overbought)',
                    'type' => 'bearish',
                    'description' => "Stochastic перетнув лінію %D згори вниз в зоні перекупленості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'k' => $currentK,
                        'd' => $currentD,
                        'overbought' => $overbought,
                    ],
                ];
            }

            if ($previousK < $oversold && $currentK > $oversold) {
                $patterns[] = [
                    'name' => 'Stochastic Oversold Exit',
                    'type' => 'bullish',
                    'description' => "Stochastic вийшов із зони перепроданості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'k' => $currentK,
                        'oversold' => $oversold,
                    ],
                ];
            }

            if ($previousK > $overbought && $currentK < $overbought) {
                $patterns[] = [
                    'name' => 'Stochastic Overbought Exit',
                    'type' => 'bearish',
                    'description' => "Stochastic вийшов із зони перекупленості",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'k' => $currentK,
                        'overbought' => $overbought,
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function findCandlePatterns(Collection $historicalData): array
    {
        $patterns = [];

        if (count($historicalData) < 3) {
            return $patterns;
        }

        for ($i = 2, $iMax = count($historicalData); $i < $iMax; $i++) {
            $currentCandle = $historicalData[$i];
            $previousCandle = $historicalData[$i - 1];
            $thirdCandle = $historicalData[$i - 2];

            if ($this->isHammer($currentCandle)) {
                $patterns[] = [
                    'name' => 'Hammer',
                    'type' => 'bullish',
                    'description' => "Виявлено свічковий паттерн Молот",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'candle' => [
                            'open' => $currentCandle->open,
                            'high' => $currentCandle->high,
                            'low' => $currentCandle->low,
                            'close' => $currentCandle->close,
                        ],
                    ],
                ];
            }

            if ($this->isHangingMan($currentCandle, $previousCandle)) {
                $patterns[] = [
                    'name' => 'Hanging Man',
                    'type' => 'bearish',
                    'description' => "Виявлено свічковий паттерн Повішений",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'medium',
                    'indicators' => [
                        'candle' => [
                            'open' => $currentCandle->open,
                            'high' => $currentCandle->high,
                            'low' => $currentCandle->low,
                            'close' => $currentCandle->close,
                        ],
                    ],
                ];
            }

            if ($this->isBullishEngulfing($currentCandle, $previousCandle)) {
                $patterns[] = [
                    'name' => 'Bullish Engulfing',
                    'type' => 'bullish',
                    'description' => "Виявлено свічковий паттерн Бичаче Поглинання",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'current_candle' => [
                            'open' => $currentCandle->open,
                            'close' => $currentCandle->close,
                        ],
                        'previous_candle' => [
                            'open' => $previousCandle->open,
                            'close' => $previousCandle->close,
                        ],
                    ],
                ];
            }

            if ($this->isBearishEngulfing($currentCandle, $previousCandle)) {
                $patterns[] = [
                    'name' => 'Bearish Engulfing',
                    'type' => 'bearish',
                    'description' => "Виявлено свічковий паттерн Ведмеже Поглинання",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'current_candle' => [
                            'open' => $currentCandle->open,
                            'close' => $currentCandle->close,
                        ],
                        'previous_candle' => [
                            'open' => $previousCandle->open,
                            'close' => $previousCandle->close,
                        ],
                    ],
                ];
            }

            if ($this->isDoji($currentCandle)) {
                $patterns[] = [
                    'name' => 'Doji',
                    'type' => 'neutral',
                    'description' => "Виявлено свічковий паттерн Доджі",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'weak',
                    'indicators' => [
                        'candle' => [
                            'open' => $currentCandle->open,
                            'high' => $currentCandle->high,
                            'low' => $currentCandle->low,
                            'close' => $currentCandle->close,
                        ],
                    ],
                ];
            }

            if ($this->isMorningStar($currentCandle, $previousCandle, $thirdCandle)) {
                $patterns[] = [
                    'name' => 'Morning Star',
                    'type' => 'bullish',
                    'description' => "Виявлено свічковий паттерн Ранкова Зірка",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'candles' => [
                            'first' => [
                                'open' => $thirdCandle->open,
                                'close' => $thirdCandle->close,
                            ],
                            'second' => [
                                'open' => $previousCandle->open,
                                'close' => $previousCandle->close,
                            ],
                            'third' => [
                                'open' => $currentCandle->open,
                                'close' => $currentCandle->close,
                            ],
                        ],
                    ],
                ];
            }

            if ($this->isEveningStar($currentCandle, $previousCandle, $thirdCandle)) {
                $patterns[] = [
                    'name' => 'Evening Star',
                    'type' => 'bearish',
                    'description' => "Виявлено свічковий паттерн Вечірня Зірка",
                    'timestamp' => $currentCandle->timestamp,
                    'strength' => 'strong',
                    'indicators' => [
                        'candles' => [
                            'first' => [
                                'open' => $thirdCandle->open,
                                'close' => $thirdCandle->close,
                            ],
                            'second' => [
                                'open' => $previousCandle->open,
                                'close' => $previousCandle->close,
                            ],
                            'third' => [
                                'open' => $currentCandle->open,
                                'close' => $currentCandle->close,
                            ],
                        ],
                    ],
                ];
            }
        }

        return $patterns;
    }

    protected function isHammer(HistoricalData $candle): bool
    {
        $body = abs($candle->close - $candle->open);
        $totalRange = $candle->high - $candle->low;

        if ((int) $totalRange === 0) {
            return false;
        }

        $lowerShadow = min($candle->open, $candle->close) - $candle->low;
        $upperShadow = $candle->high - max($candle->open, $candle->close);

        // Молот має маленьке тіло, маленьку верхню тінь і довгу нижню тінь
        return ($body / $totalRange <= 0.3) && // Маленьке тіло
            ($upperShadow / $totalRange <= 0.1) && // Маленька верхня тінь
            ($lowerShadow / $totalRange >= 0.6); // Довга нижня тінь
    }

    protected function isHangingMan(HistoricalData $candle, HistoricalData $previousCandle): bool
    {
        // Повішений схожий на молот, але з'являється після висхідного тренду
        return $this->isHammer($candle) && $previousCandle->close < $candle->open;
    }

    protected function isBullishEngulfing(HistoricalData $currentCandle, HistoricalData $previousCandle): bool
    {
        // Попередня свічка червона (ведмежа)
        $isPreviousBearish = $previousCandle->close < $previousCandle->open;

        // Поточна свічка зелена (бичача)
        $isCurrentBullish = $currentCandle->close > $currentCandle->open;

        // Поточна свічка поглинає попередню
        $isEngulfing = $currentCandle->open < $previousCandle->close &&
            $currentCandle->close > $previousCandle->open;

        return $isPreviousBearish && $isCurrentBullish && $isEngulfing;
    }

    protected function isBearishEngulfing(HistoricalData $currentCandle, HistoricalData $previousCandle): bool
    {
        // Попередня свічка зелена (бичача)
        $isPreviousBullish = $previousCandle->close > $previousCandle->open;

        // Поточна свічка червона (ведмежа)
        $isCurrentBearish = $currentCandle->close < $currentCandle->open;

        // Поточна свічка поглинає попередню
        $isEngulfing = $currentCandle->open > $previousCandle->close &&
            $currentCandle->close < $previousCandle->open;

        return $isPreviousBullish && $isCurrentBearish && $isEngulfing;
    }

    protected function isDoji(HistoricalData $candle): bool
    {
        $body = abs($candle->close - $candle->open);
        $totalRange = $candle->high - $candle->low;

        if ((int) $totalRange === 0) {
            return false;
        }

        return ($body / $totalRange <= 0.05);
    }

    protected function isMorningStar(HistoricalData $currentCandle, HistoricalData $previousCandle, HistoricalData $thirdCandle): bool
    {
        // Перша свічка червона (ведмежа) з великим тілом
        $isFirstBearish = $thirdCandle->close < $thirdCandle->open;
        $firstCandleBody = abs($thirdCandle->close - $thirdCandle->open);

        // Друга свічка з маленьким тілом (можливо Доджі)
        $secondCandleBody = abs($previousCandle->close - $previousCandle->open);
        $isSecondSmall = $secondCandleBody < ($firstCandleBody * 0.3);

        // Третя свічка зелена (бичача) з тілом, що закриває хоча б 50% тіла першої свічки
        $isThirdBullish = $currentCandle->close > $currentCandle->open;
        $isClosingGap = $currentCandle->close > ($thirdCandle->open + $thirdCandle->close) / 2;

        // Гепи між свічками
        $isGapDown = max($thirdCandle->open, $thirdCandle->close) > max($previousCandle->open, $previousCandle->close);
        $isGapUp = min($previousCandle->open, $previousCandle->close) < min($currentCandle->open, $currentCandle->close);

        return $isFirstBearish && $isSecondSmall && $isThirdBullish && $isClosingGap && $isGapDown && $isGapUp;
    }

    protected function isEveningStar(HistoricalData $currentCandle, HistoricalData $previousCandle, HistoricalData $thirdCandle): bool
    {
        // Перша свічка зелена (бичача) з великим тілом
        $isFirstBullish = $thirdCandle->close > $thirdCandle->open;
        $firstCandleBody = abs($thirdCandle->close - $thirdCandle->open);

        // Друга свічка з маленьким тілом (можливо Доджі)
        $secondCandleBody = abs($previousCandle->close - $previousCandle->open);
        $isSecondSmall = $secondCandleBody < ($firstCandleBody * 0.3);

        // Третя свічка червона (ведмежа) з тілом, що закриває хоча б 50% тіла першої свічки
        $isThirdBearish = $currentCandle->close < $currentCandle->open;
        $isClosingGap = $currentCandle->close < ($thirdCandle->open + $thirdCandle->close) / 2;

        // Гепи між свічками
        $isGapUp = min($thirdCandle->open, $thirdCandle->close) < min($previousCandle->open, $previousCandle->close);
        $isGapDown = max($previousCandle->open, $previousCandle->close) > max($currentCandle->open, $currentCandle->close);

        return $isFirstBullish && $isSecondSmall && $isThirdBearish && $isClosingGap && $isGapUp && $isGapDown;
    }
}
