<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Database connection singleton class
 *
 * Provides a single point of access to the database connection
 * using PDO with MySQL configuration from environment variables
 */
class Database
{
    /**
     * @var PDO|null Singleton instance of PDO connection
     */
    private static ?PDO $instance = null;

    /**
     * Get database connection instance
     *
     * Creates new PDO connection if it doesn't exist, or returns existing one
     * Uses environment variables for connection parameters
     * Sets up PDO to:
     * - Throw exceptions on errors
     * - Return associative arrays by default
     * - Disable prepared statement emulation
     *
     * @return PDO Database connection instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                $_ENV['DB_USER'],
                $_ENV['DB_PASS'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$instance;
    }
}