<?php

/**
 * API Entry Point
 *
 * Handles routing for the catalog API endpoints:
 * - /api/catalog/products: Product listing with filtering and pagination
 * - /api/catalog/filters: Available filters with statistics
 *
 * Provides JSON responses with debug information in development mode
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\ProductController;
use App\Controller\ParameterController;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set JSON response headers
header('Content-Type: application/json');

// Parse request URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');
$pathParts = explode('/', $path);

try {
    $debug = [
        'timestamp' => date('Y-m-d H:i:s'),
        'path' => $path,
        'pathParts' => $pathParts,
        'method' => $_SERVER['REQUEST_METHOD'],
        'query' => $_GET,
    ];

    // Route requests
    switch ($pathParts[0] ?? '') {
        case 'api':
            if (isset($pathParts[1]) && $pathParts[1] === 'catalog') {
                switch ($pathParts[2] ?? '') {
                    case 'products':
                        $controller = new ProductController();

                        $page = (int)($_GET['page'] ?? 1);
                        $limit = (int)($_GET['limit'] ?? 10);
                        $sortBy = $_GET['sort_by'] ?? 'id_asc';
                        $filters = $_GET['filter'] ?? [];

                        $result = $controller->list($page, $limit, $sortBy, $filters);
                        break;

                    case 'filters':
                        $controller = new ParameterController();
                        $activeFilters = $_GET['filter'] ?? [];
                        $result = $controller->getFilters($activeFilters);
                        break;

                    default:
                        http_response_code(404);
                        $result = [
                            'error' => 'Not Found',
                            'message' => 'Invalid endpoint',
                            'debug' => $debug
                        ];
                }
            } else {
                http_response_code(404);
                $result = [
                    'error' => 'Not Found',
                    'message' => 'Invalid catalog path',
                    'debug' => $debug
                ];
            }
            break;

        case '':
            $result = [
                'api' => 'Catalog API',
                'version' => '1.0',
                'endpoints' => [
                    '/api/catalog/products' => [
                        'description' => 'Get products list',
                        'parameters' => [
                            'page' => 'int, default: 1',
                            'limit' => 'int, default: 10',
                            'sort_by' => 'string (price_asc, price_desc, id_asc)',
                            'filter[parameter_slug]' => 'string|array'
                        ],
                        'example' => '/api/catalog/products?page=1&limit=10&sort_by=price_asc&filter[brend]=Nike'
                    ],
                    '/api/catalog/filters' => [
                        'description' => 'Get available filters',
                        'parameters' => [
                            'filter[parameter_slug]' => 'string|array (current active filters)'
                        ],
                        'example' => '/api/catalog/filters?filter[brend]=Nike'
                    ]
                ],
                'debug' => $debug
            ];
            break;

        default:
            http_response_code(404);
            $result = [
                'error' => 'Not Found',
                'message' => 'Invalid path',
                'debug' => $debug
            ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Error handling with debug information
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'debug' => $debug ?? null
    ]);
}