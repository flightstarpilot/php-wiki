<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Auth\Auth;
use Wiki\Database\DatabaseInterface;

class AuditLog
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    /**
     * Record an event.
     *
     * @param string $action   Event identifier, e.g. "user_status_update"
     * @param string $targetType Optional target type, e.g. "user", "page"
     * @param int|null $targetId Optional target id
     * @param array|string|null $detail Extra data; arrays are JSON-encoded
     */
    public static function log(string $action, string $targetType = '', ?int $targetId = null, $detail = null): void
    {
        try {
            $user = Auth::user();
            $userId = $user ? (int)$user['id'] : null;

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $ip = substr($ip, 0, 64);

            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

            if (is_array($detail) || is_object($detail)) {
                $detail = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            self::$db->execute(
                'INSERT INTO audit_logs (user_id, action, target_type, target_id, detail, ip, ua, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $action, $targetType, $targetId, (string)$detail, $ip, $ua, gmdate('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            // never let logging break a request
        }
    }

    /**
     * Paged list with optional filters.
     *
     * @return array{items: array, total: int, page: int, per_page: int, pages: int}
     */
    public static function paged(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action LIKE ?';
            $params[] = $filters['action'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['target_type'])) {
            $where[] = 'a.target_type = ?';
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(a.detail LIKE ? OR a.action LIKE ? OR a.ip LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = (int)self::$db->fetchValue(
            "SELECT COUNT(*) FROM audit_logs a $whereSql",
            $params,
            0
        );

        $items = self::$db->query(
            "SELECT a.*, u.username AS actor, u.role AS actor_role
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             $whereSql
             ORDER BY a.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ];
    }

    public static function listAll(int $limit = 200, int $offset = 0, string $action = ''): array
    {
        $r = self::paged(['action' => $action], 1, max(1, $limit));
        return $r['items'];
    }

    public static function clear(): void
    {
        self::$db->execute('DELETE FROM audit_logs');
    }

    /** Single-line, log-file / HAR style. */
    public static function toLine(array $entry): string
    {
        $time = self::formatTime((string)$entry['created_at']);
        $actor = self::actorLabel($entry);
        $event = strtoupper((string)$entry['action']);
        $target = self::targetLabel($entry);
        $detail = self::detailLabel($entry);
        $ip = (string)$entry['ip'];
        $ua = (string)($entry['ua'] ?? '');

        $parts = [
            '[' . $time . ']',
            '[' . $actor . ']',
            'EVENT:' . $event,
        ];
        if ($target !== '') $parts[] = 'target:' . $target;
        if ($detail !== '') $parts[] = $detail;
        if ($ip !== '') $parts[] = 'ip:' . $ip;
        if ($ua !== '') $parts[] = 'UA:' . $ua;

        return implode(' | ', $parts);
    }

    /** Multi-line human-friendly block. */
    public static function toFriendly(array $entry): array
    {
        return [
            'time'   => self::formatTime((string)$entry['created_at']),
            'actor'  => self::actorLabel($entry),
            'event'  => (string)$entry['action'],
            'target' => self::targetLabel($entry),
            'detail' => self::detailLabel($entry),
            'ip'     => (string)$entry['ip'],
            'ua'     => (string)($entry['ua'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function formatTime(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');
        if ($ts === false) $ts = time();
        $tz = (string)\Wiki\Models\Settings::get('site.timezone', 'UTC');
        try {
            $d = new \DateTime('@' . $ts);
            $d->setTimezone(new \DateTimeZone($tz));
            return $d->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return gmdate('Y-m-d H:i:s', $ts);
        }
    }

    private static function actorLabel(array $entry): string
    {
        if (empty($entry['user_id'])) return 'anonymous';
        $role = $entry['actor_role'] ?? '';
        $name = $entry['actor'] ?? ('#' . (int)$entry['user_id']);
        $label = $name;
        if ($role !== '') $label .= ':' . $role;
        return $label . '(id:' . (int)$entry['user_id'] . ')';
    }

    private static function targetLabel(array $entry): string
    {
        $type = (string)($entry['target_type'] ?? '');
        $id   = $entry['target_id'] ?? null;
        if ($type === '' && $id === null) return '';
        if ($type === '') return 'id:' . (int)$id;
        if ($id === null) return $type;
        return $type . ':' . (int)$id;
    }

    private static function detailLabel(array $entry): string
    {
        $raw = (string)($entry['detail'] ?? '');
        if ($raw === '') return '';

        $json = json_decode($raw, true);
        if (!is_array($json)) return $raw;

        $pairs = [];
        foreach ($json as $k => $v) {
            if (is_bool($v)) $v = $v ? 'true' : 'false';
            elseif ($v === null) $v = 'null';
            elseif (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            $pairs[] = $k . ':' . $v;
        }
        return implode(' ', $pairs);
    }
}
