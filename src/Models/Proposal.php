<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Proposal
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne(
            'SELECT p.*, u.username AS author, r.username AS reviewer
             FROM proposals p
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN users r ON r.id = p.reviewed_by
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function listAll(string $status = '', int $limit = 100, int $offset = 0): array
    {
        $params = [];
        $where = '';
        if ($status !== '' && in_array($status, ['open', 'merged', 'closed'], true)) {
            $where = 'WHERE p.status = ?';
            $params[] = $status;
        }
        $params[] = $limit;
        $params[] = $offset;

        return self::$db->query(
            "SELECT p.*, u.username AS author, g.slug AS group_slug
             FROM proposals p
             LEFT JOIN users u ON u.id = p.created_by
             LEFT JOIN pages pg ON pg.id = p.page_id
             LEFT JOIN translation_groups g ON g.id = pg.group_id
             $where
             ORDER BY p.updated_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function listByPage(int $pageId): array
    {
        return self::$db->query(
            'SELECT p.*, u.username AS author
             FROM proposals p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.page_id = ?
             ORDER BY p.updated_at DESC',
            [$pageId]
        );
    }

    public static function countOpen(): int
    {
        return (int)self::$db->fetchValue(
            "SELECT COUNT(*) FROM proposals WHERE status = 'open'",
            [],
            0
        );
    }

    public static function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO proposals
                (page_id, title, description, status, base_version,
                 proposed_title, proposed_content, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['page_id'],
                $data['title'],
                $data['description'] ?? '',
                'open',
                $data['base_version'],
                $data['proposed_title'],
                $data['proposed_content'],
                $data['created_by'] ?? null,
                $now,
                $now,
            ]
        );
        return self::$db->lastInsertId();
    }

    public static function setStatus(int $id, string $status, ?int $reviewerId = null, ?int $mergedVersion = null): void
    {
        if (!in_array($status, ['open', 'merged', 'closed'], true)) return;
        $now = gmdate('Y-m-d H:i:s');
        if ($mergedVersion !== null) {
            self::$db->execute(
                'UPDATE proposals SET status = ?, reviewed_by = ?, merged_version = ?, updated_at = ? WHERE id = ?',
                [$status, $reviewerId, $mergedVersion, $now, $id]
            );
        } else {
            self::$db->execute(
                'UPDATE proposals SET status = ?, reviewed_by = ?, updated_at = ? WHERE id = ?',
                [$status, $reviewerId, $now, $id]
            );
        }
    }

    public static function touch(int $id): void
    {
        self::$db->execute(
            'UPDATE proposals SET updated_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM comments WHERE target_type = ? AND target_id = ?', ['proposal', $id]);
        self::$db->execute('DELETE FROM proposals WHERE id = ?', [$id]);
    }

    /**
     * Fetch the version snapshot a proposal was based on.
     * Returns null if the base version no longer exists.
     */
    public static function baseVersion(array $proposal): ?array
    {
        return self::$db->fetchOne(
            'SELECT * FROM page_versions WHERE page_id = ? AND version = ?',
            [(int)$proposal['page_id'], (int)$proposal['base_version']]
        );
    }
}