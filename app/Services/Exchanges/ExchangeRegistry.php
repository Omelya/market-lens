<?php

namespace App\Services\Exchanges;

use App\Interfaces\ExchangeInterface;
use Exception;

class ExchangeRegistry
{
    /**
     * Масив екземплярів бірж, індексованих за ключем.
     *
     * @var array<string, ExchangeInterface>
     */
    private static array $instances = [];

    /**
     * Отримати екземпляр біржі за ключем або створити новий.
     *
     * @param string $key Ключ для ідентифікації біржі.
     * @param callable $factory Функція, яка створює екземпляр біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function get(string $key, callable $factory): ExchangeInterface
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = $factory();
        }

        return self::$instances[$key];
    }

    /**
     * Отримати екземпляр біржі для публічного API.
     *
     * @param string $slug Slug біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function getPublic(string $slug): ExchangeInterface
    {
        return self::get("public_{$slug}", function () use ($slug) {
            return ExchangeFactory::createPublic($slug);
        });
    }

    /**
     * Отримати екземпляр біржі з API-ключем.
     *
     * @param int $apiKeyId ID API-ключа.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function getWithApiKey(int $apiKeyId): ExchangeInterface
    {
        return self::get("api_key_{$apiKeyId}", function () use ($apiKeyId) {
            return ExchangeFactory::createWithApiKey($apiKeyId);
        });
    }

    /**
     * Отримати екземпляр біржі з API-ключем користувача.
     *
     * @param int $userId ID користувача.
     * @param int $exchangeId ID біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function getWithUserAccount(int $userId, int $exchangeId): ExchangeInterface
    {
        return self::get("user_{$userId}_exchange_{$exchangeId}", function () use ($userId, $exchangeId) {
            return ExchangeFactory::createWithUserAccount($userId, $exchangeId);
        });
    }

    /**
     * Видалити екземпляр біржі з реєстру.
     *
     * @param string $key Ключ для ідентифікації біржі.
     * @return void
     */
    public static function remove(string $key): void
    {
        if (isset(self::$instances[$key])) {
            unset(self::$instances[$key]);
        }
    }

    /**
     * Видалити всі екземпляри бірж з реєстру.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$instances = [];
    }

    /**
     * Перезавантажити екземпляр біржі.
     *
     * @param string $key Ключ для ідентифікації біржі.
     * @param callable $factory Функція, яка створює екземпляр біржі.
     * @return ExchangeInterface
     * @throws Exception
     */
    public static function reload(string $key, callable $factory): ExchangeInterface
    {
        self::remove($key);
        return self::get($key, $factory);
    }
}
