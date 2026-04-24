#!/usr/bin/env php
<?php
/**
 * NOC Web Management System
 * Poller Daemon - bin/poller-daemon.php
 * 
 * Supervisor-managed daemon untuk polling perangkat
 * Fase 1: Dummy ICMP ping ke IP dummy
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DAEMON_START_TIME', time());

require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Infrastructure\Logger\AppLogger;
use App\Infrastructure\Database\Connection;

// Load environment
$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Logger
$logger = new AppLogger();

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

$logger->info('Poller daemon started', ['pid' => getmypid()]);

// Dummy device IPs untuk testing
$dummyDevices = [
    ['name' => 'Router-JKT', 'ip' => '192.168.1.1'],
    ['name' => 'Switch-BDG', 'ip' => '192.168.2.1'],
    ['name' => 'Firewall-SBY', 'ip' => '192.168.3.1'],
];

// Polling loop
$pollInterval = 60; // 60 seconds
while (true) {
    try {
        $pollTime = time();
        $logger->info('Polling cycle started', ['timestamp' => date('Y-m-d H:i:s')]);

        // Dummy ICMP ping untuk setiap device
        foreach ($dummyDevices as $device) {
            $status = $this->pingDevice($device['ip']);
            $logger->info('Device ping result', [
                'device' => $device['name'],
                'ip' => $device['ip'],
                'status' => $status ? 'UP' : 'DOWN'
            ]);
        }

        $logger->info('Polling cycle completed');

        // Sleep sampai polling interval berikutnya
        $elapsed = time() - $pollTime;
        $sleepTime = max(1, $pollInterval - $elapsed);
        sleep($sleepTime);

    } catch (\Exception $e) {
        $logger->error('Polling error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sleep(5); // Wait sebelum retry
    }
}

/**
 * Dummy ICMP ping
 */
function pingDevice(string $ip): bool
{
    // Simulasi random status untuk demo
    return rand(0, 100) > 10; // 90% online
}
