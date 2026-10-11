<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Redirect
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    /** Match a request path against the redirects table. */
    public static function findByPath(string $path): ?array
    {
        $path = '/' . ltrim($path, '/');
        return self::$db->fetchOne('SELECT * FROM redirects WHERE from_path = ?', [$path]);
    }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM redirects WHERE id = ?', [$id]);
    }

    public static function listAll(int $limit = 500, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT r.*, u.username AS creator
             FROM redirects r
             LEFT JOIN users u ON u.id = r.created_by
             ORDER BY r.hits DESC, r.id DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function create(string $from, string $to, int $status = 301, ?string $lang = null, ?int $userId = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $from = '/' . ltrim($from, '/');

        $existing = self::$db->fetchOne('SELECT id FROM redirects WHERE from_path = ?', [$from]);
        if ($existing) {
            self::$db->execute(
                'UPDATE redirects SET to_path = ?, status_code = ?, lang = ? WHERE id = ?',
                [$to, $status, $lang, $existing['id']]
            );
            return (int)$existing['id'];
        }

        self::$db->execute(
            'INSERT INTO redirects (from_path, to_path, status_code, lang, hits, created_by, created_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$from, $to, $status, $lang, $userId, $now]
        );
        return self::$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['from_path', 'to_path', 'status_code', 'lang'] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (!$fields) return;
        $params[] = $id;
        self::$db->execute('UPDATE redirects SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM redirects WHERE id = ?', [$id]);
    }

    public static function hit(int $id): void
    {
        self::$db->execute('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$id]);
    }

    /** Auto-create a redirect when a page slug changes. */
    public static function autoFromSlugChange(string $oldSlug, string $newSlug, ?int $userId = null): void
    {
        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) return;
        self::create('/page/' . $oldSlug, '/page/' . $newSlug, 301, null, $userId);
    }
}
