<?php
declare(strict_types=1);

namespace Wiki\Database;

use PDO;
use PDOException;
use RuntimeException;

class PdoDatabase implements DatabaseInterface
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['driver'] ?? 'sqlite';
        try {
            if ($this->driver === 'sqlite') {
                $path = $cfg['path'] ?? WIKI_ROOT . '/data/wiki.sqlite';
                $dir = dirname($path);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $this->pdo = new PDO('sqlite:' . $path);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $cfg['host'] ?? '127.0.0.1',
                    (int)($cfg['port'] ?? 3306),
                    $cfg['database'] ?? ''
                );
                $this->pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '');
                $this->pdo->exec('SET NAMES utf8mb4');
            }
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            throw new RuntimeException('数据库连接失败：' . $e->getMessage());
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue(string $sql, array $params = [], $default = null)
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? $default : $v;
    }

    public function execute(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool           { return $this->pdo->commit(); }
    public function rollBack(): bool         { return $this->pdo->rollBack(); }
    public function driver(): string         { return $this->driver; }
    public function pdo(): PDO               { return $this->pdo; }

    public function tableExists(string $table): bool
    {
        if ($this->driver === 'sqlite') {
            $r = $this->fetchOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            );
            return $r !== null;
        }
        $r = $this->fetchOne("SHOW TABLES LIKE ?", [$table]);
        return $r !== null;
    }
}