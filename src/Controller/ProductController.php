<?php

namespace App\Controller;

use App\Database\Database;
use App\Traits\UseRedis;
use PDO;

/**
 * Controller for managing products listing and filtering
 *
 * Handles product listing with pagination, sorting and filtering capabilities
 * Uses database and Redis caching for optimal performance
 */
class ProductController
{
    use UseRedis;

    /**
     * @var PDO Database connection instance
     */
    private PDO $db;

    /**
     * @var int Cache time to live in seconds
     */
    private const CACHE_TTL = 3600;

    /**
     * Initialize controller with database connection
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get list of products with filtering, sorting and pagination
     *
     * @param int $page Page number
     * @param int $limit Number of products per page
     * @param string|null $sortBy Sorting field and direction
     * @param array $filters Array of filters ['param_slug' => ['value1', 'value2']]
     * @return array{
     *     data: array,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int
     *     }
     * }
     */
    public function list(int $page = 1, int $limit = 10, ?string $sortBy = null, array $filters = []): array
    {
        try {
            error_log("Starting product list with params: " . json_encode([
                    'page' => $page,
                    'limit' => $limit,
                    'sortBy' => $sortBy,
                    'filters' => $filters
                ]));

            $filteredIds = !empty($filters) ? $this->getFilteredProductIds($filters) : null;

            // Main query
            $query = "SELECT p.* FROM products p";
            $params = [];

            // Filter by id
            if (isset($filteredIds)) {
                if (empty($filteredIds)) {
                    return [
                        'data' => [],
                        'meta' => [
                            'current_page' => $page,
                            'per_page' => $limit,
                            'total' => 0,
                            'last_page' => 1
                        ]
                    ];
                }
                $query .= " WHERE p.id IN (" . implode(',', $filteredIds) . ")";
            }

            // Count main quantity
            $countQuery = "SELECT COUNT(*) FROM ($query) as counted";
            $totalRows = (int)$this->db->query($countQuery)->fetchColumn();

            // Add sorting
            $query .= match ($sortBy) {
                'price_asc' => " ORDER BY p.price ASC",
                'price_desc' => " ORDER BY p.price DESC",
                default => " ORDER BY p.id ASC"
            };

            // Add pagination
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT $limit OFFSET $offset";

            error_log("Executing query: $query");
            error_log("With params: " . json_encode($params));

            // Do query
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [
                'data' => $products,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalRows,
                    'last_page' => ceil($totalRows / $limit)
                ]
            ];

            error_log("Returning result with " . count($products) . " products");
            return $result;

        } catch (\Exception $e) {
            error_log("Error in ProductController::list: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get product IDs matching given filters
     *
     * @param array $filters Array of filters ['param_slug' => ['value1', 'value2']]
     * @return array Array of product IDs or [-1] if no matches found
     */
    public function getFilteredProductIds(array $filters): array
    {
        try {
            if (empty($filters)) {
                return [];
            }

            // Logging
            error_log("Input filters: " . json_encode($filters, JSON_UNESCAPED_UNICODE));

            $subqueries = [];
            $params = [];

            foreach ($filters as $paramSlug => $values) {
                if (empty($values)) continue;

                $values = (array)$values;
                $placeholders = rtrim(str_repeat('?,', count($values)), ',');

                $subqueries[] = "
                SELECT pp.product_id
                FROM product_parameters pp
                JOIN parameter_values pv ON pv.id = pp.parameter_value_id
                JOIN parameters p ON p.id = pv.parameter_id
                WHERE p.slug = ? 
                AND LOWER(pv.value) IN (" .
                    implode(',', array_fill(0, count($values), 'LOWER(?)')) .
                    ")
            ";

                $params[] = $paramSlug;
                $params = array_merge($params, $values);

                // Logging
                error_log("Option: $paramSlug, Value: " . json_encode($values, JSON_UNESCAPED_UNICODE));
            }

            if (empty($subqueries)) {
                return [];
            }

            $query = implode("\nINTERSECT\n", $subqueries);

            // Final query logging
            error_log("SQL query: $query");
            error_log("Options: " . json_encode($params, JSON_UNESCAPED_UNICODE));

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            error_log("Products found: " . count($ids));

            return $ids ?: [-1];

        } catch (\Exception $e) {
            error_log("Error in getFilteredProductIds: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}