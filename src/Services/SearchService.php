<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Database\DatabaseInterface;
use Wiki\Models\Settings;

class SearchService
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function mode(): string
    {
        $configured = (string)Settings::get('search.mode', 'simple');
        if ($configured === 'fts' && self::supportsFts()) return 'fts';
        return 'simple';
    }

    public static function supportsFts(): bool
    {
        $driver = self::$db->driver();

        // HTTP backend: we do not know what engine is behind it. Always
        // fall back to LIKE-based search.
        if ($driver === 'http') return false;

        if ($driver === 'sqlite') {
            return (bool)self::$db->fetchValue(
                "SELECT 1 FROM pragma_compile_options WHERE compile_options LIKE '%FTS5%'",
                [],
                false
            );
        }

        return true;
    }

    public static function rebuildIndex(): int
    {
        $driver = self::$db->driver();

        if ($driver === 'http') {
            return 0;
        }

        if ($driver === 'sqlite') {
            self::$db->pdo()->exec('DROP TABLE IF EXISTS pages_fts');
            self::$db->pdo()->exec(
                "CREATE VIRTUAL TABLE pages_fts USING fts5(
                    title, content, outline,
                    content='pages', content_rowid='id',
                    tokenize='unicode61'
                )"
            );
            self::$db->pdo()->exec(
                "INSERT INTO pages_fts (rowid, title, content, outline)
                 SELECT id, title, COALESCE(content,''), COALESCE(outline,'') FROM pages"
            );
            return (int)self::$db->fetchValue('SELECT COUNT(*) FROM pages_fts', [], 0);
        }

        try { self::$db->pdo()->exec('ALTER TABLE pages DROP INDEX ft_pages'); } catch (\Throwable $e) {}
        try {
            self::$db->pdo()->exec('ALTER TABLE pages ADD FULLTEXT INDEX ft_pages (title, content)');
        } catch (\Throwable $e) {}
        return (int)self::$db->fetchValue('SELECT COUNT(*) FROM pages', [], 0);
    }

    public static function search(string $query, string $lang = '', int $limit = 30): array
    {
        $query = trim($query);
        if ($query === '') return [];

        if (self::mode() === 'fts') {
            $driver = self::$db->driver();
            if ($driver === 'sqlite') return self::searchSqliteFts($query, $lang, $limit);
            if ($driver === 'mysql')  return self::searchMysqlFts($query, $lang, $limit);
        }

        return self::searchSimple($query, $lang, $limit);
    }

    private static function searchSqliteFts(string $query, string $lang, int $limit): array
    {
        $ftQuery = self::toFtsQuery($query);
        $params = [$ftQuery];
        $langClause = '';
        if ($lang !== '') {
            $langClause = 'AND p.lang = ?';
            $params[] = $lang;
        }
        $params[] = $limit;

        $sql = "SELECT p.id AS page_id, p.group_id, g.slug, p.lang, p.title,
                       snippet(pages_fts, 1, '<mark>', '</mark>', '...', 20) AS snippet,
                       bm25(pages_fts, 10.0, 1.0, 5.0) AS score
                FROM pages_fts
                JOIN pages p ON p.id = pages_fts.rowid
                JOIN translation_groups g ON g.id = p.group_id
                WHERE pages_fts MATCH ?
                $langClause
                ORDER BY score
                LIMIT ?";

        try {
            return self::$db->query($sql, $params);
        } catch (\Throwable $e) {
            return self::searchSimple($query, $lang, $limit);
        }
    }

    private static function searchMysqlFts(string $query, string $lang, int $limit): array
    {
        $params = [$query, $query];
        $langClause = '';
        if ($lang !== '') {
            $langClause = 'AND p.lang = ?';
            $params[] = $lang;
        }
        $params[] = $limit;

        $sql = "SELECT p.id AS page_id, p.group_id, g.slug, p.lang, p.title,
                       SUBSTRING(p.content, 1, 240) AS snippet,
                       MATCH(p.title, p.content) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
                FROM pages p
                JOIN translation_groups g ON g.id = p.group_id
                WHERE MATCH(p.title, p.content) AGAINST (? IN NATURAL LANGUAGE MODE)
                $langClause
                ORDER BY score DESC
                LIMIT ?";

        try {
            return self::$db->query($sql, $params);
        } catch (\Throwable $e) {
            return self::searchSimple($query, $lang, $limit);
        }
    }

    private static function searchSimple(string $query, string $lang, int $limit): array
    {
        $like = '%' . $query . '%';
        $params = [$like, $like, $like];
        $langClause = '';
        if ($lang !== '') {
            $langClause = 'AND p.lang = ?';
            $params[] = $lang;
        }
        $params[] = $limit;

        $sql = "SELECT p.id AS page_id, p.group_id, g.slug, p.lang, p.title,
                       SUBSTRING(COALESCE(p.content,''), 1, 240) AS snippet,
                       0.0 AS score
                FROM pages p
                JOIN translation_groups g ON g.id = p.group_id
                WHERE (p.title LIKE ? OR p.content LIKE ? OR p.outline LIKE ?)
                $langClause
                ORDER BY p.updated_at DESC
                LIMIT ?";

        return self::$db->query($sql, $params);
    }

    private static function toFtsQuery(string $query): string
    {
        $parts = preg_split('/\s+/u', $query);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p, '"\'');
            if ($p === '') continue;
            $p = str_replace('"', '""', $p);
            $out[] = '"' . $p . '"';
        }
        return implode(' ', $out) ?: '""';
    }
}
