<?php

namespace App\Services\Exchanges;

use App\Models\Exchange as ExchangeModel;

class WhiteBitExchange extends BaseExchange
{
    /**
     * Назва класу CCXT біржі.
     *
     * @var string
     */
    protected string $ccxtClass = 'whitebit';

    /**
     * Список підтримуваних таймфреймів.
     *
     * @var array
     */
    protected array $supportedTimeframes = [
        '1m', '5m', '15m', '30m',
        '1h', '4h', '12h',
        '1d', '1w'
    ];

    /**
     * Список підтримуваних типів ордерів.
     *
     * @var array
     */
    protected array $supportedOrderTypes = [
        'market', 'limit', 'stop_limit', 'stop_market'
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
            $exchange = ExchangeModel::where('slug', 'whitebit')->first();
            if ($exchange) {
                $exchangeId = $exchange->id;
            }
        }

        parent::__construct($exchangeId);
    }

    /**
     * Конвертувати символ торгової пари до формату, який використовується на WhiteBit.
     *
     * @param string $symbol Символ торгової пари.
     * @return string
     */
    public function convertSymbol(string $symbol): string
    {
        // WhiteBit використовує формат з нижнім підкресленням, наприклад, BTC_USDT замість BTC/USDT
        return str_replace('/', '_', $symbol);
    }

    /**
     * Отримати інформацію про ордербук з необхідною глибиною.
     *
     * @param string $symbol Символ торгової пари.
     * @param int $depth Глибина ордербуку (кількість рівнів).
     * @return array
     */
    public function getOrderBookWithDepth(string $symbol, int $depth = 100): array
    {
        return $this->safeRequest(function () use ($symbol, $depth) {
            // WhiteBit має спеціальний метод для отримання ордербуку
            $symbol = $this->convertSymbol($symbol);
            $result = $this->exchange->publicGetOrderbookSymbolDepth([
                'symbol' => $symbol,
                'depth' => $depth
            ]);

            return [
                'symbol' => $symbol,
                'timestamp' => time() * 1000,
                'datetime' => gmdate('Y-m-d H:i:s'),
                'bids' => $result['bids'] ?? [],
                'asks' => $result['asks'] ?? [],
            ];
        });
    }

    /**
     * Отримати статистику торгової пари за останні 24 години.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function get24hStats(string $symbol): array
    {
        return $this->safeRequest(function () use ($symbol) {
            $symbol = $this->convertSymbol($symbol);
            $result = $this->exchange->publicGetStatsSymbolHistory24([
                'symbol' => $symbol
            ]);

            return [
                'symbol' => $symbol,
                'high' => $result['high'] ?? 0,
                'low' => $result['low'] ?? 0,
                'volume' => $result['volume'] ?? 0,
                'quoteVolume' => $result['quoteVolume'] ?? 0,
                'change' => $result['change'] ?? 0,
                'changePercentage' => $result['changePercentage'] ?? 0,
                'lastPrice' => $result['lastPrice'] ?? 0,
                'timestamp' => $result['timestamp'] ?? (time() * 1000),
                'bidPrice' => $result['bidPrice'] ?? 0,
                'askPrice' => $result['askPrice'] ?? 0,
            ];
        });
    }

    /**
     * Отримати комісії користувача.
     *
     * @return array
     */
    public function getUserFees(): array
    {
        return $this->safeRequest(function () {
            $result = $this->exchange->privatePostProfileFees();

            if (isset($result['makerFee']) && isset($result['takerFee'])) {
                return [
                    'maker' => $result['makerFee'],
                    'taker' => $result['takerFee'],
                    'trade' => $result['tradeFee'] ?? null,
                    'withdraw' => $result['withdrawFee'] ?? null,
                ];
            }

            return [];
        });
    }

    /**
     * Отримати баланс гаманця.
     *
     * @param string|null $currency Валюта (null для всіх валют).
     * @return array
     */
    public function getWalletBalance(string $currency = null): array
    {
        return $this->safeRequest(function () use ($currency) {
            $result = $this->exchange->privatePostMainBalanceCurrencies([
                'currency' => $currency
            ]);

            if ($currency) {
                if (isset($result[$currency])) {
                    return [
                        'currency' => $currency,
                        'available' => $result[$currency]['available'] ?? 0,
                        'reserved' => $result[$currency]['reserved'] ?? 0,
                        'total' => ($result[$currency]['available'] ?? 0) + ($result[$currency]['reserved'] ?? 0),
                    ];
                }

                return null;
            }

            $balances = [];

            foreach ($result as $curr => $balance) {
                if (($balance['available'] > 0) || ($balance['reserved'] > 0)) {
                    $balances[$curr] = [
                        'currency' => $curr,
                        'available' => $balance['available'] ?? 0,
                        'reserved' => $balance['reserved'] ?? 0,
                        'total' => ($balance['available'] ?? 0) + ($balance['reserved'] ?? 0),
                    ];
                }
            }

            return $balances;
        });
    }

    /**
     * Створити лімітний ордер з додатковими параметрами.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $side Сторона (buy, sell).
     * @param float $amount Кількість.
     * @param float $price Ціна.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function createLimitOrderWithParams(string $symbol, string $side, float $amount, float $price, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $side, $amount, $price, $params) {
            // Додавання обов'язкових параметрів для WhiteBit
            $requestParams = array_merge([
                'market' => $this->convertSymbol($symbol),
                'side' => $side,
                'amount' => (string) $amount,
                'price' => (string) $price,
            ], $params);

            $result = $this->exchange->privatePostOrderNew($requestParams);

            return $this->formatOrder([
                'id' => $result['orderId'] ?? null,
                'timestamp' => $result['timestamp'] ?? time() * 1000,
                'datetime' => gmdate('Y-m-d H:i:s', ($result['timestamp'] ?? time()) / 1000),
                'symbol' => $symbol,
                'type' => 'limit',
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'cost' => $price * $amount,
                'filled' => 0,
                'remaining' => $amount,
                'status' => 'open',
                'fee' => null,
                'trades' => [],
                'info' => $result,
            ]);
        });
    }

    /**
     * Створити стоп-лімітний ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $side Сторона (buy, sell).
     * @param float $amount Кількість.
     * @param float $price Ціна.
     * @param float $stopPrice Стоп-ціна.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function createStopLimitOrder(string $symbol, string $side, float $amount, float $price, float $stopPrice, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $side, $amount, $price, $stopPrice, $params) {
            // Додавання параметрів для стоп-лімітного ордера
            $requestParams = array_merge([
                'market' => $this->convertSymbol($symbol),
                'side' => $side,
                'amount' => (string) $amount,
                'price' => (string) $price,
                'activation_price' => (string) $stopPrice,
            ], $params);

            $result = $this->exchange->privatePostOrderStopLimit($requestParams);

            return $this->formatOrder([
                'id' => $result['orderId'] ?? null,
                'timestamp' => $result['timestamp'] ?? time() * 1000,
                'datetime' => gmdate('Y-m-d H:i:s', ($result['timestamp'] ?? time()) / 1000),
                'symbol' => $symbol,
                'type' => 'stop_limit',
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'cost' => $price * $amount,
                'filled' => 0,
                'remaining' => $amount,
                'status' => 'open',
                'fee' => null,
                'trades' => [],
                'info' => $result,
            ]);
        });
    }

    /**
     * Отримати історію ордерів з додатковою фільтрацією.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість ордерів.
     * @param array $params Додаткові параметри фільтрації.
     * @return array
     */
    public function getOrderHistoryFiltered(string $symbol = null, ?int $since = null, ?int $limit = 100, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit, $params) {
            $requestParams = array_merge([
                'limit' => $limit,
            ], $params);

            if ($symbol) {
                $requestParams['market'] = $this->convertSymbol($symbol);
            }

            if ($since) {
                $requestParams['from'] = $since;
            }

            $result = $this->exchange->privatePostOrderHistory($requestParams);

            if (!isset($result['data'])) {
                return [];
            }

            return array_map(function ($order) {
                return $this->formatOrder($order);
            }, $result['data']);
        });
    }

    /**
     * Створити маркет-ордер з ціною для WhiteBit (особливість біржі).
     *
     * @param string $symbol Символ торгової пари.
     * @param string $side Сторона (buy, sell).
     * @param float $amount Кількість.
     * @param float|null $marketPrice Ринкова ціна (для розрахунку вартості).
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function createMarketOrderWithPrice(string $symbol, string $side, float $amount, ?float $marketPrice = null, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $side, $amount, $marketPrice, $params) {
            // Якщо не вказана ринкова ціна, отримуємо її
            if ($marketPrice === null) {
                $ticker = $this->getTicker($symbol);
                $marketPrice = $side === 'buy' ? $ticker['ask'] : $ticker['bid'];
            }

            // WhiteBit вимагає ціну навіть для маркет-ордерів
            $requestParams = array_merge([
                'market' => $this->convertSymbol($symbol),
                'side' => $side,
                'amount' => (string) $amount,
                'price' => (string) $marketPrice,
            ], $params);

            $result = $this->exchange->privatePostOrderMarket($requestParams);

            return $this->formatOrder([
                'id' => $result['orderId'] ?? null,
                'timestamp' => $result['timestamp'] ?? time() * 1000,
                'datetime' => gmdate('Y-m-d H:i:s', ($result['timestamp'] ?? time()) / 1000),
                'symbol' => $symbol,
                'type' => 'market',
                'side' => $side,
                'price' => $marketPrice,
                'amount' => $amount,
                'cost' => $marketPrice * $amount,
                'filled' => $amount, // Маркет-ордер зазвичай виконується одразу
                'remaining' => 0,
                'status' => 'closed',
                'fee' => null,
                'trades' => [],
                'info' => $result,
            ]);
        });
    }

    /**
     * Отримати історію угод з додатковою фільтрацією.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість угод.
     * @param array $params Додаткові параметри фільтрації.
     * @return array
     */
    public function getTradeHistoryFiltered(string $symbol = null, ?int $since = null, ?int $limit = 100, array $params = []): array
    {
        return $this->safeRequest(function () use ($symbol, $since, $limit, $params) {
            $requestParams = array_merge([
                'limit' => $limit,
            ], $params);

            if ($symbol) {
                $requestParams['market'] = $this->convertSymbol($symbol);
            }

            if ($since) {
                $requestParams['from'] = $since;
            }

            $result = $this->exchange->privatePostTradeHistory($requestParams);

            if (!isset($result['data'])) {
                return [];
            }

            return array_map(function ($trade) {
                return [
                    'id' => $trade['tradeId'] ?? null,
                    'timestamp' => $trade['timestamp'] ?? time() * 1000,
                    'datetime' => gmdate('Y-m-d H:i:s', ($trade['timestamp'] ?? time()) / 1000),
                    'symbol' => str_replace('_', '/', $trade['market'] ?? ''),
                    'order' => $trade['orderId'] ?? null,
                    'type' => $trade['type'] ?? null,
                    'side' => $trade['side'] ?? null,
                    'price' => $trade['price'] ?? 0,
                    'amount' => $trade['amount'] ?? 0,
                    'cost' => $trade['cost'] ?? 0,
                    'fee' => [
                        'cost' => $trade['fee'] ?? 0,
                        'currency' => $trade['feeCurrency'] ?? null,
                    ],
                ];
            }, $result['data']);
        });
    }

    /**
     * Отримати ліміти на виведення коштів.
     *
     * @param string $currency Валюта.
     * @return array
     */
    public function getWithdrawalFees(string $currency): array
    {
        return $this->safeRequest(function () use ($currency) {
            $result = $this->exchange->privatePostMainWithdrawalFee([
                'currency' => $currency
            ]);

            return [
                'currency' => $currency,
                'minAmount' => $result['minAmount'] ?? 0,
                'maxAmount' => $result['maxAmount'] ?? 0,
                'flatFee' => $result['flatFee'] ?? 0,
                'percentFee' => $result['percentFee'] ?? 0,
                'networkFee' => $result['networkFee'] ?? 0,
            ];
        });
    }

    /**
     * Отримати історію депозитів.
     *
     * @param string|null $currency Валюта (null для всіх валют).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість записів.
     * @return array
     */
    public function getDepositHistory(string $currency = null, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($currency, $since, $limit) {
            $params = [
                'limit' => $limit,
            ];

            if ($currency) {
                $params['currency'] = $currency;
            }

            if ($since) {
                $params['from'] = $since;
            }

            $result = $this->exchange->privatePostMainDepositHistory($params);

            if (!isset($result['data'])) {
                return [];
            }

            return array_map(function ($deposit) {
                return [
                    'id' => $deposit['id'] ?? null,
                    'timestamp' => $deposit['timestamp'] ?? time() * 1000,
                    'datetime' => gmdate('Y-m-d H:i:s', ($deposit['timestamp'] ?? time()) / 1000),
                    'currency' => $deposit['currency'] ?? null,
                    'amount' => $deposit['amount'] ?? 0,
                    'address' => $deposit['address'] ?? null,
                    'txid' => $deposit['transactionHash'] ?? null,
                    'status' => $deposit['status'] ?? null,
                    'type' => $deposit['type'] ?? null,
                ];
            }, $result['data']);
        });
    }

    /**
     * Отримати історію виведень.
     *
     * @param string|null $currency Валюта (null для всіх валют).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість записів.
     * @return array
     */
    public function getWithdrawalHistory(string $currency = null, ?int $since = null, ?int $limit = 100): array
    {
        return $this->safeRequest(function () use ($currency, $since, $limit) {
            $params = [
                'limit' => $limit,
            ];

            if ($currency) {
                $params['currency'] = $currency;
            }

            if ($since) {
                $params['from'] = $since;
            }

            $result = $this->exchange->privatePostMainWithdrawalHistory($params);

            if (!isset($result['data'])) {
                return [];
            }

            return array_map(function ($withdrawal) {
                return [
                    'id' => $withdrawal['id'] ?? null,
                    'timestamp' => $withdrawal['timestamp'] ?? time() * 1000,
                    'datetime' => gmdate('Y-m-d H:i:s', ($withdrawal['timestamp'] ?? time()) / 1000),
                    'currency' => $withdrawal['currency'] ?? null,
                    'amount' => $withdrawal['amount'] ?? 0,
                    'address' => $withdrawal['address'] ?? null,
                    'txid' => $withdrawal['transactionHash'] ?? null,
                    'status' => $withdrawal['status'] ?? null,
                    'fee' => $withdrawal['fee'] ?? 0,
                ];
            }, $result['data']);
        });
    }
}
