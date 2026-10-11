<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;
use Wiki\Services\MarkdownService;
use Wiki\Services\SearchService;

class Page
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM pages WHERE id = ?', [$id]);
    }

    public static function findByGroupLang(int $groupId, string $lang): ?array
    {
        return self::$db->fetchOne(
            'SELECT * FROM pages WHERE group_id = ? AND lang = ?',
            [$groupId, $lang]
        );
    }

    public static function listByGroup(int $groupId): array
    {
        return self::$db->query(
            'SELECT id, lang, title, updated_at FROM pages WHERE group_id = ? ORDER BY lang',
            [$groupId]
        );
    }

    public static function titlesByGroup(): array
    {
        $rows = self::$db->query('SELECT group_id, lang, title FROM pages');
        $out = [];
        foreach ($rows as $r) $out[$r['group_id']][$r['lang']] = $r['title'];
        return $out;
    }

    public static function countAll(): int
    {
        return (int)self::$db->fetchValue('SELECT COUNT(*) FROM pages', [], 0);
    }

    public static function recentUpdates(int $limit = 30): array
    {
        return self::$db->query(
            'SELECT p.id, p.lang, p.title, p.updated_at, g.slug AS group_slug, g.default_lang
             FROM pages p
             JOIN translation_groups g ON g.id = p.group_id
             ORDER BY p.updated_at DESC
             LIMIT ?',
            [$limit]
        );
    }

    /** Admin list with group and language context. */
    public static function listAllWithGroup(int $limit = 300, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT p.*, g.slug AS group_slug, g.default_lang, g.is_locked AS group_locked
             FROM pages p
             JOIN translation_groups g ON g.id = p.group_id
             ORDER BY g.slug, p.lang
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function create(int $groupId, string $lang, string $title, string $content, int $userId, string $comment = ''): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $outline = MarkdownService::extractOutline($content);

        self::$db->execute(
            'INSERT INTO pages (group_id, lang, title, content, outline, current_version, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)',
            [$groupId, $lang, $title, $content, $outline, $userId, $userId, $now, $now]
        );
        $pageId = self::$db->lastInsertId();

        self::$db->execute(
            'INSERT INTO page_versions (page_id, version, title, content, comment, created_by, created_at)
             VALUES (?, 1, ?, ?, ?, ?, ?)',
            [$pageId, $title, $content, $comment, $userId, $now]
        );

        self::syncLinks($pageId, $content);
        self::syncSearch($pageId, $title, $content, $outline);
        return $pageId;
    }

    public static function save(int $pageId, string $title, string $content, int $userId, string $comment = ''): int
    {
        $page = self::find($pageId);
        if (!$page) throw new \RuntimeException('Page not found');

        $newVersion = (int)$page['current_version'] + 1;
        $now = gmdate('Y-m-d H:i:s');
        $outline = MarkdownService::extractOutline($content);

        self::$db->execute(
            'UPDATE pages SET title = ?, content = ?, outline = ?, current_version = ?, updated_by = ?, updated_at = ?
             WHERE id = ?',
            [$title, $content, $outline, $newVersion, $userId, $now, $pageId]
        );
        self::$db->execute(
            'INSERT INTO page_versions (page_id, version, title, content, comment, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$pageId, $newVersion, $title, $content, $comment, $userId, $now]
        );

        self::syncLinks($pageId, $content);
        self::syncSearch($pageId, $title, $content, $outline);
        return $newVersion;
    }

    public static function setLocked(int $pageId, bool $locked, ?int $byUserId = null, ?string $reason = null): void
    {
        if ($locked) {
            self::$db->execute(
                'UPDATE pages SET is_locked = 1, locked_by = ?, locked_reason = ?, locked_at = ? WHERE id = ?',
                [$byUserId, $reason, gmdate('Y-m-d H:i:s'), $pageId]
            );
        } else {
            self::$db->execute(
                'UPDATE pages SET is_locked = 0, locked_by = NULL, locked_reason = NULL, locked_at = NULL WHERE id = ?',
                [$pageId]
            );
        }
    }

    /** Delete a single language version and everything that hangs off it. */
    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM page_links WHERE source_page_id = ?', [$id]);
        self::$db->execute('UPDATE page_links SET target_page_id = NULL WHERE target_page_id = ?', [$id]);
        self::$db->execute('DELETE FROM page_tags WHERE page_id = ?', [$id]);
        self::$db->execute('DELETE FROM page_versions WHERE page_id = ?', [$id]);

        try {
            self::$db->execute('DELETE FROM comments WHERE target_type = ? AND target_id IN (SELECT id FROM proposals WHERE page_id = ?)', ['proposal', $id]);
            self::$db->execute('DELETE FROM proposals WHERE page_id = ?', [$id]);
        } catch (\Throwable $e) {
            // proposals table may not exist in very old schemas
        }

        self::$db->execute('DELETE FROM pages WHERE id = ?', [$id]);

        if (self::$db->driver() === 'sqlite' && SearchService::supportsFts()) {
            try { self::$db->execute('DELETE FROM pages_fts WHERE rowid = ?', [$id]); } catch (\Throwable $e) {}
        }
    }

    public static function versions(int $pageId): array
    {
        return self::$db->query(
            'SELECT v.*, u.username AS author
             FROM page_versions v
             LEFT JOIN users u ON u.id = v.created_by
             WHERE v.page_id = ? ORDER BY v.version DESC',
            [$pageId]
        );
    }

    private static function syncLinks(int $pageId, string $content): void
    {
        $slugs = MarkdownService::extractLinkSlugs($content);
        self::$db->execute('DELETE FROM page_links WHERE source_page_id = ?', [$pageId]);

        $now = gmdate('Y-m-d H:i:s');
        foreach ($slugs as $slug) {
            $targetPageId = null;
            $g = TranslationGroup::findBySlug($slug);
            if ($g) {
                $id = self::$db->fetchValue(
                    'SELECT id FROM pages WHERE group_id = ? ORDER BY (lang = ?) DESC LIMIT 1',
                    [$g['id'], $g['default_lang']]
                );
                $targetPageId = $id ? (int)$id : null;
            }
            self::$db->execute(
                'INSERT INTO page_links (source_page_id, target_page_id, target_slug, created_at)
                 VALUES (?, ?, ?, ?)',
                [$pageId, $targetPageId, $slug, $now]
            );
        }
    }

    private static function syncSearch(int $pageId, string $title, string $content, string $outline): void
    {
        if (self::$db->driver() !== 'sqlite') return;
        if (!SearchService::supportsFts()) return;

        try {
            self::$db->execute('DELETE FROM pages_fts WHERE rowid = ?', [$pageId]);
            self::$db->execute(
                'INSERT INTO pages_fts (rowid, title, content, outline) VALUES (?, ?, ?, ?)',
                [$pageId, $title, $content, $outline]
            );
        } catch (\Throwable $e) {
            // FTS table may not exist yet; rebuild via admin
        }
    }

    public static function backlinks(int $pageId): array
    {
        return self::$db->query(
            'SELECT DISTINCT g.slug, p.title, p.lang
             FROM page_links l
             JOIN pages p ON p.id = l.source_page_id
             JOIN translation_groups g ON g.id = p.group_id
             WHERE l.target_page_id = ?',
            [$pageId]
        );
    }
}
