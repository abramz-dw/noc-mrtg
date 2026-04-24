<?php

namespace App\Domain\Dashboard\Action;

use DI\Container;

class HealthAction
{
    public function __invoke(array $vars, Container $container): string
    {
        http_response_code(200);
        return json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ]);
    }
}
