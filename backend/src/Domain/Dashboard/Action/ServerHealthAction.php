<?php

namespace App\Domain\Dashboard\Action;

use App\Domain\Dashboard\Service\ServerHealthService;
use App\Middleware\JwtMiddleware;
use DI\Container;

class ServerHealthAction
{
    public function __invoke(array $vars, Container $container): string
    {
        try {
            // Verify JWT token
            $user = JwtMiddleware::authenticate($container);

            $serverHealthService = $container->get(ServerHealthService::class);
            $health = $serverHealthService->getServerHealth();

            http_response_code(200);
            return json_encode([
                'success' => true,
                'data' => $health
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
