<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Получить значение из кеша
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            report($e);
            return $default;
        }
    }

    /**
     * Сохранить значение в кеш
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Время жизни в секундах (null = навсегда)
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            if ($ttl === null) {
                return Cache::forever($key, $value);
            }

            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Проверить существование ключа
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Удалить значение из кеша
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    /**
     * Получить или установить значение в кеш
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            report($e);
            return $callback();
        }
    }

    /**
     * Увеличить значение счетчика
     *
     * @param string $key
     * @param int $value
     * @return int
     */
    public function increment(string $key, int $value = 1): int
    {
        return Cache::increment($key, $value);
    }

    /**
     * Уменьшить значение счетчика
     *
     * @param string $key
     * @param int $value
     * @return int
     */
    public function decrement(string $key, int $value = 1): int
    {
        return Cache::decrement($key, $value);
    }

    /**
     * Очистить весь кеш
     *
     * @return bool
     */
    public function flush(): bool
    {
        return Cache::flush();
    }

    /**
     * Работа напрямую с Redis (для сложных операций)
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function redis()
    {
        return Redis::connection();
    }

    /**
     * Установить значение с истечением срока действия (Redis команда)
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds
     * @return bool
     */
    public function setex(string $key, $value, int $seconds): bool
    {
        return (bool) Redis::setex($key, $seconds, $value);
    }

    /**
     * Получить все ключи по паттерну
     *
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern = '*'): array
    {
        return Redis::keys($pattern);
    }

    /**
     * Установить TTL для существующего ключа
     *
     * @param string $key
     * @param int $seconds
     * @return bool
     */
    public function expire(string $key, int $seconds): bool
    {
        return (bool) Redis::expire($key, $seconds);
    }

    /**
     * Получить TTL ключа
     *
     * @param string $key
     * @return int
     */
    public function ttl(string $key): int
    {
        return Redis::ttl($key);
    }
}

