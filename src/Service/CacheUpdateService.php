<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\RedisCache;
use App\Database\Database;
use PDO;

/**
 * Service for updating Redis cache with product parameters
 *
 * Handles synchronization between database and Redis cache
 * by storing product IDs in sets grouped by parameter values
 */
class CacheUpdateService
{
    /**
     * @var PDO Database connection instance
     */
    private PDO $db;

    /**
     * @var \Predis\Client Redis client instance
     */
    private \Predis\Client $redis;

    /**
     * Initialize service with database and Redis connections
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->redis = RedisCache::getInstance();
    }

    /**
     * Update Redis cache with product parameters
     *
     * Process:
     * 1. Clears existing cache
     * 2. Fetches all parameter-value-product relationships
     * 3. Groups products by parameter values in Redis sets
     *
     * Uses Redis pipeline for better performance
     */
    public function updateCache(): void
    {
        $this->redis->pipeline(function ($pipe) {
            $pipe->flushdb();

            $stmt = $this->db->query("
                SELECT 
                    p.slug as param_slug,
                    pv.value,
                    pp.product_id
                FROM parameters p
                JOIN parameter_values pv ON pv.parameter_id = p.id
                JOIN product_parameters pp ON pp.parameter_value_id = pv.id
            ");

            $sets = [];
            while ($row = $stmt->fetch()) {
                $key = RedisCache::getParameterKey($row['param_slug'], $row['value']);
                $pipe->sadd($key, $row['product_id']);
            }
        });
    }
}