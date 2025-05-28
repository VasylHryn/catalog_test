<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RedisFilterService;
use PDO;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * Tests for Redis filtering service
 *
 * @covers \App\Service\RedisFilterService
 */
class RedisFilterServiceTest extends TestCase
{
    private PDO $db;
    private RedisFilterService $service;
    private Client $redis;

    /**
     * Set up test environment and sample data
     */
    protected function setUp(): void
    {
        $this->db = new PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec("TRUNCATE TABLE product_parameters");
        $this->db->exec("TRUNCATE TABLE parameter_values");
        $this->db->exec("TRUNCATE TABLE parameters");
        $this->db->exec("TRUNCATE TABLE products");
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->redis = new Client([
            'scheme' => $_ENV['REDIS_SCHEME'] ?? 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
        ]);

        $this->redis->flushdb();
        $this->service = new RedisFilterService($this->db);
        $this->setupTestData();
    }

    /**
     * Clean up test data
     */
    protected function tearDown(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec("TRUNCATE TABLE product_parameters");
        $this->db->exec("TRUNCATE TABLE parameter_values");
        $this->db->exec("TRUNCATE TABLE parameters");
        $this->db->exec("TRUNCATE TABLE products");
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->redis->flushdb();
    }

    /**
     * Initialize test dataset
     */
    private function setupTestData(): void
    {
        $this->db->exec("INSERT INTO products (id, name, price) VALUES 
            (1, 'Ноутбук 1', 25000),
            (2, 'Ноутбук 2', 30000),
            (3, 'Ноутбук 3', 35000)");

        $this->db->exec("INSERT INTO parameters (id, name, slug) VALUES 
            (1, 'Бренд', 'brand'),
            (2, 'Колір', 'color')");

        $this->db->exec("INSERT INTO parameter_values (id, parameter_id, value) VALUES 
            (1, 1, 'Lenovo'),
            (2, 1, 'HP'),
            (3, 2, 'червоний'),
            (4, 2, 'чорний')");

        $this->db->exec("INSERT INTO product_parameters (product_id, parameter_value_id) VALUES 
            (1, 1), (1, 3),  /* Lenovo, червоний */
            (2, 1), (2, 4),  /* Lenovo, чорний */
            (3, 2), (3, 3)   /* HP, червоний */");

        $this->service->updateAfterImport();
    }

    /**
     * Test Redis keys creation after import
     */
    public function testRedisKeysAfterImport(): void
    {
        $productKeys = $this->redis->keys('products:param:*');
        $this->assertNotEmpty($productKeys, 'Should have product parameter keys');

        $statsKeys = $this->redis->keys('param:stats:*');
        $this->assertNotEmpty($statsKeys, 'Should have parameter stats keys');

        $valuesKeys = $this->redis->keys('param:values:*');
        $this->assertNotEmpty($valuesKeys, 'Should have parameter values keys');
    }

    /**
     * Test product filtering functionality
     */
    public function testGetFilteredProducts(): void
    {
        $result = $this->service->getFilteredProducts(['brand' => ['Lenovo']]);
        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['items']);

        $result = $this->service->getFilteredProducts([
            'brand' => ['Lenovo'],
            'color' => ['червоний']
        ]);
        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    /**
     * Test parameter values retrieval
     */
    public function testGetParameterValues(): void
    {
        $values = $this->service->getParameterValues('brand');
        $this->assertIsArray($values);
        $this->assertContains('Lenovo', $values);
        $this->assertContains('HP', $values);
    }

    /**
     * Test parameter statistics calculation
     */
    public function testGetParameterStats(): void
    {
        $stats = $this->service->getParameterStats('brand', 'Lenovo');
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['count']);
        $this->assertEquals(25000, $stats['min_price']);
        $this->assertEquals(30000, $stats['max_price']);
    }

    /**
     * Test catalog statistics retrieval
     */
    public function testGetCatalogStats(): void
    {
        $stats = $this->service->getCatalogStats();
        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_products']);
        $this->assertEquals(25000, $stats['min_price']);
        $this->assertEquals(35000, $stats['max_price']);
    }

    /**
     * Test pagination functionality
     */
    public function testPagination(): void
    {
        $result = $this->service->getFilteredProducts([], '', 1, 2);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(2, $result['items']);

        $result = $this->service->getFilteredProducts([], '', 2, 2);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(1, $result['items']);
    }

    /**
     * Test product sorting functionality
     */
    public function testSorting(): void
    {
        $result = $this->service->getFilteredProducts([], 'price_asc');
        $prices = array_column($result['items'], 'price');
        $this->assertEquals([25000, 30000, 35000], $prices);

        $result = $this->service->getFilteredProducts([], 'price_desc');
        $prices = array_column($result['items'], 'price');
        $this->assertEquals([35000, 30000, 25000], $prices);
    }
}