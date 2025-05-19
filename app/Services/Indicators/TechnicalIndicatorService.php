<?php

namespace App\Services\Indicators;

use App\Models\HistoricalData;
use App\Models\IndicatorValue;
use App\Models\TechnicalIndicator;
use App\Models\TradingPair;
use Illuminate\Support\Facades\Log;

class TechnicalIndicatorService
{
    public function calculateIndicator(
        int $technicalIndicatorId,
        int $tradingPairId,
        string $timeframe,
        array $parameters = [],
        ?int $limit = 100
    ): array {
        try {
            $technicalIndicator = TechnicalIndicator::findOrFail($technicalIndicatorId);
            $tradingPair = TradingPair::findOrFail($tradingPairId);

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
                    'message' => 'Недостатньо історичних даних для розрахунку індикатора.',
                    'technical_indicator' => $technicalIndicator->name,
                    'trading_pair' => $tradingPair->symbol,
                    'timeframe' => $timeframe,
                ];
            }

            $mergedParameters = $technicalIndicator->mergeWithDefaultParameters($parameters);

            $indicatorValues = $this->performCalculation(
                $technicalIndicator->name,
                $historicalData,
                $mergedParameters
            );

            if (empty($indicatorValues)) {
                return [
                    'status' => 'error',
                    'message' => 'Помилка розрахунку індикатора.',
                    'technical_indicator' => $technicalIndicator->name,
                    'trading_pair' => $tradingPair->symbol,
                    'timeframe' => $timeframe,
                ];
            }

            $savedValues = $this->saveIndicatorValues(
                $technicalIndicator->id,
                $tradingPair->id,
                $timeframe,
                $mergedParameters,
                $indicatorValues,
                $historicalData
            );

            return [
                'status' => 'success',
                'message' => 'Індикатор успішно розрахований.',
                'technical_indicator' => $technicalIndicator->name,
                'trading_pair' => $tradingPair->symbol,
                'timeframe' => $timeframe,
                'parameters' => $mergedParameters,
                'data_count' => count($savedValues),
                'data' => $savedValues,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка розрахунку індикатора', [
                'technical_indicator_id' => $technicalIndicatorId,
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'technical_indicator_id' => $technicalIndicatorId,
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
            ];
        }
    }

    public function calculateAllIndicators(int $tradingPairId, string $timeframe, ?int $limit = 100): array
    {
        try {
            $indicators = TechnicalIndicator::where('is_active', true)->get();

            $results = [];

            foreach ($indicators as $indicator) {
                $result = $this->calculateIndicator(
                    $indicator->id,
                    $tradingPairId,
                    $timeframe,
                    [],
                    $limit
                );

                $results[] = $result;
            }

            return [
                'status' => 'success',
                'message' => 'Всі індикатори успішно розраховані.',
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'indicators_count' => count($results),
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка розрахунку всіх індикаторів', [
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

    public function getLatestIndicatorValues(
        int $technicalIndicatorId,
        int $tradingPairId,
        string $timeframe,
        array $parameters = [],
        int $limit = 100
    ): array {
        try {
            $technicalIndicator = TechnicalIndicator::findOrFail($technicalIndicatorId);
            $tradingPair = TradingPair::findOrFail($tradingPairId);

            $mergedParameters = $technicalIndicator->mergeWithDefaultParameters($parameters);
            $paramJson = json_encode($mergedParameters);

            $values = IndicatorValue
                ::where('technical_indicator_id', $technicalIndicatorId)
                ->where('trading_pair_id', $tradingPairId)
                ->where('timeframe', $timeframe)
                ->where('parameters', $paramJson)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get()
                ->sortBy('timestamp')
                ->values();

            if ($values->isEmpty()) {
                $result = $this->calculateIndicator(
                    $technicalIndicatorId,
                    $tradingPairId,
                    $timeframe,
                    $parameters,
                    $limit
                );

                if ($result['status'] === 'success') {
                    return $result;
                }

                return [
                    'status' => 'error',
                    'message' => 'Значення індикатора не знайдені та не можуть бути розраховані.',
                    'technical_indicator' => $technicalIndicator->name,
                    'trading_pair' => $tradingPair->symbol,
                    'timeframe' => $timeframe,
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Значення індикатора отримані.',
                'technical_indicator' => $technicalIndicator->name,
                'trading_pair' => $tradingPair->symbol,
                'timeframe' => $timeframe,
                'parameters' => $mergedParameters,
                'data_count' => count($values),
                'data' => $values,
            ];
        } catch (\Exception $e) {
            Log::error('Помилка отримання значень індикатора', [
                'technical_indicator_id' => $technicalIndicatorId,
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'technical_indicator_id' => $technicalIndicatorId,
                'trading_pair_id' => $tradingPairId,
                'timeframe' => $timeframe,
            ];
        }
    }

    protected function performCalculation(string $indicatorName, $historicalData, array $parameters): array
    {
        $ohlcv = $this->prepareOHLCVData($historicalData);

        return match ($indicatorName) {
            'SMA' => $this->calculateSMA($ohlcv, $parameters),
            'EMA' => $this->calculateEMA($ohlcv, $parameters),
            'MACD' => $this->calculateMACD($ohlcv, $parameters),
            'RSI' => $this->calculateRSI($ohlcv, $parameters),
            'Bollinger Bands' => $this->calculateBollingerBands($ohlcv, $parameters),
            'Stochastic' => $this->calculateStochastic($ohlcv, $parameters),
            'ADX' => $this->calculateADX($ohlcv, $parameters),
            'CCI' => $this->calculateCCI($ohlcv, $parameters),
            'OBV' => $this->calculateOBV($ohlcv),
            'ATR' => $this->calculateATR($ohlcv, $parameters),
            default => [],
        };
    }

    protected function prepareOHLCVData($historicalData): array
    {
        $timestamps = [];
        $open = [];
        $high = [];
        $low = [];
        $close = [];
        $volume = [];

        foreach ($historicalData as $candle) {
            $timestamps[] = $candle->timestamp->timestamp * 1000;
            $open[] = (float) $candle->open;
            $high[] = (float) $candle->high;
            $low[] = (float) $candle->low;
            $close[] = (float) $candle->close;
            $volume[] = (float) $candle->volume;
        }

        return [
            'timestamps' => $timestamps,
            'open' => $open,
            'high' => $high,
            'low' => $low,
            'close' => $close,
            'volume' => $volume,
        ];
    }

    protected function saveIndicatorValues(
        int $technicalIndicatorId,
        int $tradingPairId,
        string $timeframe,
        array $parameters,
        array $indicatorValues,
        $historicalData
    ): array {
        $paramJson = json_encode($parameters);
        $savedValues = [];

        foreach ($indicatorValues as $index => $values) {
            if (!isset($historicalData[$index])) {
                continue;
            }

            $candle = $historicalData[$index];

            $indicatorValue = IndicatorValue::updateOrCreate(
                [
                    'technical_indicator_id' => $technicalIndicatorId,
                    'trading_pair_id' => $tradingPairId,
                    'timeframe' => $timeframe,
                    'timestamp' => $candle->timestamp,
                    'parameters' => $paramJson,
                ],
                ['values' => $values],
            );

            $savedValues[] = $indicatorValue;
        }

        return $savedValues;
    }

    protected function calculateSMA(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 20;
        $source = $parameters['source'] ?? 'close';
        $sourceData = $ohlcv[$source];
        $result = [];

        if (count($sourceData) < $length) {
            return [];
        }

        for ($i = 0, $iMax = count($sourceData); $i < $iMax; $i++) {
            if ($i < $length - 1) {
                $result[] = ['value' => null];
            } else {
                $sum = 0;

                for ($j = $i - $length + 1; $j <= $i; $j++) {
                    $sum += $sourceData[$j];
                }

                $sma = $sum / $length;
                $result[] = ['value' => $sma];
            }
        }

        return $result;
    }

    protected function calculateEMA(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 20;
        $source = $parameters['source'] ?? 'close';
        $sourceData = $ohlcv[$source];
        $result = [];

        if (count($sourceData) < $length) {
            return [];
        }

        $multiplier = 2 / ($length + 1);

        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $sum += $sourceData[$i];
        }
        $ema = $sum / $length;

        for ($i = 0; $i < $length - 1; $i++) {
            $result[] = ['value' => null];
        }

        $result[] = ['value' => $ema];

        for ($i = $length, $iMax = count($sourceData); $i < $iMax; $i++) {
            $ema = ($sourceData[$i] - $ema) * $multiplier + $ema;
            $result[] = ['value' => $ema];
        }

        return $result;
    }

    protected function calculateMACD(array $ohlcv, array $parameters): array
    {
        $fastLength = $parameters['fast_length'] ?? 12;
        $slowLength = $parameters['slow_length'] ?? 26;
        $signalLength = $parameters['signal_length'] ?? 9;
        $source = $parameters['source'] ?? 'close';
        $sourceData = $ohlcv[$source];
        $result = [];

        if (count($sourceData) < $slowLength + $signalLength) {
            return [];
        }

        $fastEMA = $this->calculateEMAArray($sourceData, $fastLength);

        $slowEMA = $this->calculateEMAArray($sourceData, $slowLength);

        $macdLine = [];

        for ($i = 0, $iMax = count($sourceData); $i < $iMax; $i++) {
            if ($i < $slowLength - 1) {
                $macdLine[] = null;
            } else {
                $macdLine[] = $fastEMA[$i] - $slowEMA[$i];
            }
        }

        $signalLine = $this->calculateEMAArray($macdLine, $signalLength, $slowLength - 1);

        for ($i = 0, $iMax = count($sourceData); $i < $iMax; $i++) {
            if ($i < $slowLength + $signalLength - 2) {
                $result[] = [
                    'macd' => null,
                    'signal' => null,
                    'histogram' => null,
                ];
            } else {
                $histogram = $macdLine[$i] - $signalLine[$i];

                $result[] = [
                    'macd' => $macdLine[$i],
                    'signal' => $signalLine[$i],
                    'histogram' => $histogram,
                ];
            }
        }

        return $result;
    }

    protected function calculateRSI(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 14;
        $source = $parameters['source'] ?? 'close';
        $overbought = $parameters['overbought'] ?? 70;
        $oversold = $parameters['oversold'] ?? 30;
        $sourceData = $ohlcv[$source];
        $result = [];

        if (count($sourceData) < $length + 1) {
            return [];
        }

        $changes = [];

        for ($i = 1, $iMax = count($sourceData); $i < $iMax; $i++) {
            $changes[] = $sourceData[$i] - $sourceData[$i - 1];
        }

        for ($i = 0; $i < $length; $i++) {
            $result[] = ['value' => null];
        }

        $gains = 0;
        $losses = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($changes[$i] >= 0) {
                $gains += $changes[$i];
            } else {
                $losses += abs($changes[$i]);
            }
        }

        $avgGain = $gains / $length;
        $avgLoss = $losses / $length;

        if ($avgLoss === 0) {
            $rsi = 100;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi = 100 - (100 / (1 + $rs));
        }

        $result[] = [
            'value' => $rsi,
            'overbought' => $overbought,
            'oversold' => $oversold,
        ];

        for ($i = $length, $iMax = count($changes); $i < $iMax; $i++) {
            $change = $changes[$i];
            $gain = max($change, 0);
            $loss = $change < 0 ? abs($change) : 0;

            $avgGain = (($avgGain * ($length - 1)) + $gain) / $length;
            $avgLoss = (($avgLoss * ($length - 1)) + $loss) / $length;

            if ($avgLoss === 0) {
                $rsi = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi = 100 - (100 / (1 + $rs));
            }

            $result[] = [
                'value' => $rsi,
                'overbought' => $overbought,
                'oversold' => $oversold,
            ];
        }

        return $result;
    }

    protected function calculateBollingerBands(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 20;
        $stdDev = $parameters['std_dev'] ?? 2;
        $source = $parameters['source'] ?? 'close';
        $sourceData = $ohlcv[$source];
        $result = [];

        if (count($sourceData) < $length) {
            return [];
        }

        $smaValues = $this->calculateSMA($ohlcv, ['length' => $length, 'source' => $source]);

        for ($i = 0; $i < $length - 1; $i++) {
            $result[] = [
                'middle' => null,
                'upper' => null,
                'lower' => null,
            ];
        }

        for ($i = $length - 1, $iMax = count($sourceData); $i < $iMax; $i++) {
            $sma = $smaValues[$i]['value'];

            $sumSquaredDiff = 0;

            for ($j = $i - $length + 1; $j <= $i; $j++) {
                $sumSquaredDiff += ($sourceData[$j] - $sma) ** 2;
            }

            $stdDevValue = sqrt($sumSquaredDiff / $length);

            $upper = $sma + ($stdDev * $stdDevValue);
            $lower = $sma - ($stdDev * $stdDevValue);

            $result[] = [
                'middle' => $sma,
                'upper' => $upper,
                'lower' => $lower,
            ];
        }

        return $result;
    }

    protected function calculateStochastic(array $ohlcv, array $parameters): array
    {
        $kLength = $parameters['k_length'] ?? 14;
        $dLength = $parameters['d_length'] ?? 3;
        $smooth = $parameters['smooth'] ?? 3;
        $overbought = $parameters['overbought'] ?? 80;
        $oversold = $parameters['oversold'] ?? 20;

        $high = $ohlcv['high'];
        $low = $ohlcv['low'];
        $close = $ohlcv['close'];
        $result = [];

        if (count($high) < $kLength + $dLength) {
            return [];
        }

        for ($i = 0; $i < $kLength - 1; $i++) {
            $result[] = [
                'k' => null,
                'd' => null,
                'overbought' => $overbought,
                'oversold' => $oversold,
            ];
        }

        $kValues = [];

        for ($i = $kLength - 1, $iMax = count($high); $i < $iMax; $i++) {
            $highestHigh = max(array_slice($high, $i - $kLength + 1, $kLength));
            $lowestLow = min(array_slice($low, $i - $kLength + 1, $kLength));

            if ($highestHigh - $lowestLow === 0) {
                $k = 50;
            } else {
                $k = 100 * (($close[$i] - $lowestLow) / ($highestHigh - $lowestLow));
            }

            $kValues[] = $k;
        }

        if ($smooth > 1) {
            $smoothedK = [];

            for ($i = 0, $iMax = count($kValues); $i < $iMax; $i++) {
                if ($i < $smooth - 1) {
                    $smoothedK[] = $kValues[$i];
                } else {
                    $sum = 0;

                    for ($j = $i - $smooth + 1; $j <= $i; $j++) {
                        $sum += $kValues[$j - ($kLength - 1)];
                    }

                    $smoothedK[] = $sum / $smooth;
                }
            }

            $kValues = $smoothedK;
        }

        $dValues = [];

        for ($i = 0, $iMax = count($kValues); $i < $iMax; $i++) {
            if ($i < $dLength - 1) {
                $dValues[] = null;
            } else {
                $sum = 0;

                for ($j = $i - $dLength + 1; $j <= $i; $j++) {
                    $sum += $kValues[$j];
                }

                $dValues[] = $sum / $dLength;
            }
        }

        for ($i = 0, $iMax = count($kValues); $i < $iMax; $i++) {
            $result[] = [
                'k' => $kValues[$i],
                'd' => $dValues[$i],
                'overbought' => $overbought,
                'oversold' => $oversold,
            ];
        }

        for ($i = count($result), $iMax = count($high); $i < $iMax; $i++) {
            $result[] = [
                'k' => null,
                'd' => null,
                'overbought' => $overbought,
                'oversold' => $oversold,
            ];
        }

        return $result;
    }

    protected function calculateADX(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 14;
        $high = $ohlcv['high'];
        $low = $ohlcv['low'];
        $close = $ohlcv['close'];
        $result = [];

        if (count($high) < ($length * 2)) {
            return [];
        }

        for ($i = 0; $i < $length * 2 - 1; $i++) {
            $result[] = [
                'adx' => null,
                'pdi' => null,
                'mdi' => null,
            ];
        }

        $tr = [];
        $plusDM = [];
        $minusDM = [];

        for ($i = 1, $iMax = count($high); $i < $iMax; $i++) {
            $tr[] = max(
                $high[$i] - $low[$i],
                abs($high[$i] - $close[$i - 1]),
                abs($low[$i] - $close[$i - 1])
            );

            $highMove = $high[$i] - $high[$i - 1];
            $lowMove = $low[$i - 1] - $low[$i];

            if ($highMove > $lowMove && $highMove > 0) {
                $plusDM[] = $highMove;
            } else {
                $plusDM[] = 0;
            }

            if ($lowMove > $highMove && $lowMove > 0) {
                $minusDM[] = $lowMove;
            } else {
                $minusDM[] = 0;
            }
        }

        $smoothedTR = $this->calculateSmoothed($tr, $length);
        $smoothedPlusDM = $this->calculateSmoothed($plusDM, $length);
        $smoothedMinusDM = $this->calculateSmoothed($minusDM, $length);

        $plusDI = [];
        $minusDI = [];
        $dx = [];

        for ($i = 0, $iMax = count($smoothedTR); $i < $iMax; $i++) {
            if ($smoothedTR[$i] === 0) {
                $plusDI[] = 0;
                $minusDI[] = 0;
            } else {
                $plusDI[] = 100 * ($smoothedPlusDM[$i] / $smoothedTR[$i]);
                $minusDI[] = 100 * ($smoothedMinusDM[$i] / $smoothedTR[$i]);
            }

            if ($plusDI[$i] + $minusDI[$i] === 0) {
                $dx[] = 0;
            } else {
                $dx[] = 100 * (abs($plusDI[$i] - $minusDI[$i]) / ($plusDI[$i] + $minusDI[$i]));
            }
        }

        $adx = [];

        for ($i = 0, $iMax = count($dx); $i < $iMax; $i++) {
            if ($i < $length - 1) {
                $adx[] = null;
            } else {
                $sum = 0;

                for ($j = $i - $length + 1; $j <= $i; $j++) {
                    $sum += $dx[$j];
                }

                $adx[] = $sum / $length;
            }
        }

        for ($i = 0, $iMax = count($adx); $i < $iMax; $i++) {
            if ($adx[$i] !== null) {
                $result[] = [
                    'adx' => $adx[$i],
                    'pdi' => $plusDI[$i],
                    'mdi' => $minusDI[$i],
                ];
            }
        }

        return $result;
    }

    protected function calculateCCI(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 20;
        $constant = $parameters['constant'] ?? 0.015;
        $high = $ohlcv['high'];
        $low = $ohlcv['low'];
        $close = $ohlcv['close'];
        $result = [];

        if (count($high) < $length) {
            return [];
        }

        for ($i = 0; $i < $length - 1; $i++) {
            $result[] = ['value' => null];
        }

        $tp = [];

        for ($i = 0, $iMax = count($high); $i < $iMax; $i++) {
            $tp[] = ($high[$i] + $low[$i] + $close[$i]) / 3;
        }

        for ($i = $length - 1, $iMax = count($tp); $i < $iMax; $i++) {
            $sum = 0;

            for ($j = $i - $length + 1; $j <= $i; $j++) {
                $sum += $tp[$j];
            }

            $smaTP = $sum / $length;

            $meanDeviation = 0;

            for ($j = $i - $length + 1; $j <= $i; $j++) {
                $meanDeviation += abs($tp[$j] - $smaTP);
            }

            $meanDeviation /= $length;

            $cci = ($meanDeviation !== 0)
                ? ($tp[$i] - $smaTP) / ($constant * $meanDeviation)
                : 0;

            $result[] = ['value' => $cci];
        }

        return $result;
    }

    protected function calculateOBV(array $ohlcv): array
    {
        $close = $ohlcv['close'];
        $volume = $ohlcv['volume'];
        $result = [];

        if (count($close) < 2) {
            return [];
        }

        $obv = $volume[0];
        $result[] = ['value' => $obv];

        for ($i = 1, $iMax = count($close); $i < $iMax; $i++) {
            if ($close[$i] > $close[$i - 1]) {
                $obv += $volume[$i];
            } elseif ($close[$i] < $close[$i - 1]) {
                $obv -= $volume[$i];
            }

            $result[] = ['value' => $obv];
        }

        return $result;
    }

    protected function calculateATR(array $ohlcv, array $parameters): array
    {
        $length = $parameters['length'] ?? 14;
        $high = $ohlcv['high'];
        $low = $ohlcv['low'];
        $close = $ohlcv['close'];
        $result = [];

        if (count($high) < $length + 1) {
            return [];
        }

        for ($i = 0; $i < 1; $i++) {
            $result[] = ['value' => null];
        }

        $tr = [];
        $tr[] = $high[0] - $low[0];

        for ($i = 1, $iMax = count($high); $i < $iMax; $i++) {
            $tr[] = max(
                $high[$i] - $low[$i],
                abs($high[$i] - $close[$i - 1]),
                abs($low[$i] - $close[$i - 1]),
            );
        }

        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $sum += $tr[$i];
        }

        $atr = $sum / $length;

        $result[] = ['value' => $atr];

        for ($i = $length, $iMax = count($tr); $i < $iMax; $i++) {
            $atr = (($atr * ($length - 1)) + $tr[$i]) / $length;
            $result[] = ['value' => $atr];
        }

        for ($i = count($result), $iMax = count($high); $i < $iMax; $i++) {
            $result[] = ['value' => null];
        }

        return $result;
    }

    protected function calculateEMAArray(array $data, int $length, int $offset = 0): array
    {
        $emaValues = [];
        $multiplier = 2 / ($length + 1);

        for ($i = 0; $i < $offset; $i++) {
            $emaValues[] = null;
        }

        $sum = 0;
        $count = 0;

        for ($i = $offset; $i < $offset + $length && $i < count($data); $i++) {
            if ($data[$i] !== null) {
                $sum += $data[$i];
                $count++;
            }
        }

        if ($count > 0) {
            $ema = $sum / $count;
        } else {
            $ema = null;
        }

        for ($i = $offset; $i < $offset + $length - 1 && $i < count($data); $i++) {
            $emaValues[] = null;
        }

        $emaValues[] = $ema;

        for ($i = $offset + $length, $iMax = count($data); $i < $iMax; $i++) {
            if ($data[$i] !== null && $ema !== null) {
                $ema = ($data[$i] - $ema) * $multiplier + $ema;
            }

            $emaValues[] = $ema;
        }

        return $emaValues;
    }

    protected function calculateSmoothed(array $data, int $length): array
    {
        if (empty($data) || $length <= 0) {
            return [];
        }

        $smoothed = [];
        $sum = 0;

        for ($i = 0; $i < $length && $i < count($data); $i++) {
            $sum += $data[$i];
        }

        $smoothed[] = $sum;

        for ($i = 1; $i < count($data) - $length + 1; $i++) {
            $smoothed[] = $smoothed[$i - 1] - ($smoothed[$i - 1] / $length) + $data[$i + $length - 1];
        }

        return $smoothed;
    }
}
