<?php

/**
 * Application entry point
 *
 * Handles routing for both API endpoints and static files
 * Includes CORS support and error handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\ProductController;
use App\Controller\ParameterController;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse request URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($queryString ?? '', $queryParams);

try {
    // API endpoints
    if (strpos($path, '/api/') === 0) {
        header('Content-Type: application/json');

        switch ($path) {
            case '/api/catalog/products':
                $controller = new ProductController();

                $page = (int)($queryParams['page'] ?? 1);
                $limit = (int)($queryParams['limit'] ?? 10);
                $sortBy = $queryParams['sort_by'] ?? null;

                $filters = [];
                if (isset($queryParams['filter']) && is_array($queryParams['filter'])) {
                    foreach ($queryParams['filter'] as $paramSlug => $value) {
                        if (is_array($value)) {
                            $filters[$paramSlug] = array_map('strval', $value);
                        } else {
                            $filters[$paramSlug] = [$value];
                        }
                    }
                }

                error_log("Products request: " . json_encode([
                        'page' => $page,
                        'limit' => $limit,
                        'sortBy' => $sortBy,
                        'filters' => $filters
                    ]));

                echo json_encode($controller->list($page, $limit, $sortBy, $filters));
                break;

            case '/api/catalog/filters':
                $controller = new ParameterController();

                $activeFilters = [];
                if (isset($queryParams['filter']) && is_array($queryParams['filter'])) {
                    foreach ($queryParams['filter'] as $paramSlug => $value) {
                        if (is_array($value)) {
                            $activeFilters[$paramSlug] = array_map('strval', $value);
                        } else {
                            $activeFilters[$paramSlug] = [$value];
                        }
                    }
                }

                error_log("Processing filters request with active filters: " . json_encode($activeFilters));
                header('Content-Type: application/json');
                echo json_encode($controller->getFilters($activeFilters));
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found']);
                break;
        }
    }
    // Static file handling
    else {
        switch ($path) {
            case '/':
            case '/index.html':
                include __DIR__ . '/index.html';
                break;

            default:
                $filePath = __DIR__ . $path;

                // Validate file path
                $realPath = realpath($filePath);
                $publicDir = realpath(__DIR__);

                if ($realPath === false || strpos($realPath, $publicDir) !== 0) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    break;
                }

                if (file_exists($filePath) && is_file($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $mimeTypes = [
                        'html' => 'text/html',
                        'css' => 'text/css',
                        'js' => 'application/javascript',
                        'json' => 'application/json',
                        'png' => 'image/png',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        'woff' => 'font/woff',
                        'woff2' => 'font/woff2',
                        'ttf' => 'font/ttf',
                        'eot' => 'application/vnd.ms-fontobject',
                        'otf' => 'font/otf'
                    ];

                    if (isset($mimeTypes[$ext])) {
                        header('Content-Type: ' . $mimeTypes[$ext]);
                    }

                    $etag = md5_file($filePath);
                    header('ETag: "' . $etag . '"');
                    header('Cache-Control: public, max-age=86400');

                    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == '"' . $etag . '"') {
                        http_response_code(304);
                        exit;
                    }

                    readfile($filePath);
                } else {
                    include __DIR__ . '/index.html';
                }
                break;
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);

    if (strpos($path, '/api/') === 0) {
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage()
        ]);
    } else {
        include __DIR__ . '/500.html';
    }
}