<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Redis configuration and connection singleton
 *
 * Provides Redis connection instance with configured parameters
 */
class Redis
{
    private static ?self $instance = null;
    private \Redis $connection;

    /**
     * Initialize Redis connection with environment configuration
     */
    private function __construct()
    {
        $this->connection = new \Redis();
        $this->connection->connect(
            $_ENV['REDIS_HOST'],
            (int)$_ENV['REDIS_PORT']
        );
    }

    /**
     * Get singleton instance of Redis
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get Redis connection instance
     *
     * @return \Redis
     */
    public function getConnection(): \Redis
    {
        return $this->connection;
    }
}