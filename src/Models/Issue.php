<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Issue
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne(
            'SELECT i.*, u.username AS author, g.slug AS group_slug
             FROM issues i
             LEFT JOIN users u ON u.id = i.created_by
             LEFT JOIN translation_groups g ON g.id = i.group_id
             WHERE i.id = ?',
            [$id]
        );
    }

    public static function listAll(string $status = '', int $limit = 100, int $offset = 0): array
    {
        $params = [];
        $where = '';
        if ($status !== '' && in_array($status, ['open', 'closed'], true)) {
            $where = 'WHERE i.status = ?';
            $params[] = $status;
        }
        $params[] = $limit;
        $params[] = $offset;

        return self::$db->query(
            "SELECT i.*, u.username AS author, g.slug AS group_slug
             FROM issues i
             LEFT JOIN users u ON u.id = i.created_by
             LEFT JOIN translation_groups g ON g.id = i.group_id
             $where
             ORDER BY i.updated_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function listByGroup(int $groupId): array
    {
        return self::$db->query(
            'SELECT i.*, u.username AS author
             FROM issues i
             LEFT JOIN users u ON u.id = i.created_by
             WHERE i.group_id = ?
             ORDER BY i.updated_at DESC',
            [$groupId]
        );
    }

    public static function countOpen(): int
    {
        return (int)self::$db->fetchValue(
            "SELECT COUNT(*) FROM issues WHERE status = 'open'",
            [],
            0
        );
    }

    public static function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO issues (group_id, page_id, title, content, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['group_id'],
                $data['page_id'] ?? null,
                $data['title'],
                $data['content'] ?? '',
                'open',
                $data['created_by'] ?? null,
                $now,
                $now,
            ]
        );
        return self::$db->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['open', 'closed'], true)) return;
        self::$db->execute(
            'UPDATE issues SET status = ?, updated_at = ? WHERE id = ?',
            [$status, gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function touch(int $id): void
    {
        self::$db->execute(
            'UPDATE issues SET updated_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM comments WHERE target_type = ? AND target_id = ?', ['issue', $id]);
        self::$db->execute('DELETE FROM issues WHERE id = ?', [$id]);
    }
}