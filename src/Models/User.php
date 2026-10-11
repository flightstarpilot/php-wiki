<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class User
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $u): ?array
    {
        return self::$db->fetchOne('SELECT * FROM users WHERE username = ?', [$u]);
    }

    public static function findByEmail(string $e): ?array
    {
        return self::$db->fetchOne('SELECT * FROM users WHERE email = ?', [$e]);
    }

    public static function findByOAuth(string $provider, string $oauthId): ?array
    {
        return self::$db->fetchOne(
            'SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?',
            [$provider, $oauthId]
        );
    }

    public static function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO users (username, email, password_hash, display_name, role, status,
                                email_verified, oauth_provider, oauth_id, avatar_url, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['username'],
                $data['email'],
                $data['password_hash'],
                $data['display_name'] ?? $data['username'],
                $data['role'] ?? 'user',
                $data['status'] ?? 'active',
                (int)($data['email_verified'] ?? 0),
                $data['oauth_provider'] ?? null,
                $data['oauth_id'] ?? null,
                $data['avatar_url'] ?? '',
                $now,
                $now,
            ]
        );
        return self::$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach ([
            'username', 'email', 'password_hash', 'display_name', 'avatar_url',
            'role', 'status', 'email_verified', 'oauth_provider', 'oauth_id',
        ] as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (!$fields) return;
        $fields[] = 'updated_at = ?'; $params[] = gmdate('Y-m-d H:i:s');
        $params[] = $id;
        self::$db->execute('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function listAll(int $limit = 100, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT * FROM users ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Paged + filtered listing.
     *
     * @param array{role?:string,status?:string,keyword?:string} $filters
     * @return array{items:array,total:int,page:int,per_page:int,pages:int}
     */
    public static function paged(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['role']) && in_array($filters['role'], ['user', 'editor', 'admin'], true)) {
            $where[] = 'role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'pending', 'banned'], true)) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(username LIKE ? OR email LIKE ? OR display_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = (int)self::$db->fetchValue("SELECT COUNT(*) FROM users $whereSql", $params, 0);

        $items = self::$db->query(
            "SELECT * FROM users $whereSql ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / max(1, $perPage)),
        ];
    }

    public static function count(): int
    {
        return (int)self::$db->fetchValue('SELECT COUNT(*) FROM users', [], 0);
    }

    /**
     * Count active administrators, optionally excluding a set of ids.
     */
    public static function countActiveAdmins(array $excludeIds = []): int
    {
        $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), fn($i) => $i > 0));
        if (!$excludeIds) {
            return (int)self::$db->fetchValue(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'",
                [],
                0
            );
        }
        $ph = implode(',', array_fill(0, count($excludeIds), '?'));
        return (int)self::$db->fetchValue(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id NOT IN ($ph)",
            $excludeIds,
            0
        );
    }

    /**
     * Given a list of user ids, return only those that are currently active admins.
     */
    public static function filterAdminIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::$db->query(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active' AND id IN ($ph)",
            $ids
        );
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    /**
     * Batch update a set of users. Only role, status, email_verified are accepted.
     */
    public static function batchUpdate(array $ids, array $fields): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
        if (!$ids || !$fields) return 0;

        $set = [];
        $params = [];
        foreach (['role', 'status', 'email_verified'] as $f) {
            if (array_key_exists($f, $fields)) {
                $set[] = "$f = ?";
                $params[] = $fields[$f];
            }
        }
        if (!$set) return 0;

        $set[] = 'updated_at = ?';
        $params[] = gmdate('Y-m-d H:i:s');

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($params, $ids);

        self::$db->execute('UPDATE users SET ' . implode(', ', $set) . " WHERE id IN ($ph)", $params);
        return count($ids);
    }

    public static function batchDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));
        if (!$ids) return 0;

        $ph = implode(',', array_fill(0, count($ids), '?'));
        self::$db->execute("DELETE FROM email_tokens WHERE user_id IN ($ph)", $ids);
        self::$db->execute("DELETE FROM users WHERE id IN ($ph)", $ids);
        return count($ids);
    }

    public static function delete(int $id): void
    {
        self::batchDelete([$id]);
    }

    public static function generateUsername(string $email, string $prefix = 'user'): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', strstr($email, '@', true) ?: ''));
        if ($base === '') $base = $prefix;
        $base = substr($base, 0, 24);

        $candidate = $base;
        $i = 1;
        while (self::$db->fetchValue('SELECT 1 FROM users WHERE username = ?', [$candidate])) {
            $candidate = $base . $i++;
        }
        return $candidate;
    }
}
