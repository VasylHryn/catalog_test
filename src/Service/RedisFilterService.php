<?php

namespace App\Service;

use Predis\Client;
use PDO;

/**
 * Service for managing product filtering and caching in Redis
 *
 * Provides functionality for:
 * - Filtering products based on parameters
 * - Caching filtered results
 * - Managing parameter statistics
 * - Updating cache after data imports
 */
class RedisFilterService
{
    private Client $redis;
    private PDO $db;
    private array $tempKeys = [];

    /**
     * Redis key prefixes and cache configuration
     */
    public const PREFIX_PRODUCT_SET = 'products:param:';
    public const PREFIX_ALL_PRODUCTS = 'all_products';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Initialize service with database connection
     *
     * @param PDO $db Database connection instance
     */
    public function __construct(PDO $db)
    {
        $this->redis = new Client([
            'scheme' => $_ENV['REDIS_SCHEME'] ?? 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
        ]);
        $this->db = $db;
    }

    /**
     * Update Redis cache after product import
     *
     * Updates:
     * - All products set
     * - Parameter-based product sets
     * - Parameter statistics
     * - Catalog statistics
     *
     * @throws \Exception If update fails
     */
    public function updateAfterImport(): void
    {
        try {
            $this->clearCache();

            $sql = "SELECT id FROM products";
            $stmt = $this->db->query($sql);
            $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($productIds)) {
                $chunks = array_chunk($productIds, 1000);
                foreach ($chunks as $chunk) {
                    $this->redis->sadd(self::PREFIX_ALL_PRODUCTS, ...array_map('strval', $chunk));
                }
            }

            $sql = "
                SELECT 
                    p.slug as param_slug,
                    pv.value,
                    GROUP_CONCAT(pp.product_id) as product_ids,
                    COUNT(DISTINCT pp.product_id) as count,
                    MIN(pr.price) as min_price,
                    MAX(pr.price) as max_price
                FROM parameters p
                JOIN parameter_values pv ON pv.parameter_id = p.id
                JOIN product_parameters pp ON pp.parameter_value_id = pv.id
                JOIN products pr ON pr.id = pp.product_id
                GROUP BY p.slug, pv.value
            ";

            $stmt = $this->db->query($sql);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $productIds = explode(',', $row['product_ids']);
                if (!empty($productIds)) {
                    $productIds = array_map('strval', $productIds);
                    $key = self::PREFIX_PRODUCT_SET . "{$row['param_slug']}:{$row['value']}";

                    $this->redis->pipeline(function ($pipe) use ($key, $productIds, $row) {
                        $pipe->del($key);
                        $pipe->sadd($key, ...$productIds);

                        $statsKey = "param:stats:{$key}";
                        $pipe->hmset($statsKey, [
                            'count' => (string)$row['count'],
                            'min_price' => (string)$row['min_price'],
                            'max_price' => (string)$row['max_price']
                        ]);
                        $pipe->expire($statsKey, self::CACHE_TTL);
                    });

                    $valuesKey = "param:values:{$row['param_slug']}";
                    $this->redis->sadd($valuesKey, $row['value']);
                }
            }

            $sql = "SELECT 
                COUNT(*) as total_products,
                MIN(price) as min_price,
                MAX(price) as max_price
            FROM products";

            $stmt = $this->db->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stats) {
                $this->redis->hmset('catalog:stats', array_map('strval', $stats));
                $this->redis->expire('catalog:stats', self::CACHE_TTL);
            }

        } catch (\Exception $e) {
            error_log("Error updating Redis cache after import: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get filtered products with pagination
     *
     * @param array $filters Parameter filters ['param_slug' => ['value1', 'value2']]
     * @param string $sort Sorting option ('price_asc', 'price_desc')
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Filtered products with pagination metadata
     * @throws \Exception If filtering fails
     */
    public function getFilteredProducts(array $filters, string $sort = '', int $page = 1, int $perPage = 20): array
    {
        try {
            $cacheKey = $this->createCacheKey($filters, $sort, $page, $perPage);
            $cachedResult = $this->redis->get($cacheKey);

            if ($cachedResult) {
                return json_decode($cachedResult, true);
            }

            $productIds = $this->getFilteredProductIds($filters);

            if (empty($productIds)) {
                $result = [
                    'total' => 0,
                    'items' => [],
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 0
                ];
                $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($result));
                return $result;
            }

            $sql = "SELECT * FROM products WHERE id IN (" . implode(',', $productIds) . ")";

            if ($sort) {
                $sql .= match ($sort) {
                    'price_asc' => ' ORDER BY price ASC',
                    'price_desc' => ' ORDER BY price DESC',
                    default => ' ORDER BY id ASC'
                };
            }

            $total = count($productIds);
            $totalPages = ceil($total / $perPage);

            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT $perPage OFFSET $offset";

            $stmt = $this->db->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [
                'total' => $total,
                'items' => $items,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ];

            $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($result));

            return $result;
        } catch (\Exception $e) {
            error_log("Error in getFilteredProducts: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get product IDs matching given filters
     *
     * @param array $filters Parameter filters
     * @return array Array of product IDs
     * @throws \Exception If filtering fails
     */
    private function getFilteredProductIds(array $filters): array
    {
        try {
            if (empty($filters)) {
                $result = $this->redis->smembers(self::PREFIX_ALL_PRODUCTS);
                return $result ?: [];
            }

            $sets = [];
            $this->tempKeys = [];

            foreach ($filters as $paramSlug => $values) {
                $paramSets = [];
                foreach ((array)$values as $value) {
                    $key = self::PREFIX_PRODUCT_SET . "{$paramSlug}:{$value}";
                    $paramSets[] = $key;
                }

                if (!empty($paramSets)) {
                    $unionKey = "temp:union:" . uniqid();
                    $this->redis->sunionstore($unionKey, ...$paramSets);
                    $sets[] = $unionKey;
                    $this->tempKeys[] = $unionKey;
                }
            }

            if (empty($sets)) {
                return [];
            }

            $resultKey = "temp:result:" . uniqid();
            $this->redis->sinterstore($resultKey, ...$sets);
            $this->tempKeys[] = $resultKey;

            $result = $this->redis->smembers($resultKey);

            if (!empty($this->tempKeys)) {
                $this->redis->del(...$this->tempKeys);
            }

            return $result ?: [];

        } catch (\Exception $e) {
            error_log("Error in getFilteredProductIds: " . $e->getMessage());
            if (!empty($this->tempKeys)) {
                $this->redis->del(...$this->tempKeys);
            }
            throw $e;
        }
    }

    /**
     * Get possible values for a parameter
     *
     * @param string $paramSlug Parameter identifier
     * @return array List of possible values
     * @throws \Exception If retrieval fails
     */
    public function getParameterValues(string $paramSlug): array
    {
        try {
            $valuesKey = "param:values:{$paramSlug}";
            $values = $this->redis->smembers($valuesKey);

            if (empty($values)) {
                $sql = "
                    SELECT DISTINCT pv.value
                    FROM parameter_values pv
                    JOIN parameters p ON p.id = pv.parameter_id
                    WHERE p.slug = ?
                ";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$paramSlug]);
                $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($values)) {
                    $this->redis->sadd($valuesKey, ...$values);
                    $this->redis->expire($valuesKey, self::CACHE_TTL);
                }
            }

            return $values;
        } catch (\Exception $e) {
            error_log("Error getting parameter values: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get statistics for a parameter value
     *
     * @param string $paramSlug Parameter identifier
     * @param string $value Parameter value
     * @return array Statistics (count, min_price, max_price)
     * @throws \Exception If retrieval fails
     */
    public function getParameterStats(string $paramSlug, string $value): array
    {
        try {
            $key = self::PREFIX_PRODUCT_SET . "{$paramSlug}:{$value}";
            $statsKey = "param:stats:{$key}";
            $stats = $this->redis->hgetall($statsKey);

            if (!$stats) {
                $sql = "
                    SELECT 
                        COUNT(DISTINCT pp.product_id) as count,
                        MIN(pr.price) as min_price,
                        MAX(pr.price) as max_price
                    FROM parameters p
                    JOIN parameter_values pv ON pv.parameter_id = p.id
                    JOIN product_parameters pp ON pp.parameter_value_id = pv.id
                    JOIN products pr ON pr.id = pp.product_id
                    WHERE p.slug = ? AND pv.value = ?
                ";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$paramSlug, $value]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($stats) {
                    $this->redis->hmset($statsKey, array_map('strval', $stats));
                    $this->redis->expire($statsKey, self::CACHE_TTL);
                }
            }

            return [
                'count' => (int)($stats['count'] ?? 0),
                'min_price' => (float)($stats['min_price'] ?? 0),
                'max_price' => (float)($stats['max_price'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log("Error getting parameter stats: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get overall catalog statistics
     *
     * @return array Statistics (total_products, min_price, max_price)
     * @throws \Exception If retrieval fails
     */
    public function getCatalogStats(): array
    {
        try {
            $stats = $this->redis->hgetall('catalog:stats');

            if (!$stats) {
                $sql = "SELECT 
                    COUNT(*) as total_products,
                    MIN(price) as min_price,
                    MAX(price) as max_price
                FROM products";

                $stmt = $this->db->query($sql);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($stats) {
                    $this->redis->hmset('catalog:stats', array_map('strval', $stats));
                    $this->redis->expire('catalog:stats', self::CACHE_TTL);
                }
            }

            return [
                'total_products' => (int)($stats['total_products'] ?? 0),
                'min_price' => (float)($stats['min_price'] ?? 0),
                'max_price' => (float)($stats['max_price'] ?? 0)
            ];
        } catch (\Exception $e) {
            error_log("Error getting catalog stats: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear all Redis cache entries
     *
     * @throws \Exception If clearing fails
     */
    public function clearCache(): void
    {
        try {
            $patterns = [
                self::PREFIX_PRODUCT_SET . '*',
                'products:filtered:*',
                'param:stats:*',
                'param:values:*'
            ];

            foreach ($patterns as $pattern) {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                }
            }

            // Видаляємо окремі ключі
            $this->redis->del(self::PREFIX_ALL_PRODUCTS);
            $this->redis->del('catalog:stats');
        } catch (\Exception $e) {
            error_log("Error clearing Redis cache: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate cache key for filtered results
     *
     * @param array $filters Applied filters
     * @param string $sort Sorting option
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return string Cache key
     */
    private function createCacheKey(array $filters, string $sort, int $page, int $perPage): string
    {
        $filterKey = empty($filters) ? 'none' : md5(json_encode($filters));
        return "products:filtered:{$filterKey}:{$sort}:{$page}:{$perPage}";
    }
}