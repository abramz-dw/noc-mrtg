<?php
/**
 * NOC Web Management System
 * Entry Point - public/index.php
 * 
 * FastRoute + PHP-DI dispatcher
 * Router untuk semua request API
 */

declare(strict_types=1);

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Constants
define('BASE_PATH', dirname(__DIR__));
define('BOOTSTRAP_TIME', microtime(true));

// Autoloader
require_once BASE_PATH . '/vendor/autoload.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Dotenv\Dotenv;
use App\Infrastructure\Logger\AppLogger;
use App\Infrastructure\Database\Connection;

// Load environment
$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Logger
$logger = new AppLogger();

try {
    // CORS Middleware
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: ' . $_ENV['CORS_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        http_response_code(200);
        exit;
    }

    // Set default headers
    header('Access-Control-Allow-Origin: ' . $_ENV['CORS_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');

    // Database connection
    $db = new Connection(
        host: $_ENV['DB_HOST'],
        port: (int)$_ENV['DB_PORT'],
        name: $_ENV['DB_NAME'],
        user: $_ENV['DB_USER'],
        pass: $_ENV['DB_PASS'],
        charset: $_ENV['DB_CHARSET'],
        timezone: $_ENV['DB_TIMEZONE'],
        logger: $logger
    );

    // Dependency Injection Container
    $container = new DI\Container();

    // Register services
    $container->set('db', $db);
    $container->set('logger', $logger);

    // Services
    $container->set(App\Domain\Authentication\Service\LoginService::class, function (DI\Container $c) {
        return new App\Domain\Authentication\Service\LoginService(
            db: $c->get('db'),
            logger: $c->get('logger'),
            jwtSecret: $_ENV['JWT_SECRET'],
            jwtAlgorithm: $_ENV['JWT_ALGORITHM'],
            jwtExpiration: (int)$_ENV['JWT_EXPIRATION']
        );
    });

    $container->set(App\Domain\Dashboard\Service\ServerHealthService::class, function (DI\Container $c) {
        return new App\Domain\Dashboard\Service\ServerHealthService(
            db: $c->get('db'),
            logger: $c->get('logger')
        );
    });

    // Routes
    $dispatcher = simpleDispatcher(function (RouteCollector $r) {
        // Public endpoints
        $r->get('/api/health', 'App\Domain\Dashboard\Action\HealthAction');
        $r->post('/api/login', 'App\Domain\Authentication\Action\LoginAction');

        // Protected endpoints
        $r->get('/api/dashboard/summary', 'App\Domain\Dashboard\Action\DashboardSummaryAction');
        $r->get('/api/dashboard/server-health', 'App\Domain\Dashboard\Action\ServerHealthAction');
        $r->get('/api/dashboard/stream', 'App\Domain\Dashboard\Action\DashboardStreamAction');
    });

    // Dispatch request
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            echo json_encode(['error' => 'Route not found']);
            break;

        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;

        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            // Resolve handler from container
            if (is_string($handler)) {
                $handler = $container->get($handler);
            }

            // Execute handler
            $response = $handler($vars, $container);
            echo $response;
            break;
    }

} catch (\Throwable $e) {
    $logger->error('Fatal error', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'An error occurred'
    ]);
}
