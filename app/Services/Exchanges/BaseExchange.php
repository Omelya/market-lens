<?php

namespace App\Services\Exchanges;

use App\Interfaces\ExchangeInterface;
use ccxt\Exchange;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Exchange as ExchangeModel;

abstract class BaseExchange implements ExchangeInterface
{
    /**
     * Екземпляр CCXT біржі.
     *
     * @var Exchange
     */
    protected Exchange $exchange;

    /**
     * Модель біржі.
     *
     * @var \App\Models\Exchange
     */
    protected ExchangeModel $model;

    /**
     * ID біржі.
     *
     * @var int
     */
    protected int $exchangeId;

    /**
     * Назва класу CCXT біржі.
     *
     * @var string
     */
    protected string $ccxtClass;

    /**
     * Список підтримуваних таймфреймів.
     *
     * @var array
     */
    protected array$supportedTimeframes = [];

    /**
     * Список підтримуваних типів ордерів.
     *
     * @var array
     */
    protected array $supportedOrderTypes = [];

    /**
     * Базовий конструктор.
     *
     * @param int|null $exchangeId ID біржі.
     * @throws \Exception
     */
    public function __construct(?int $exchangeId = null)
    {
        if ($exchangeId) {
            $this->exchangeId = $exchangeId;
            $this->model = ExchangeModel::findOrFail($exchangeId);
        }

        if (!$this->ccxtClass) {
            throw new Exception("CCXT class not defined for exchange");
        }
    }

    /**
     * Ініціалізація з'єднання з біржею з вказаними обліковими даними.
     *
     * @param array $credentials Облікові дані (API ключ, секрет, тощо).
     * @param array $options Додаткові параметри.
     * @return void
     * @throws \Exception
     */
    public function initialize(array $credentials, array $options = []): void
    {
        $class = "\\ccxt\\{$this->ccxtClass}";

        if (!class_exists($class)) {
            throw new Exception("CCXT class {$class} not found");
        }

        $config = array_merge([
            'enableRateLimit' => true,
        ], $credentials, $options);

        $this->exchange = new $class($config);
    }

    /**
     * Виконання захищеного API запиту з обробкою помилок.
     *
     * @param callable $callback Функція запиту.
     * @param mixed $default Значення за замовчуванням у випадку помилки.
     * @return mixed
     */
    protected function safeRequest(callable $callback, $default = []): mixed
    {
        try {
            if (!$this->exchange) {
                throw new Exception("Exchange not initialized");
            }

            return $callback();
        } catch (Exception $e) {
            Log::error('Exchange API Error', [
                'exchange' => $this->ccxtClass,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $default;
        }
    }

    /**
     * Отримати інформацію про біржу.
     *
     * @return array
     */
    public function getExchangeInfo(): array
    {
        return $this->safeRequest(function () {
            $markets = $this->exchange->loadMarkets();
            $timeframes = $this->exchange->timeframes ?? [];
            $currencies = $this->exchange->currencies ?? [];

            return [
                'id' => $this->exchange->id,
                'name' => $this->exchange->name,
                'timeframes' => $timeframes,
                'markets_count' => count($markets),
                'currencies_count' => count($currencies),
                'has' => $this->exchange->has,
            ];
        });
    }

    /**
     * Отримати список доступних торгових пар.
     *
     * @return array
     */
    public function getMarkets(): array
    {
        return $this->safeRequest(function () {
            $markets = $this->exchange->loadMarkets();
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
                ];
            }

            return $result;
        });
    }

    /**
     * Отримати тікер (поточну інформацію) для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getTicker(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            $ticker = $this->exchange->fetch_ticker($symbol);
            return $this->formatTicker($ticker);
        });
    }

    /**
     * Отримати тікери для всіх або вказаних торгових пар.
     *
     * @param array|null $symbols Список символів торгових пар (null для всіх пар).
     * @return array
     */
    public function getTickers(array $symbols = null): array
    {
        return $this->safeRequest(function () use ($symbols) {
            $tickers = $this->exchange->fetch_tickers($symbols);
            $result = [];

            foreach ($tickers as $symbol => $ticker) {
                $result[$symbol] = $this->formatTicker($ticker);
            }

            return $result;
        });
    }

    /**
     * Отримати дані книги ордерів для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int $limit Кількість рівнів (глибина).
     * @return array
     */
    public function getOrderBook(string $symbol, int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $limit) {
            $orderBook = $this->exchange->fetch_order_book($symbol, $limit);

            return [
                'symbol' => $symbol,
                'timestamp' => $orderBook['timestamp'],
                'datetime' => $orderBook['datetime'],
                'bids' => $orderBook['bids'],
                'asks' => $orderBook['asks'],
                'nonce' => $orderBook['nonce'],
            ];
        });
    }

    /**
     * Отримати історичні дані OHLCV для вказаної торгової пари та таймфрейму.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $timeframe Таймфрейм (1m, 5m, 15m, 30m, 1h, 4h, 1d, тощо).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість свічок.
     * @return array
     */
    public function getOHLCV(string $symbol, string $timeframe, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $timeframe, $since, $limit) {
            $candles = $this->exchange->fetch_ohlcv($symbol, $timeframe, $since, $limit);

            return array_map(function ($candle) {
                return [
                    'timestamp' => $candle[0],
                    'datetime' => $this->exchange->iso8601($candle[0]),
                    'open' => $candle[1],
                    'high' => $candle[2],
                    'low' => $candle[3],
                    'close' => $candle[4],
                    'volume' => $candle[5],
                ];
            }, $candles);
        });
    }

    /**
     * Отримати історію останніх угод для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість угод.
     * @return array
     */
    public function getTrades(string $symbol, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit) {
            $trades = $this->exchange->fetch_trades($symbol, $since, $limit);

            return array_map(function ($trade) {
                return [
                    'id' => $trade['id'],
                    'timestamp' => $trade['timestamp'],
                    'datetime' => $trade['datetime'],
                    'symbol' => $trade['symbol'],
                    'order' => $trade['order'] ?? null,
                    'type' => $trade['type'] ?? null,
                    'side' => $trade['side'],
                    'price' => $trade['price'],
                    'amount' => $trade['amount'],
                    'cost' => $trade['cost'],
                    'fee' => $trade['fee'],
                ];
            }, $trades);
        });
    }

    /**
     * Отримати баланс акаунту.
     *
     * @return array
     */
    public function getBalance(): array
    {
        return $this->safeRequest(function () {
            $balance = $this->exchange->fetch_balance();

            return [
                'free' => $balance['free'],
                'used' => $balance['used'],
                'total' => $balance['total'],
                'info' => $balance['info'],
            ];
        });
    }

    /**
     * Отримати інформацію про конкретний ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $orderId ID ордера.
     * @return array
     */
    public function getOrder(string $symbol, string $orderId): array
    {
        return $this->safeRequest(function () use ($symbol, $orderId) {
            $order = $this->exchange->fetch_order($orderId, $symbol);
            return $this->formatOrder($order);
        });
    }

    /**
     * Отримати відкриті ордери для вказаної торгової пари або всіх пар.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @return array
     */
    public function getOpenOrders(string $symbol = null): array
    {
        return $this->safeRequest(function () use ($symbol) {
            $orders = $this->exchange->fetch_open_orders($symbol);

            return array_map(function ($order) {
                return $this->formatOrder($order);
            }, $orders);
        });
    }

    /**
     * Отримати історію ордерів для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість ордерів.
     * @return array
     */
    public function getOrderHistory(string $symbol, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit) {
            $orders = $this->exchange->fetch_closed_orders($symbol, $since, $limit);

            return array_map(function ($order) {
                return $this->formatOrder($order);
            }, $orders);
        });
    }

    /**
     * Отримати історію угод (виконаних ордерів) для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість угод.
     * @return array
     */
    public function getMyTrades(string $symbol, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit) {
            $trades = $this->exchange->fetch_my_trades($symbol, $since, $limit);

            return array_map(function ($trade) {
                return [
                    'id' => $trade['id'],
                    'timestamp' => $trade['timestamp'],
                    'datetime' => $trade['datetime'],
                    'symbol' => $trade['symbol'],
                    'order' => $trade['order'] ?? null,
                    'type' => $trade['type'] ?? null,
                    'side' => $trade['side'],
                    'price' => $trade['price'],
                    'amount' => $trade['amount'],
                    'cost' => $trade['cost'],
                    'fee' => $trade['fee'],
                ];
            }, $trades);
        });
    }

    /**
     * Створити новий ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $type Тип ордера (market, limit, тощо).
     * @param string $side Сторона (buy, sell).
     * @param float $amount Кількість.
     * @param float|null $price Ціна (для лімітних ордерів).
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function createOrder(string $symbol, string $type, string $side, float $amount, ?float $price = null, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $type, $side, $amount, $price, $params) {
            $order = $this->exchange->create_order($symbol, $type, $side, $amount, $price, $params);
            return $this->formatOrder($order);
        });
    }

    /**
     * Скасувати ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $orderId ID ордера.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function cancelOrder(string $symbol, string $orderId, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $orderId, $params) {
            $result = $this->exchange->cancel_order($orderId, $symbol, $params);
            return $this->formatOrder($result);
        });
    }

    /**
     * Скасувати всі ордери для вказаної торгової пари.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @return array
     */
    public function cancelAllOrders(string $symbol = null): array
    {
        return $this->safeRequest(function () use ($symbol) {
            $result = $this->exchange->cancel_all_orders($symbol);

            if (is_array($result) && isset($result[0])) {
                return array_map(function ($order) {
                    return $this->formatOrder($order);
                }, $result);
            }

            return $result;
        });
    }

    /**
     * Отримати доступні таймфрейми для вказаної біржі.
     *
     * @return array
     */
    public function getTimeframes(): array
    {
        return $this->safeRequest(function () {
            return $this->exchange->timeframes ?? $this->supportedTimeframes;
        });
    }

    /**
     * Отримати обмеження для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getMarketLimits(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            $this->exchange->loadMarkets();

            if (!isset($this->exchange->markets[$symbol])) {
                throw new Exception("Market {$symbol} not found");
            }

            $market = $this->exchange->markets[$symbol];

            return [
                'symbol' => $symbol,
                'limits' => $market['limits'],
                'precision' => $market['precision'],
            ];
        });
    }

    /**
     * Конвертувати символ торгової пари до формату, який використовується на біржі.
     *
     * @param string $symbol Символ торгової пари.
     * @return string
     */
    public function convertSymbol(string $symbol): string
    {
        return $symbol;
    }

    /**
     * Форматувати дані тікера.
     *
     * @param array $ticker Дані тікера.
     * @return array
     */
    protected function formatTicker(array $ticker)
    {
        return [
            'symbol' => $ticker['symbol'],
            'timestamp' => $ticker['timestamp'],
            'datetime' => $ticker['datetime'],
            'high' => $ticker['high'],
            'low' => $ticker['low'],
            'bid' => $ticker['bid'],
            'ask' => $ticker['ask'],
            'open' => $ticker['open'],
            'close' => $ticker['close'],
            'last' => $ticker['last'],
            'change' => $ticker['change'],
            'percentage' => $ticker['percentage'],
            'average' => $ticker['average'],
            'baseVolume' => $ticker['baseVolume'],
            'quoteVolume' => $ticker['quoteVolume'],
        ];
    }

    /**
     * Форматувати дані ордера.
     *
     * @param array $order Дані ордера.
     * @return array
     */
    protected function formatOrder(array $order)
    {
        return [
            'id' => $order['id'],
            'timestamp' => $order['timestamp'],
            'datetime' => $order['datetime'],
            'symbol' => $order['symbol'],
            'type' => $order['type'],
            'side' => $order['side'],
            'price' => $order['price'],
            'amount' => $order['amount'],
            'cost' => $order['cost'],
            'filled' => $order['filled'],
            'remaining' => $order['remaining'],
            'status' => $order['status'],
            'fee' => $order['fee'],
            'trades' => $order['trades'] ?? [],
        ];
    }
}
