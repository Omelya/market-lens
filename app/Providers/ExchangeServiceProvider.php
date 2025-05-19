<?php

namespace App\Providers;

use App\Interfaces\ExchangeInterface;
use App\Services\Exchanges\BinanceExchange;
use App\Services\Exchanges\BybitExchange;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\WhiteBitExchange;
use Illuminate\Support\ServiceProvider;

class ExchangeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Реєстрація сервісу фабрики бірж
        $this->app->singleton('exchange.factory', function ($app) {
            return new ExchangeFactory();
        });

        // Реєстрація сервісу для Binance
        $this->app->bind('exchange.binance', function ($app) {
            return new BinanceExchange();
        });

        // Реєстрація сервісу для Bybit
        $this->app->bind('exchange.bybit', function ($app) {
            return new BybitExchange();
        });

        // Реєстрація сервісу для WhiteBit
        $this->app->bind('exchange.whitebit', function ($app) {
            return new WhiteBitExchange();
        });

        // Реєстрація сервісу для шлюзу до бірж
        $this->app->singleton('exchange.gateway', function ($app) {
            return static function (string $slug) {
                return ExchangeFactory::createPublic($slug);
            };
        });

        // Реєстрація інтерфейсу для впровадження в контролери
        $this->app->bind(ExchangeInterface::class, function ($app, $parameters) {
            if (isset($parameters['slug'])) {
                return ExchangeFactory::createBySlug($parameters['slug']);
            }

            if (isset($parameters['id'])) {
                return ExchangeFactory::createById($parameters['id']);
            }

            if (isset($parameters['apiKey'])) {
                return ExchangeFactory::createWithApiKey($parameters['apiKey']);
            }

            throw new \RuntimeException('Cannot resolve ExchangeInterface: missing parameters');
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Вказує, чи відкладається завантаження провайдера.
     *
     * @var bool
     */
    protected bool $defer = true;

    /**
     * Отримати сервіси, надані провайдером.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'exchange.factory',
            'exchange.binance',
            'exchange.bybit',
            'exchange.whitebit',
            'exchange.gateway',
            ExchangeInterface::class,
        ];
    }
}
