<?php

namespace App\Domain\Dashboard\Action;

use App\Domain\Dashboard\Service\ServerHealthService;
use App\Middleware\JwtMiddleware;
use DI\Container;

class DashboardStreamAction
{
    public function __invoke(array $vars, Container $container): string
    {
        try {
            // Verify JWT token
            $user = JwtMiddleware::authenticate($container);

            // Set SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: ' . $_ENV['CORS_ORIGIN']);

            $serverHealthService = $container->get(ServerHealthService::class);

            // Send initial data
            $health = $serverHealthService->getServerHealth();
            echo "data: " . json_encode($health) . "\n\n";
            flush();

            // Stream data every 5 seconds for 1 minute
            $startTime = time();
            $duration = 60; // 1 minute
            $interval = 5; // 5 seconds

            while ((time() - $startTime) < $duration) {
                sleep($interval);

                if (connection_aborted()) {
                    break;
                }

                $health = $serverHealthService->getServerHealth();
                echo "data: " . json_encode($health) . "\n\n";
                flush();
            }

            return '';
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
