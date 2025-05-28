<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Migration runner class
 *
 * Handles execution and rollback of database migrations
 * in the correct order
 */
class MigrationRunner
{
    /**
     * @var array List of migration classes in order of execution
     */
    private array $migrations = [
        Migrations\CreateProductsTable::class,
        Migrations\CreateParametersTable::class,
        Migrations\CreateParameterValuesTable::class,
        Migrations\CreateProductParametersTable::class,
    ];

    /**
     * Run all migrations in order
     *
     * Executes up() method for each migration class
     * and outputs completion status
     */
    public function migrate(): void
    {
        foreach ($this->migrations as $migrationClass) {
            $migration = new $migrationClass();
            $migration->up();
            echo "Migration completed: " . $migrationClass . PHP_EOL;
        }
    }

    /**
     * Rollback all migrations in reverse order
     *
     * Executes down() method for each migration class
     * and outputs completion status
     */
    public function rollback(): void
    {
        foreach (array_reverse($this->migrations) as $migrationClass) {
            $migration = new $migrationClass();
            $migration->down();
            echo "Rollback completed: " . $migrationClass . PHP_EOL;
        }
    }
}