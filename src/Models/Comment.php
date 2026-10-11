<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Comment
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM comments WHERE id = ?', [$id]);
    }

    public static function listFor(string $type, int $targetId): array
    {
        return self::$db->query(
            'SELECT c.*, u.username AS author, u.avatar_url
             FROM comments c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.target_type = ? AND c.target_id = ?
             ORDER BY c.created_at ASC',
            [$type, $targetId]
        );
    }

    public static function count(string $type, int $targetId): int
    {
        return (int)self::$db->fetchValue(
            'SELECT COUNT(*) FROM comments WHERE target_type = ? AND target_id = ?',
            [$type, $targetId],
            0
        );
    }

    public static function create(string $type, int $targetId, string $content, ?int $userId): int
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO comments (target_type, target_id, content, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$type, $targetId, $content, $userId, $now, $now]
        );
        return self::$db->lastInsertId();
    }

    public static function update(int $id, string $content): void
    {
        self::$db->execute(
            'UPDATE comments SET content = ?, updated_at = ? WHERE id = ?',
            [$content, gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM comments WHERE id = ?', [$id]);
    }
}