<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class NotFoundLog
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function record(string $path): void
    {
        try {
            $now = gmdate('Y-m-d H:i:s');
            $referer = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
            $ua      = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

            $existing = self::$db->fetchOne('SELECT id FROM not_found_logs WHERE path = ?', [$path]);
            if ($existing) {
                self::$db->execute(
                    'UPDATE not_found_logs SET hits = hits + 1, last_seen = ?, referer = ?, user_agent = ? WHERE id = ?',
                    [$now, $referer, $ua, (int)$existing['id']]
                );
            } else {
                self::$db->execute(
                    'INSERT INTO not_found_logs (path, referer, user_agent, hits, last_seen)
                     VALUES (?, ?, ?, 1, ?)',
                    [$path, $referer, $ua, $now]
                );
            }
        } catch (\Throwable $e) {
            // never let logging break the request
        }
    }

    public static function listAll(int $limit = 300, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT * FROM not_found_logs ORDER BY last_seen DESC, hits DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /** Suggest up to 3 slugs that are similar to the requested path. */
    public static function suggest(string $path, int $limit = 3): array
    {
        $needle = trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $segments = explode('/', $needle);
        $tail = end($segments);
        if ($tail === '' || $tail === false) return [];

        // Simple similarity: slugs sharing a prefix or containing the tail
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $tail) . '%';
        return self::$db->query(
            'SELECT slug FROM translation_groups WHERE slug LIKE ? ORDER BY LENGTH(slug) LIMIT ?',
            [$like, $limit]
        );
    }

    public static function clear(): void
    {
        self::$db->execute('DELETE FROM not_found_logs');
    }
}
