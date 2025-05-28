<?php

declare(strict_types=1);

namespace App\Command;

use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for checking imported data statistics
 *
 * Shows total counts of products and parameters,
 * top 10 most used parameters and random 5 products with their details
 */
class CheckDataCommand extends Command
{
    protected static $defaultName = 'app:check-data';
    private PDO $db;

    /**
     * Initialize command with database connection
     */
    public function __construct()
    {
        parent::__construct();

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
     * Configure command description
     */
    protected function configure(): void
    {
        $this->setDescription('Check imported data statistics');
    }

    /**
     * Execute statistics gathering and display
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command status code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM products");
        $productsCount = $stmt->fetch()['count'];
        $output->writeln("Total products: {$productsCount}");

        $stmt = $this->db->query("SELECT COUNT(*) as count FROM parameters");
        $parametersCount = $stmt->fetch()['count'];
        $output->writeln("Total parameters: {$parametersCount}");

        $stmt = $this->db->query("
            SELECT 
                p.name as parameter_name, 
                COUNT(DISTINCT pp.product_id) as usage_count
            FROM parameters p
            JOIN parameter_values pv ON pv.parameter_id = p.id
            JOIN product_parameters pp ON pp.parameter_value_id = pv.id
            GROUP BY p.id
            ORDER BY usage_count DESC
            LIMIT 10
        ");

        $output->writeln("\nTop 10 most used parameters:");
        while ($row = $stmt->fetch()) {
            $output->writeln(sprintf(
                "%s: %d products",
                $row['parameter_name'],
                $row['usage_count']
            ));
        }

        $stmt = $this->db->query("
            SELECT p.*, GROUP_CONCAT(CONCAT(param.name, ': ', pv.value) SEPARATOR '\n') as parameters
            FROM products p
            LEFT JOIN product_parameters pp ON pp.product_id = p.id
            LEFT JOIN parameter_values pv ON pv.id = pp.parameter_value_id
            LEFT JOIN parameters param ON param.id = pv.parameter_id
            GROUP BY p.id
            ORDER BY RAND()
            LIMIT 5
        ");

        $output->writeln("\nRandom 5 products with parameters:");
        while ($row = $stmt->fetch()) {
            $output->writeln("\n" . str_repeat('-', 50));
            $output->writeln("ID: {$row['id']}");
            $output->writeln("Name: {$row['name']}");
            $output->writeln("Price: {$row['price']}");
            $output->writeln("Parameters:\n{$row['parameters']}");
        }

        return Command::SUCCESS;
    }
}