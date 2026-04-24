<?php

namespace App\Infrastructure\Logger;

use Monolog\Logger;
use Monolog\Handlers\StreamHandler;
use Monolog\Handlers\RotatingFileHandler;
use Monolog\Processors\IntrospectionProcessor;
use Monolog\Processors\WebProcessor;
use Psr\Log\LoggerInterface;

class AppLogger implements LoggerInterface
{
    private Logger $logger;

    public function __construct()
    {
        $logDir = $_ENV['LOG_DIR'] ?? '/var/log/apps';
        $logLevel = $_ENV['LOG_LEVEL'] ?? 'info';

        // Create logs directory if not exists
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $this->logger = new Logger('app');

        // File handler (rotating)
        $fileHandler = new RotatingFileHandler(
            $logDir . '/app.log',
            10,
            Logger::toMonologLevel($logLevel)
        );
        $this->logger->pushHandler($fileHandler);

        // Error log file handler
        $errorHandler = new RotatingFileHandler(
            $logDir . '/error.log',
            10,
            Logger::ERROR
        );
        $this->logger->pushHandler($errorHandler);

        // Console handler (stderr)
        if ($_ENV['APP_ENV'] !== 'production') {
            $consoleHandler = new StreamHandler('php://stderr', Logger::DEBUG);
            $this->logger->pushHandler($consoleHandler);
        }

        // Processors
        $this->logger->pushProcessor(new IntrospectionProcessor());
        $this->logger->pushProcessor(new WebProcessor());
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
