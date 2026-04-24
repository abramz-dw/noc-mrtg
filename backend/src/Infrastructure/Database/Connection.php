<?php

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

class Connection
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $name,
        private readonly string $user,
        private readonly string $pass,
        private readonly string $charset,
        private readonly string $timezone,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->name,
                $this->charset
            );

            $this->pdo = new PDO(
                $dsn,
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );

            // Set timezone
            $this->pdo->exec(sprintf('SET SESSION time_zone = \'%s\'', $this->timezone));

            $this->logger->info('Database connected', [
                'host' => $this->host,
                'database' => $this->name
            ]);
        } catch (PDOException $e) {
            $this->logger->critical('Database connection failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function execute(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function query(string $sql, array $params = [])
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = [])
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }

    public function insert(string $table, array $data): string|int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $conditions): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $where = implode(' AND ', array_map(fn($col) => "{$col} = ?", array_keys($conditions)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $params = array_merge(array_values($data), array_values($conditions));
        $stmt = $this->execute($sql, $params);

        return $stmt->rowCount();
    }

    public function delete(string $table, array $conditions): int
    {
        $where = implode(' AND ', array_map(fn($col) => "{$col} = ?", array_keys($conditions)));
        $sql = "DELETE FROM {$table} WHERE {$where}";

        $stmt = $this->execute($sql, array_values($conditions));
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
