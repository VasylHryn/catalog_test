<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Migration;


/**
 * Migration for creating the product_parameters table
 *
 * Creates a junction table for storing relationships between:
 * - Products
 * - Parameter values
 * With composite primary key and bidirectional foreign keys
 */
class CreateProductParametersTable extends Migration
{
    /**
     * Create product_parameters table
     *
     * Creates table with following structure:
     * - product_id: Foreign key to products table
     * - parameter_value_id: Foreign key to parameter_values table
     *
     * Includes indexes:
     * - Composite primary key (product_id, parameter_value_id)
     * - Foreign key constraints with cascade deletion
     * - Reverse lookup index on parameter_value_id
     */
    public function up(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS product_parameters (
            product_id BIGINT UNSIGNED NOT NULL,
            parameter_value_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (product_id, parameter_value_id),
            FOREIGN KEY (product_id) 
                REFERENCES products(id) 
                ON DELETE CASCADE,
            FOREIGN KEY (parameter_value_id) 
                REFERENCES parameter_values(id) 
                ON DELETE CASCADE,
            KEY idx_value_product (parameter_value_id, product_id)  -- Для обратного поиска
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->exec($sql);
    }

    /**
     * Drop product_parameters table
     *
     * Removes the product_parameters table if it exists
     */
    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS product_parameters");
    }
}