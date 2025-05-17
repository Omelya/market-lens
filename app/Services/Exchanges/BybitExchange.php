<?php

namespace App\Services\Exchanges;

use App\Models\Exchange as ExchangeModel;

class BybitExchange extends BaseExchange
{
    /**
     * Назва класу CCXT біржі.
     *
     * @var string
     */
    protected string $ccxtClass = 'bybit';

    /**
     * Список підтримуваних таймфреймів.
     *
     * @var array
     */
    protected array $supportedTimeframes = [
        '1m', '3m', '5m', '15m', '30m',
        '1h', '2h', '4h', '6h', '12h',
        '1d', '1w', '1M'
    ];

    /**
     * Список підтримуваних типів ордерів.
     *
     * @var array
     */
    protected array $supportedOrderTypes = [
        'market', 'limit', 'stop_market', 'stop_limit',
        'take_profit_market', 'take_profit_limit'
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
            $exchange = ExchangeModel::where('slug', 'bybit')->first();
            if ($exchange) {
                $exchangeId = $exchange->id;
            }
        }

        parent::__construct($exchangeId);
    }

    /**
     * Конвертувати символ торгової пари до формату, який використовується на Bybit.
     *
     * @param string $symbol Символ торгової пари.
     * @return string
     */
    public function convertSymbol(string $symbol): string
    {
        // Bybit використовує формат без розділювачів, наприклад, BTCUSDT замість BTC/USDT
        return str_replace('/', '', $symbol);
    }

    /**
     * Отримати інформацію про доступні ф'ючерси.
     *
     * @return array
     */
    public function getDerivativeMarkets(): array
    {
        return $this->safeRequest(function () {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            $markets = $this->exchange->loadMarkets(true);
            $result = [];

            foreach ($markets as $symbol => $market) {
                if ($market['swap']) {
                    $result[$symbol] = [
                        'symbol' => $symbol,
                        'base' => $market['base'],
                        'quote' => $market['quote'],
                        'active' => $market['active'],
                        'precision' => $market['precision'],
                        'limits' => $market['limits'],
                        'info' => $market['info'],
                        'type' => 'swap',
                    ];
                }
            }

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return $result;
        });
    }

    /**
     * Отримати інформацію про позицію на деривативному ринку.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getPosition(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

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
     * Отримати всі відкриті позиції.
     *
     * @return array
     */
    public function getAllPositions(): array
    {
        return $this->safeRequest(function () {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            $positions = $this->exchange->fetchPositions();

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return array_map(function ($position) {
                return $this->formatPosition($position);
            }, $positions);
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
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

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
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

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
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

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
     * Отримати страхові фонди для деривативів.
     *
     * @return array
     */
    public function getInsuranceFunds(): array
    {
        return $this->safeRequest(function () {
            // Використання приватного API Bybit для отримання страхових фондів
            $result = $this->exchange->privateGetV2PrivateWalletExchangeOrder();

            if (isset($result['result']) && isset($result['result']['currency'])) {
                return $result['result'];
            }

            return [];
        });
    }

    /**
     * Створити умовний ордер (conditional order) для деривативів.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $type Тип ордера.
     * @param string $side Сторона.
     * @param float $amount Кількість.
     * @param float|null $price Ціна.
     * @param float $triggerPrice Тригер-ціна.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function createConditionalOrder(string $symbol, string $type, string $side, float $amount, ?float $price, float $triggerPrice, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $type, $side, $amount, $price, $triggerPrice, $params) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            // Додавання тригер-ціни до параметрів
            $params['stopPrice'] = $triggerPrice;

            // Створення умовного ордера
            $order = $this->exchange->createOrder($symbol, $type, $side, $amount, $price, $params);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return $this->formatOrder($order);
        });
    }

    /**
     * Отримати відкриті умовні ордери.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @return array
     */
    public function getOpenConditionalOrders(string $symbol = null): array
    {
        return $this->safeRequest(function () use ($symbol) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            // Додавання параметра для отримання тільки умовних ордерів
            $params = ['stop' => true];

            $orders = $this->exchange->fetchOpenOrders($symbol, null, null, $params);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return array_map(function ($order) {
                return $this->formatOrder($order);
            }, $orders);
        });
    }

    /**
     * Скасувати умовний ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $orderId ID ордера.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function cancelConditionalOrder(string $symbol, string $orderId, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $orderId, $params) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            // Додавання параметра для умовних ордерів
            $params['stop'] = true;

            $result = $this->exchange->cancelOrder($orderId, $symbol, $params);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return $this->formatOrder($result);
        });
    }

    /**
     * Встановити режим позиції (one-way або hedge).
     *
     * @param string $symbol Символ торгової пари.
     * @param string $mode Режим ("MergedSingle" для one-way, "BothSide" для hedge).
     * @return array
     */
    public function setPositionMode(string $symbol, string $mode): array
    {
        return $this->safeRequest(function () use ($symbol, $mode) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            // Використання приватного API Bybit
            $params = [
                'symbol' => $this->convertSymbol($symbol),
                'mode' => $mode,
            ];

            $result = $this->exchange->v2PrivatePostPositionSwitchMode($params);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'mode' => $mode,
                'result' => $result,
            ];
        });
    }

    /**
     * Встановити тип маржі для позицій.
     *
     * @param string $symbol Символ торгової пари.
     * @param bool $isolated True для ізольованої маржі, false для крос-маржі.
     * @return array
     */
    public function setMarginType(string $symbol, bool $isolated): array
    {
        return $this->safeRequest(function () use ($symbol, $isolated) {
            // Переключення на деривативний API
            $currentOptions = $this->exchange->options;
            $this->exchange->options['defaultType'] = 'swap';

            // Використання приватного API Bybit
            $params = [
                'symbol' => $this->convertSymbol($symbol),
                'is_isolated' => $isolated,
                'buy_leverage' => $this->exchange->fetchPosition($symbol)['leverage'],
                'sell_leverage' => $this->exchange->fetchPosition($symbol)['leverage'],
            ];

            $result = $this->exchange->v2PrivatePostPositionSwitchIsolated($params);

            // Повернення до попередніх налаштувань
            $this->exchange->options = $currentOptions;

            return [
                'symbol' => $symbol,
                'isolated' => $isolated,
                'result' => $result,
            ];
        });
    }

    /**
     * Отримати гаманці користувача.
     *
     * @return array
     */
    public function getWallets(): array
    {
        return $this->safeRequest(function () {
            $result = $this->exchange->fetchBalance();

            // Форматування результату
            $wallets = [];

            if (isset($result['info']) && isset($result['info']['result'])) {
                foreach ($result['info']['result'] as $currency => $wallet) {
                    $wallets[$currency] = [
                        'currency' => $currency,
                        'available' => $wallet['available_balance'],
                        'total' => $wallet['wallet_balance'],
                        'used' => $wallet['wallet_balance'] - $wallet['available_balance'],
                        'equity' => $wallet['equity'] ?? $wallet['wallet_balance'],
                    ];
                }
            }

            return $wallets;
        });
    }

    /**
     * Отримати комісії користувача.
     *
     * @return array
     */
    public function getFees(): array
    {
        return $this->safeRequest(function () {
            // Використання приватного API Bybit для отримання комісій
            $result = $this->exchange->privateGetV2PrivateAccountFee();

            if (isset($result['result']) && isset($result['result']['taker_fee_rate'])) {
                return [
                    'taker' => $result['result']['taker_fee_rate'],
                    'maker' => $result['result']['maker_fee_rate'],
                ];
            }

            return [];
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
