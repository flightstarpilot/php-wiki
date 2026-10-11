<?php
declare(strict_types=1);

namespace Wiki\Database;

interface DatabaseInterface
{
    public function query(string $sql, array $params = []): array;
    public function fetchOne(string $sql, array $params = []): ?array;
    public function fetchValue(string $sql, array $params = [], $default = null);
    public function execute(string $sql, array $params = []): int;
    public function lastInsertId(): int;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;
    public function driver(): string;
    public function tableExists(string $table): bool;
    public function pdo(): \PDO;
}