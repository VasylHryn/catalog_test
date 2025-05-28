<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Migration;

/**
 * Migration for creating the parameters table
 *
 * Creates a table for storing product parameters/attributes with:
 * - Auto-incrementing ID
 * - Parameter name and slug
 * - Filterability flag
 * - Sort order
 * - Timestamps
 */
class CreateParametersTable extends Migration
{
    /**
     * Create parameters table
     *
     * Creates table with following structure:
     * - id: Primary key, unsigned auto-increment integer
     * - name: Parameter name (VARCHAR 255)
     * - slug: Unique parameter identifier (VARCHAR 100)
     * - is_filterable: Boolean flag for filter availability
     * - sort_order: Unsigned integer for custom sorting
     * - created_at: Creation timestamp
     * - updated_at: Last update timestamp
     *
     * Includes indexes:
     * - Primary key on id
     * - Unique key on slug
     * - Compound index on is_filterable and sort_order
     */
    public function up(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS parameters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,             
            is_filterable BOOLEAN DEFAULT TRUE,      
            sort_order INT UNSIGNED DEFAULT 0,    
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unq_slug (slug),
            KEY idx_filterable_sort (is_filterable, sort_order) 
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->exec($sql);
    }

    /**
     * Drop parameters table
     *
     * Removes the parameters table if it exists
     */
    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS parameters");
    }
}