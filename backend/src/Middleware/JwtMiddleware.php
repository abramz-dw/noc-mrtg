<?php

namespace App\Middleware;

use App\Domain\Authentication\Service\LoginService;
use DI\Container;

class JwtMiddleware
{
    // Routes yang tidak memerlukan JWT
    private const PUBLIC_ROUTES = [
        'GET /api/health',
        'POST /api/login'
    ];

    public static function isPublicRoute(string $method, string $path): bool
    {
        $routeKey = "{$method} {$path}";
        return in_array($routeKey, self::PUBLIC_ROUTES, true);
    }

    public static function authenticate(Container $container): array
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Check if route is public
        if (self::isPublicRoute($method, $path)) {
            return [];
        }

        // Get Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing authorization token']);
            exit;
        }

        $token = substr($authHeader, 7);

        try {
            $loginService = $container->get(LoginService::class);
            $decoded = $loginService->verifyToken($token);
            return $decoded;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
