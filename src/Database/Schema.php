<?php
declare(strict_types=1);

namespace Wiki\Database;

/**
 * Applies the reference SQL schema from /database into the target database.
 *
 * The schema files are the single source of truth; the installer simply
 * streams them through PDO. Statements are split on ";" at end of line.
 */
class Schema
{
    public static function install(DatabaseInterface $db): void
    {
        $driver = $db->driver();
        $file = WIKI_ROOT . '/database/' . ($driver === 'sqlite' ? 'sqlite.sql' : 'mysql.sql');

        if (!is_file($file)) {
            throw new \RuntimeException('Schema file not found: ' . $file);
        }

        $sql = (string)file_get_contents($file);
        $sql = self::stripComments($sql);

        $pdo = $db->pdo();
        foreach (self::splitStatements($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;

            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                // Ignore "already exists" errors so the installer is idempotent
                if (!self::isAlreadyExists($e)) throw $e;
            }
        }
    }

    private static function stripComments(string $sql): string
    {
        // Remove /* ... */ block comments
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
        // Remove -- line comments
        $sql = preg_replace('#^\s*--.*$#m', '', $sql) ?? $sql;
        return $sql;
    }

    /**
     * Naive statement splitter: splits on ";" followed by end-of-line.
     * Safe here because the schema has no string literals containing ";".
     */
    private static function splitStatements(string $sql): array
    {
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_filter(array_map('trim', $parts), fn($s) => $s !== '');
    }

    private static function isAlreadyExists(\PDOException $e): bool
    {
        $code = $e->getCode();
        $msg  = $e->getMessage();

        // MySQL: 42S01 = table exists, 42S21 = column exists,
        //        42000 = generic syntax / duplicate key
        if (in_array($code, ['42S01', '42S21'], true)) return true;

        foreach ([
            'already exists',
            'Duplicate key name',
            'Duplicate entry',
            'Duplicate column name',
        ] as $needle) {
            if (stripos($msg, $needle) !== false) return true;
        }

        return false;
    }
}