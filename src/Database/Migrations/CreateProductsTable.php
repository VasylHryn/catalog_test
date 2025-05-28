<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Migration;

/**
 * Migration for creating the products table
 *
 * Creates a table for storing product information with:
 * - External ID as primary key
 * - Basic product details (name, price, status)
 * - Extended description
 * - Timestamps
 */
class CreateProductsTable extends Migration
{
    /**
     * Create products table
     *
     * Creates table with following structure:
     * - id: Primary key, unsigned bigint
     * - name: Product name (VARCHAR 255)
     * - price: Product price (DECIMAL 10,2)
     * - status: Product status (TINYINT)
     * - description: Optional product description (TEXT)
     * - created_at: Creation timestamp
     * - updated_at: Last update timestamp
     *
     * Includes indexes:
     * - Primary key on id
     * - Compound index on status and price
     * - Index on name for searches
     * - Index on updated_at for sorting
     */
    public function up(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id BIGINT UNSIGNED PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) UNSIGNED NOT NULL, 
            status TINYINT UNSIGNED DEFAULT 1,      
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_status_price (status, price),  
            KEY idx_name (name),
            KEY idx_updated (updated_at)           
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->exec($sql);
    }

    /**
     * Drop products table
     *
     * Removes the products table if it exists
     */
    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS products");
    }
}