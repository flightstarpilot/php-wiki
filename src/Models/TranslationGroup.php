<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class TranslationGroup
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM translation_groups WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::$db->fetchOne('SELECT * FROM translation_groups WHERE slug = ?', [$slug]);
    }

    public static function all(): array
    {
        return self::$db->query('SELECT * FROM translation_groups ORDER BY sort_order, id');
    }

    /** Groups with aggregated stats for the admin list. */
    public static function listAllWithStats(int $limit = 300, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM pages p WHERE p.group_id = g.id) AS lang_count,
                    (SELECT MAX(p.updated_at) FROM pages p WHERE p.group_id = g.id) AS last_update,
                    parent.slug AS parent_slug
             FROM translation_groups g
             LEFT JOIN translation_groups parent ON parent.id = g.parent_id
             ORDER BY g.sort_order, g.id
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function count(): int
    {
        return (int)self::$db->fetchValue('SELECT COUNT(*) FROM translation_groups', [], 0);
    }

    public static function create(string $slug, string $defaultLang, ?int $parentId = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO translation_groups (slug, default_lang, parent_id, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?)',
            [$slug, $defaultLang, $parentId, $now, $now]
        );
        return self::$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = []; $params = [];
        foreach (['slug', 'default_lang', 'parent_id', 'sort_order', 'is_locked'] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (!$fields) return;
        $fields[] = 'updated_at = ?'; $params[] = gmdate('Y-m-d H:i:s');
        $params[] = $id;
        self::$db->execute('UPDATE translation_groups SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function move(int $id, ?int $parentId, int $sortOrder): void
    {
        self::$db->execute(
            'UPDATE translation_groups SET parent_id = ?, sort_order = ?, updated_at = ? WHERE id = ?',
            [$parentId, $sortOrder, gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function setLocked(int $id, bool $locked): void
    {
        self::$db->execute(
            'UPDATE translation_groups SET is_locked = ?, updated_at = ? WHERE id = ?',
            [$locked ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]
        );
    }

    /** Delete a group and every language page under it. */
    public static function deleteCascade(int $id): void
    {
        $pages = self::$db->query('SELECT id FROM pages WHERE group_id = ?', [$id]);
        foreach ($pages as $p) {
            Page::delete((int)$p['id']);
        }
        self::$db->execute('DELETE FROM translation_groups WHERE id = ?', [$id]);
    }

    public static function makeSlug(string $text): string
    {
        $text = trim($text);
        if ($text === '') return 'page-' . bin2hex(random_bytes(4));
        $slug = preg_replace('/[^\p{L}\p{N}\-\s]/u', '', $text);
        $slug = preg_replace('/[\s_]+/u', '-', $slug);
        $slug = mb_strtolower(trim($slug, '-'));
        return $slug !== '' ? $slug : 'page-' . bin2hex(random_bytes(4));
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = $base; $i = 1;
        while (self::$db->fetchValue('SELECT 1 FROM translation_groups WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
