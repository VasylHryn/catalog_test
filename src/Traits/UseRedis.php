<?php

namespace App\Traits;

use Predis\Client;

/**
 * Trait for Redis connection management
 */
trait UseRedis
{
    /**
     * @var Client|null Redis client instance
     */
    private ?Client $redis = null;

    /**
     * Get Redis client instance
     *
     * Creates new connection if not exists
     */
    private function getRedis(): Client
    {
        if ($this->redis === null) {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port' => $_ENV['REDIS_PORT'] ?? 6379,
            ]);
        }
        return $this->redis;
    }
}