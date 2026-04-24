<?php

namespace App\Domain\Dashboard\Action;

use App\Domain\Dashboard\Service\ServerHealthService;
use App\Middleware\JwtMiddleware;
use DI\Container;

class DashboardSummaryAction
{
    public function __invoke(array $vars, Container $container): string
    {
        try {
            // Verify JWT token
            $user = JwtMiddleware::authenticate($container);

            $serverHealthService = $container->get(ServerHealthService::class);
            $summary = $serverHealthService->getDashboardSummary();

            http_response_code(200);
            return json_encode([
                'success' => true,
                'data' => $summary
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
