<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Base migration class
 *
 * Provides database connection and abstract methods for migrations
 */
abstract class Migration
{
    /**
     * @var PDO Database connection instance
     */
    protected PDO $db;

    /**
     * Initialize migration with database connection
     */
    public function __construct()
    {
        $this->db = new PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }

    /**
     * Apply migration
     */
    abstract public function up(): void;

    /**
     * Revert migration
     */
    abstract public function down(): void;
}