<?php

namespace App\Services\Exchanges;

use App\Models\Exchange as ExchangeModel;

class BinanceExchange extends BaseExchange
{
    /**
     * Назва класу CCXT біржі.
     *
     * @var string
     */
    protected string $ccxtClass = 'binance';

    /**
     * Список підтримуваних таймфреймів.
     *
     * @var array
     */
    protected array $supportedTimeframes = [
        '1m', '3m', '5m', '15m', '30m',
        '1h', '2h', '4h', '6h', '8h', '12h',
        '1d', '3d', '1w', '1M'
    ];

    /**
     * Список підтримуваних типів ордерів.
     *
     * @var array
     */
    protected array $supportedOrderTypes = [
        'market', 'limit', 'stop_loss', 'stop_loss_limit',
        'take_profit', 'take_profit_limit', 'limit_maker'
    ];

    /**
     * Конструктор.
     *
     * @param int|null $exchangeId ID біржі.
     * @throws \Exception
     */
    public function __construct(?int $exchangeId = null)
    {
        if (!$exchangeId) {
            $exchange = ExchangeModel::where('slug', 'binance')->first();
            if ($exchange) {
                $exchangeId = $exchange->id;
            }
        }

        parent::__construct($exchangeId);
    }

    /**
     * Конвертувати символ торгової пари до формату, який використовується на Binance.
     *
     * @param string $symbol Символ торгової пари.
     * @return string
     */
    public function convertSymbol(string $symbol): string
    {
        // Binance використовує формат без розділювачів, наприклад, BTCUSDT замість BTC/USDT
        return str_replace('/', '', $symbol);
    }

    /**
     * Отримати інформацію про доступні ф'ючерси.
     *
     * @return array
     */
    public function getFuturesMarkets(): array
    {
        return $this->safeRequest(function () {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $markets = $this->exchange->loadMarkets(true);
            $result = [];

            foreach ($markets as $symbol => $market) {
                $result[$symbol] = [
                    'symbol' => $symbol,
                    'base' => $market['base'],
                    'quote' => $market['quote'],
                    'active' => $market['active'],
                    'precision' => $market['precision'],
                    'limits' => $market['limits'],
                    'info' => $market['info'],
                    'type' => 'future',
                ];
            }

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return $result;
        });
    }

    /**
     * Отримати інформацію про ф'ючерсну позицію.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getFuturePosition(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $positions = $this->exchange->fetchPositions([$symbol]);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            if (empty($positions)) {
                return null;
            }

            return $this->formatPosition($positions[0]);
        });
    }

    /**
     * Отримати всі відкриті ф'ючерсні позиції.
     *
     * @return array
     */
    public function getAllFuturePositions(): array
    {
        return $this->safeRequest(function () {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $positions = $this->exchange->fetchPositions();

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return array_map(function ($position) {
                return $this->formatPosition($position);
            }, $positions);
        });
    }

    /**
     * Отримати інформацію про маржинальні позиції.
     *
     * @return array
     */
    public function getMarginPositions(): array
    {
        return $this->safeRequest(function () {
            // Переключення на маржинальний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'margin';

            $positions = $this->exchange->fetchPositions();

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return array_map(function ($position) {
                return $this->formatPosition($position);
            }, $positions);
        });
    }

    /**
     * Отримати інформацію про кредитне плече для торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getLeverage(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $leverage = $this->exchange->fetchLeverage($symbol);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'leverage' => $leverage['leverage'],
                'maxLeverage' => $leverage['maxLeverage'],
            ];
        });
    }

    /**
     * Встановити кредитне плече для торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int $leverage Кредитне плече.
     * @return array
     */
    public function setLeverage(string $symbol, int $leverage): array
    {
        return $this->safeRequest(function () use ($symbol, $leverage) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $result = $this->exchange->setLeverage($leverage, $symbol);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'leverage' => $leverage,
                'result' => $result,
            ];
        });
    }

    /**
     * Отримати режим позиції (hedge або one-way).
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getPositionMode(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $result = $this->exchange->fapiPrivateGetPositionSideDual();

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'dualSidePosition' => $result['dualSidePosition'],
                'mode' => $result['dualSidePosition'] ? 'hedge' : 'one-way',
            ];
        });
    }

    /**
     * Встановити режим позиції (hedge або one-way).
     *
     * @param bool $hedgeMode True для hedge mode, false для one-way mode.
     * @return array
     */
    public function setPositionMode(bool $hedgeMode): array
    {
        return $this->safeRequest(function () use ($hedgeMode) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $result = $this->exchange->fapiPrivatePostPositionSideDual([
                'dualSidePosition' => $hedgeMode,
            ]);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'dualSidePosition' => $hedgeMode,
                'mode' => $hedgeMode ? 'hedge' : 'one-way',
                'result' => $result,
            ];
        });
    }

    /**
     * Отримати історію фандингу для ф'ючерсів.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість записів.
     * @return array
     */
    public function getFundingHistory(string $symbol, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $result = $this->exchange->fetchFundingHistory($symbol, $since, $limit);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return $result;
        });
    }

    /**
     * Отримати поточну ставку фандингу.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getFundingRate(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $result = $this->exchange->fetchFundingRate($symbol);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'fundingRate' => $result['fundingRate'],
                'fundingTimestamp' => $result['fundingTimestamp'],
                'fundingDatetime' => $result['fundingDatetime'],
                'nextFundingRate' => $result['nextFundingRate'] ?? null,
                'nextFundingTimestamp' => $result['nextFundingTimestamp'] ?? null,
                'nextFundingDatetime' => $result['nextFundingDatetime'] ?? null,
            ];
        });
    }

    /**
     * Встановити тип маржі для ф'ючерсних позицій (cross або isolated).
     *
     * @param string $symbol Символ торгової пари.
     * @param bool $isolated True для isolated margin, false для cross margin.
     * @return array
     */
    public function setMarginType(string $symbol, bool $isolated): array
    {
        return $this->safeRequest(function () use ($symbol, $isolated) {
            // Переключення на ф'ючерсний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'future';

            $marginType = $isolated ? 'ISOLATED' : 'CROSSED';

            $result = $this->exchange->fapiPrivatePostMarginType([
                'symbol' => $this->convertSymbol($symbol),
                'marginType' => $marginType,
            ]);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'marginType' => $marginType,
                'result' => $result,
            ];
        });
    }

    /**
     * Форматувати дані позиції.
     *
     * @param array $position Дані позиції.
     * @return array
     */
    protected function formatPosition(array $position): array
    {
        return [
            'symbol' => $position['symbol'],
            'timestamp' => $position['timestamp'] ?? time() * 1000,
            'datetime' => $position['datetime'] ?? gmdate('Y-m-d H:i:s'),
            'initialMargin' => $position['initialMargin'] ?? 0,
            'initialMarginPercentage' => $position['initialMarginPercentage'] ?? 0,
            'maintenanceMargin' => $position['maintenanceMargin'] ?? 0,
            'maintenanceMarginPercentage' => $position['maintenanceMarginPercentage'] ?? 0,
            'entryPrice' => $position['entryPrice'] ?? 0,
            'notional' => $position['notional'] ?? 0,
            'leverage' => $position['leverage'] ?? 0,
            'unrealizedPnl' => $position['unrealizedPnl'] ?? 0,
            'contracts' => $position['contracts'] ?? 0,
            'contractSize' => $position['contractSize'] ?? 0,
            'marginRatio' => $position['marginRatio'] ?? 0,
            'collateral' => $position['collateral'] ?? 0,
            'marginMode' => $position['marginMode'] ?? 'cross',
            'side' => $position['side'] ?? 'none',
            'percentage' => $position['percentage'] ?? 0,
            'liquidationPrice' => $position['liquidationPrice'] ?? 0,
            'marginType' => $position['marginType'] ?? 'cross',
        ];
    }
}
