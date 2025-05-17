<?php

namespace App\Interfaces;

interface ExchangeInterface
{
    /**
     * Ініціалізація з'єднання з біржею з вказаними обліковими даними.
     *
     * @param array $credentials Облікові дані (API ключ, секрет, тощо).
     * @param array $options Додаткові параметри.
     * @return void
     */
    public function initialize(array $credentials, array $options = []): void;

    /**
     * Отримати інформацію про біржу.
     *
     * @return array
     */
    public function getExchangeInfo(): array;

    /**
     * Отримати список доступних торгових пар.
     *
     * @return array
     */
    public function getMarkets(): array;

    /**
     * Отримати тікер (поточну інформацію) для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getTicker(string $symbol): array;

    /**
     * Отримати тікери для всіх або вказаних торгових пар.
     *
     * @param array|null $symbols Список символів торгових пар (null для всіх пар).
     * @return array
     */
    public function getTickers(array $symbols = null): array;

    /**
     * Отримати дані книги ордерів для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int $limit Кількість рівнів (глибина).
     * @return array
     */
    public function getOrderBook(string $symbol, int $limit = 100): array;

    /**
     * Отримати історичні дані OHLCV для вказаної торгової пари та таймфрейму.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $timeframe Таймфрейм (1m, 5m, 15m, 30m, 1h, 4h, 1d, тощо).
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість свічок.
     * @return array
     */
    public function getOHLCV(string $symbol, string $timeframe, ?int $since = null, ?int $limit = 100): array;

    /**
     * Отримати історію останніх угод для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість угод.
     * @return array
     */
    public function getTrades(string $symbol, ?int $since = null, ?int $limit = 100): array;

    /**
     * Отримати баланс акаунту.
     *
     * @return array
     */
    public function getBalance(): array;

    /**
     * Отримати інформацію про конкретний ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $orderId ID ордера.
     * @return array
     */
    public function getOrder(string $symbol, string $orderId): array;

    /**
     * Отримати відкриті ордери для вказаної торгової пари або всіх пар.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @return array
     */
    public function getOpenOrders(string $symbol = null): array;

    /**
     * Отримати історію ордерів для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість ордерів.
     * @return array
     */
    public function getOrderHistory(string $symbol, ?int $since = null, ?int $limit = 100): array;

    /**
     * Отримати історію угод (виконаних ордерів) для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @param int|null $since Час початку в мілісекундах (Unix timestamp).
     * @param int|null $limit Кількість угод.
     * @return array
     */
    public function getMyTrades(string $symbol, ?int $since = null, ?int $limit = 100): array;

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
    public function createOrder(string $symbol, string $type, string $side, float $amount, ?float $price = null, array $params = []): array;

    /**
     * Скасувати ордер.
     *
     * @param string $symbol Символ торгової пари.
     * @param string $orderId ID ордера.
     * @param array $params Додаткові параметри.
     * @return array
     */
    public function cancelOrder(string $symbol, string $orderId, array $params = []): array;

    /**
     * Скасувати всі ордери для вказаної торгової пари.
     *
     * @param string|null $symbol Символ торгової пари (null для всіх пар).
     * @return array
     */
    public function cancelAllOrders(string $symbol = null): array;

    /**
     * Отримати доступні таймфрейми для вказаної біржі.
     *
     * @return array
     */
    public function getTimeframes(): array;

    /**
     * Отримати обмеження для вказаної торгової пари.
     *
     * @param string $symbol Символ торгової пари.
     * @return array
     */
    public function getMarketLimits(string $symbol): array;

    /**
     * Конвертувати символ торгової пари до формату, який використовується на біржі.
     *
     * @param string $symbol Символ торгової пари.
     * @return string
     */
    public function convertSymbol(string $symbol): string;
}
