<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Migration;


/**
 * Migration for creating the parameter_values table
 *
 * Creates a table for storing parameter values with:
 * - Auto-incrementing ID
 * - Foreign key to parameters
 * - Text and numeric value storage
 * - Timestamps
 */
class CreateParameterValuesTable extends Migration
{
    /**
     * Create parameter_values table
     *
     * Creates table with following structure:
     * - id: Primary key, unsigned auto-increment integer
     * - parameter_id: Foreign key to parameters table
     * - value: Parameter value text (VARCHAR 255)
     * - numeric_value: Optional decimal value for numeric parameters
     * - created_at: Creation timestamp
     * - updated_at: Last update timestamp
     *
     * Includes indexes:
     * - Primary key on id
     * - Foreign key on parameter_id
     * - Unique compound key on parameter_id and value
     * - Index for numeric range filtering
     */
    public function up(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS parameter_values (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parameter_id INT UNSIGNED NOT NULL,
            value VARCHAR(255) NOT NULL,
            numeric_value DECIMAL(10,2) DEFAULT NULL,  -- Для числовых значений и сортировки
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parameter_id) REFERENCES parameters(id) ON DELETE CASCADE,
            UNIQUE KEY unq_parameter_value (parameter_id, value),
            KEY idx_numeric (parameter_id, numeric_value)  -- Для диапазонных фильтров
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->exec($sql);
    }

    /**
     * Drop parameter_values table
     *
     * Removes the parameter_values table if it exists
     */
    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS parameter_values");
    }
}