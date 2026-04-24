<?php

namespace App\Domain\Authentication\Action;

use App\Domain\Authentication\Service\LoginService;
use DI\Container;

class LoginAction
{
    public function __invoke(array $vars, Container $container): string
    {
        try {
            // Get POST data
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['username'], $input['password'])) {
                http_response_code(400);
                return json_encode(['error' => 'Missing username or password']);
            }

            $username = trim($input['username']);
            $password = $input['password'];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // Get LoginService from container
            $loginService = $container->get(LoginService::class);

            // Perform login
            $result = $loginService->login($username, $password, $ipAddress);

            http_response_code(200);
            return json_encode([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            http_response_code($statusCode);
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
