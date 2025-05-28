<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client;

class RedisCache
{
    private static ?Client $instance = null;
    private const CACHE_LIFETIME = 3600; // 1 hour

    public static function getInstance(): Client
    {
        if (self::$instance === null) {
            self::$instance = new Client([
                'scheme' => 'tcp',
                'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port'   => $_ENV['REDIS_PORT'] ?? 6379,
                'password' => $_ENV['REDIS_PASSWORD'] ?? null
            ]);
        }

        return self::$instance;
    }

    /**
     * Generates a key for a set of products by parameter
     */
    public static function getParameterKey(string $paramSlug, string $value): string
    {
        return "catalog:param:{$paramSlug}:{$value}";
    }

    /**
     * Generates a key for the filter cache
     */
    public static function getFiltersKey(array $activeFilters = []): string
    {
        if (empty($activeFilters)) {
            return "catalog:filters";
        }

        ksort($activeFilters);
        return "catalog:filters:" . md5(json_encode($activeFilters));
    }
}