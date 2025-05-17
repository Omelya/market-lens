<?php

namespace App\Services\Exchanges;

use App\Interfaces\ExchangeInterface;
use App\Models\Exchange as ExchangeModel;
use App\Models\ExchangeApiKey;
use Exception;

class ExchangeFactory
{
    /**
     * Створити екземпляр біржі за ID.
     *
     * @param int $exchangeId ID біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function createById(int $exchangeId): ExchangeInterface
    {
        $exchange = ExchangeModel::findOrFail($exchangeId);
        return self::createBySlug($exchange->slug, $exchangeId);
    }

    /**
     * Створити екземпляр біржі за slug.
     *
     * @param string $slug Slug біржі.
     * @param int|null $exchangeId ID біржі (якщо відомо).
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function createBySlug(string $slug, ?int $exchangeId = null): ExchangeInterface
    {
        if (!$exchangeId) {
            $exchange = ExchangeModel::where('slug', $slug)->first();
            if (!$exchange) {
                throw new Exception("Exchange with slug '{$slug}' not found");
            }
            $exchangeId = $exchange->id;
        }

        switch ($slug) {
            case 'binance':
                return new BinanceExchange($exchangeId);
            case 'bybit':
                return new BybitExchange($exchangeId);
            case 'whitebit':
                return new WhiteBitExchange($exchangeId);
            default:
                throw new Exception("Exchange '{$slug}' not supported");
        }
    }

    /**
     * Створити екземпляр біржі з API-ключем.
     *
     * @param int $apiKeyId ID API-ключа.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function createWithApiKey(int $apiKeyId): ExchangeInterface
    {
        $apiKey = ExchangeApiKey::with('exchange')->findOrFail($apiKeyId);

        // Перевірка, чи API-ключ активний
        if (!$apiKey->is_active) {
            throw new Exception("API key is not active");
        }

        // Створення екземпляра біржі
        $exchange = self::createById($apiKey->exchange_id);

        // Налаштування екземпляра біржі
        $credentials = [
            'apiKey' => $apiKey->getDecryptedApiKey(),
            'secret' => $apiKey->getDecryptedApiSecret(),
        ];

        // Додавання парольної фрази, якщо вона є
        if ($apiKey->passphrase) {
            $credentials['password'] = $apiKey->getDecryptedPassphrase();
        }

        // Додавання опції для тестової мережі, якщо потрібно
        $options = [];
        if ($apiKey->is_test_net) {
            $options['testnet'] = true;
        }

        // Ініціалізація з'єднання з біржею
        $exchange->initialize($credentials, $options);

        return $exchange;
    }

    /**
     * Створити екземпляр біржі з API-ключем за обліковим записом користувача.
     *
     * @param int $userId ID користувача.
     * @param int $exchangeId ID біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function createWithUserAccount(int $userId, int $exchangeId): ExchangeInterface
    {
        $apiKey = ExchangeApiKey::where('user_id', $userId)
            ->where('exchange_id', $exchangeId)
            ->where('is_active', true)
            ->first();

        if (!$apiKey) {
            throw new Exception("Active API key for user {$userId} and exchange {$exchangeId} not found");
        }

        return self::createWithApiKey($apiKey->id);
    }

    /**
     * Створити екземпляр біржі без API-ключа (для публічних API).
     *
     * @param string $slug Slug біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function createPublic(string $slug): ExchangeInterface
    {
        $exchange = self::createBySlug($slug);

        // Ініціалізація з'єднання з публічним API
        $exchange->initialize([], []);

        return $exchange;
    }
}
