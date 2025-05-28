<?php

namespace App\Controller;

use App\Database\Database;
use App\Traits\UseRedis;
use PDO;

/**
 * Controller for managing product parameters and filters
 */
class ParameterController
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
     * @var array Parameters to exclude from filters
     */
    private array $excludedParameters = [
        'angl-yske-naymenuvannya',
        'english_name'
    ];

    /**
     * Initialize controller with database connection
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get list of available filters considering active ones
     *
     * @param array $activeFilters Array of active filters ['param_slug' => ['value1', 'value2']]
     * @return array Array of available filters with product counts for each value
     */
    public function getFilters(array $activeFilters = []): array
    {
        try {
            error_log("Starting getFilters with active filters: " . json_encode($activeFilters));

            // Basic query to get filtered items
            $baseQuery = "
            WITH FilteredProducts AS (
                SELECT DISTINCT pp.product_id 
                FROM product_parameters pp
                JOIN parameter_values pv ON pv.id = pp.parameter_value_id
                JOIN parameters p ON p.id = pv.parameter_id
                WHERE 1=1
        ";

            $params = [];

            // If we have filter related to brand we apply it firstly
            if (isset($activeFilters['brend'])) {
                $brandValues = (array)$activeFilters['brend'];
                $placeholders = rtrim(str_repeat('?,', count($brandValues)), ',');

                $baseQuery .= "
                AND EXISTS (
                    SELECT 1 
                    FROM product_parameters pp2
                    JOIN parameter_values pv2 ON pv2.id = pp2.parameter_value_id
                    JOIN parameters p2 ON p2.id = pv2.parameter_id
                    WHERE pp2.product_id = pp.product_id
                    AND p2.slug = 'brend'
                    AND LOWER(pv2.value) IN (" . implode(',', array_fill(0, count($brandValues), 'LOWER(?)')) . ")
                )
            ";
                $params = array_merge($params, $brandValues);
            }

            // Adding additional filters
            foreach ($activeFilters as $paramSlug => $values) {
                if ($paramSlug === 'brend') continue; // Skip brand if it already processed

                if (!empty($values)) {
                    $values = (array)$values;
                    $placeholders = rtrim(str_repeat('?,', count($values)), ',');

                    $baseQuery .= "
                    AND EXISTS (
                        SELECT 1 
                        FROM product_parameters pp2
                        JOIN parameter_values pv2 ON pv2.id = pp2.parameter_value_id
                        JOIN parameters p2 ON p2.id = pv2.parameter_id
                        WHERE pp2.product_id = pp.product_id
                        AND p2.slug = ?
                        AND LOWER(pv2.value) IN (" . implode(',', array_fill(0, count($values), 'LOWER(?)')) . ")
                    )
                ";
                    $params[] = $paramSlug;
                    $params = array_merge($params, $values);
                }
            }

            $baseQuery .= ")";

            // Main query to get filters
            $query = "
            SELECT 
                p.name,
                p.slug,
                pv.value,
                COUNT(DISTINCT CASE 
                    WHEN fp.product_id IS NOT NULL THEN pp.product_id 
                END) as product_count
            FROM parameters p
            JOIN parameter_values pv ON pv.parameter_id = p.id
            LEFT JOIN product_parameters pp ON pp.parameter_value_id = pv.id
            LEFT JOIN FilteredProducts fp ON fp.product_id = pp.product_id
            WHERE p.slug NOT IN ('" . implode("','", $this->excludedParameters) . "')
        ";

            // If there are active filters, show only values with products
            if (!empty($activeFilters)) {
                $query .= "
                AND (
                    EXISTS (
                        SELECT 1
                        FROM product_parameters pp2
                        WHERE pp2.parameter_value_id = pv.id
                        AND pp2.product_id IN (SELECT product_id FROM FilteredProducts)
                    )
                    OR p.slug = 'brend' 
                    OR EXISTS (
                        SELECT 1
                        FROM parameter_values pv2
                        JOIN product_parameters pp2 ON pp2.parameter_value_id = pv2.id
                        WHERE pv2.parameter_id = p.id
                        AND pp2.product_id IN (SELECT product_id FROM FilteredProducts)
                    )
                )
            ";
            }

            $query .= "
            GROUP BY p.name, p.slug, pv.value
            HAVING product_count > 0 OR p.slug = 'brend'
            ORDER BY 
                CASE WHEN p.slug = 'brend' THEN 0 ELSE 1 END,
                p.name,
                pv.value
        ";

            error_log("Executing query: " . $baseQuery . $query);
            error_log("With params: " . json_encode($params));

            $stmt = $this->db->prepare($baseQuery . $query);
            $stmt->execute($params);

            $filters = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($filters[$row['slug']])) {
                    $filters[$row['slug']] = [
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'values' => []
                    ];
                }

                $isActive = isset($activeFilters[$row['slug']]) &&
                    in_array($row['value'], (array)$activeFilters[$row['slug']]);

                // Add the value only if it is active or has goods
                if ($row['slug'] === 'brend' || $isActive || $row['product_count'] > 0) {
                    $filters[$row['slug']]['values'][] = [
                        'value' => $row['value'],
                        'count' => (int)$row['product_count'],
                        'active' => $isActive
                    ];
                }
            }

            // Sort values in every filter
            foreach ($filters as &$filter) {
                usort($filter['values'], function($a, $b) {
                    if ($a['active'] !== $b['active']) {
                        return $b['active'] <=> $a['active'];
                    }
                    return $b['count'] <=> $a['count'];
                });
            }

            return array_values($filters);

        } catch (\Exception $e) {
            error_log("Error in ParameterController::getFilters: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Validate database data integrity
     */
    private function validateData(): void
    {
        $queries = [
            "SELECT COUNT(*) FROM parameters" => "Parameters",
            "SELECT COUNT(*) FROM parameter_values" => "Parameter values",
            "SELECT COUNT(*) FROM product_parameters" => "Product parameters",
            "SELECT COUNT(*) FROM products" => "Products"
        ];

        foreach ($queries as $query => $name) {
            $count = $this->db->query($query)->fetchColumn();
            error_log("$name count: $count");
        }

        $query = "
            SELECT 
                p.name, 
                p.slug,
                COUNT(DISTINCT pv.id) as values_count,
                COUNT(DISTINCT pp.product_id) as products_count
            FROM parameters p
            LEFT JOIN parameter_values pv ON pv.parameter_id = p.id
            LEFT JOIN product_parameters pp ON pp.parameter_value_id = pv.id
            GROUP BY p.id, p.name, p.slug
        ";

        $stmt = $this->db->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Parameter {$row['name']} ({$row['slug']}): {$row['values_count']} values, {$row['products_count']} products");
        }
    }
}