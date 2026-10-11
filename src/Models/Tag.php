<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Tag
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function makeSlug(string $name): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}\-\s]/u', '', trim($name));
        $slug = preg_replace('/[\s_]+/u', '-', $slug);
        $slug = mb_strtolower(trim($slug, '-'));
        return $slug !== '' ? $slug : 'tag-' . bin2hex(random_bytes(4));
    }

    public static function findOrCreate(string $name): int
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Empty tag name');

        $row = self::$db->fetchOne('SELECT id FROM tags WHERE name = ?', [$name]);
        if ($row) return (int)$row['id'];

        $slug = self::makeSlug($name);
        $base = $slug;
        $i = 1;
        while (self::$db->fetchValue('SELECT 1 FROM tags WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . (++$i);
        }

        self::$db->execute(
            'INSERT INTO tags (name, slug) VALUES (?, ?)',
            [$name, $slug]
        );
        return self::$db->lastInsertId();
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::$db->fetchOne('SELECT * FROM tags WHERE slug = ?', [$slug]);
    }

    public static function listForPage(int $pageId): array
    {
        return self::$db->query(
            'SELECT t.* FROM tags t
             JOIN page_tags pt ON pt.tag_id = t.id
             WHERE pt.page_id = ?
             ORDER BY t.name',
            [$pageId]
        );
    }

    public static function listAll(): array
    {
        return self::$db->query(
            'SELECT t.*, COUNT(pt.page_id) AS usage_count
             FROM tags t
             LEFT JOIN page_tags pt ON pt.tag_id = t.id
             GROUP BY t.id
             ORDER BY usage_count DESC, t.name'
        );
    }

    public static function pagesByTag(int $tagId): array
    {
        return self::$db->query(
            'SELECT p.id, p.lang, p.title, p.updated_at, g.slug AS group_slug,
                    g.default_lang
             FROM pages p
             JOIN page_tags pt ON pt.page_id = p.id
             JOIN translation_groups g ON g.id = p.group_id
             WHERE pt.tag_id = ?
             ORDER BY p.updated_at DESC',
            [$tagId]
        );
    }

    public static function syncForPage(int $pageId, string $tagString): void
    {
        self::$db->execute('DELETE FROM page_tags WHERE page_id = ?', [$pageId]);

        $names = array_filter(array_map('trim', explode(',', $tagString)));
        $names = array_unique($names);

        foreach ($names as $name) {
            $tagId = self::findOrCreate($name);
            self::$db->execute(
                'INSERT INTO page_tags (page_id, tag_id) VALUES (?, ?)',
                [$pageId, $tagId]
            );
        }
    }

    public static function tagStringForPage(int $pageId): string
    {
        $rows = self::listForPage($pageId);
        return implode(', ', array_column($rows, 'name'));
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM page_tags WHERE tag_id = ?', [$id]);
        self::$db->execute('DELETE FROM tags WHERE id = ?', [$id]);
    }
}