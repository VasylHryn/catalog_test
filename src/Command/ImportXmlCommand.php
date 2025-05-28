<?php

declare(strict_types=1);

namespace App\Command;

use PDO;
use App\Service\RedisFilterService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for importing products from XML file
 *
 * This command reads product data from an XML file and imports it into the database.
 * It also updates Redis cache for fast filtering and statistics calculation.
 *
 */
class ImportXmlCommand extends Command
{
    protected static $defaultName = 'app:import-xml';
    private PDO $db;
    private RedisFilterService $redisFilter;

    /**
     * Initialize command and database connections
     *
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

        $this->redisFilter = new RedisFilterService($this->db);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import products from XML file')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to XML file', $_ENV['XML_FILE_PATH']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $xmlPath = $input->getOption('file');

        $output->writeln("Checking file: " . realpath($xmlPath));
        if (!file_exists($xmlPath)) {
            $output->writeln("<error>XML file not found: {$xmlPath}</error>");
            return Command::FAILURE;
        }

        $fileSize = filesize($xmlPath);
        $output->writeln("File size: " . ($fileSize ? number_format($fileSize / 1024 / 1024, 2) . " MB" : "0 bytes"));

        $output->writeln("Starting import from: {$xmlPath}");

        $reader = new \XMLReader();
        if (!$reader->open($xmlPath)) {
            $output->writeln("<error>Failed to open XML file</error>");
            return Command::FAILURE;
        }

        $this->db->beginTransaction();

        try {
            $batch = [];
            $batchSize = 1000;
            $processedCount = 0;

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'offers') {
                    break;
                }
            }

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'offer') {
                    $offer = $this->parseOffer($reader);

                    if ($this->validateOffer($offer)) {
                        $batch[] = $offer;

                        if (count($batch) >= $batchSize) {
                            $this->processBatch($batch, $output);
                            $processedCount += count($batch);
                            $output->writeln("Processed {$processedCount} offers");
                            $batch = [];
                        }
                    }
                }
            }

            if (!empty($batch)) {
                $this->processBatch($batch, $output);
                $processedCount += count($batch);
                $output->writeln("Processed {$processedCount} offers");
            }

            $this->db->commit();
            $reader->close();

            $output->writeln("Updating Redis cache...");
            $this->redisFilter->updateAfterImport();
            $output->writeln("Redis cache updated successfully!");

            $output->writeln("<info>Import completed successfully! Total items: {$processedCount}</info>");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->db->rollBack();
            $reader->close();
            $output->writeln("<error>Import failed: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }

    /**
     * Parse single offer from XML reader
     *
     * @param XMLReader $reader
     * @return array Parsed offer data
     * @throws RuntimeException
     */
    private function parseOffer(\XMLReader $reader): array
    {
        $node = $reader->expand();

        if (!$node) {
            throw new \RuntimeException("Failed to parse XML node");
        }

        $dom = new \DOMDocument();
        $node = $dom->importNode($node, true);

        $offer = [
            'id' => $node->getAttribute('id'),
            'name' => $this->getNodeValue($node, 'name'),
            'price' => $this->getNodeValue($node, 'price'),
            'description' => $this->getNodeValue($node, 'description'),
            'parameters' => []
        ];

        $params = $node->getElementsByTagName('param');
        foreach ($params as $param) {
            $name = $param->getAttribute('name');
            $value = $param->nodeValue;

            if ($name && $value) {
                $offer['parameters'][] = [
                    'name' => $name,
                    'value' => $value
                ];
            }
        }

        return $offer;
    }

    /**
     * Validate offer data
     *
     * @param array $offer
     * @return bool
     */
    private function validateOffer(array $offer): bool
    {
        return !empty($offer['id'])
            && !empty($offer['name'])
            && is_numeric($offer['price'])
            && $offer['price'] > 0;
    }

    /**
     * Get XML node value by tag name
     *
     * @param \DOMNode $node
     * @param string $tagName
     * @return string
     */
    private function getNodeValue(\DOMNode $node, string $tagName): string
    {
        $nodes = $node->getElementsByTagName($tagName);
        return $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) : '';
    }

    /**
     * Process batch of offers
     *
     * @param array $batch
     * @param OutputInterface $output
     */
    private function processBatch(array $batch, OutputInterface $output): void
    {
        foreach ($batch as $item) {
            try {
                $this->saveProduct($item);

                foreach ($item['parameters'] as $param) {
                    $parameterId = $this->saveParameter($param['name']);
                    $valueId = $this->saveParameterValue($parameterId, $param['value']);
                    $this->saveProductParameter($item['id'], $valueId);
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Error processing item {$item['id']}: " . $e->getMessage() . "</error>");
            }
        }
    }

    /**
     * Save product to database
     *
     * @param array $item
     */
    private function saveProduct(array $item): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (id, name, price, description)
            VALUES (:id, :name, :price, :description)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                price = VALUES(price),
                description = VALUES(description)
        ");

        $stmt->execute([
            'id' => $this->parseId($item['id']),
            'name' => $item['name'],
            'price' => $item['price'],
            'description' => $item['description'] ?? null
        ]);
    }

    /**
     * Save parameter and return its ID
     *
     * @param string $name
     * @return int
     */
    private function saveParameter(string $name): int
    {
        $slug = $this->createSlug($name);

        $stmt = $this->db->prepare("
            INSERT INTO parameters (name, slug)
            VALUES (:name, :slug)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");

        $stmt->execute([
            'name' => $name,
            'slug' => $slug
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Save parameter value and return its ID
     *
     * @param int $parameterId
     * @param string $value
     * @return int
     */
    private function saveParameterValue(int $parameterId, string $value): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO parameter_values (parameter_id, value)
            VALUES (:parameter_id, :value)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");

        $stmt->execute([
            'parameter_id' => $parameterId,
            'value' => $value
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Save product parameter relation
     *
     * @param string|int $productId
     * @param int $valueId
     */
    private function saveProductParameter(string|int $productId, int $valueId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO product_parameters (product_id, parameter_value_id)
            VALUES (:product_id, :parameter_value_id)
        ");

        $stmt->execute([
            'product_id' => $this->parseId($productId),
            'parameter_value_id' => $valueId
        ]);
    }

    /**
     * Parse and normalize ID
     *
     * @param string|int $id
     * @return int
     */
    private function parseId(string|int $id): int
    {
        if (is_string($id)) {
            $id = ltrim($id, '0');
            if ($id === '') {
                $id = '0';
            }
        }
        return (int)$id;
    }

    /**
     * Create slug from name
     *
     * @param string $name
     * @return string
     */
    private function createSlug(string $name): string
    {
        $slug = mb_strtolower($name, 'UTF-8');

        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];

        $slug = strtr($slug, $transliteration);

        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}