<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Invite
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function findByCode(string $code): ?array
    {
        return self::$db->fetchOne('SELECT * FROM invites WHERE code = ?', [$code]);
    }

    public static function isValid(string $code): bool
    {
        $i = self::findByCode($code);
        if (!$i) return false;
        if ($i['expires_at'] !== null && strtotime((string)$i['expires_at']) < time()) return false;
        if ((int)$i['used_count'] >= (int)$i['max_uses']) return false;
        return true;
    }

    public static function consume(string $code, int $userId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'UPDATE invites SET used_count = used_count + 1, used_by = ?, used_at = ? WHERE code = ?',
            [$userId, $now, $code]
        );
    }

    public static function create(array $data): int
    {
        $code = $data['code'] ?? strtoupper(bin2hex(random_bytes(6)));
        $now  = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO invites (code, created_by, expires_at, max_uses, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $code,
                $data['created_by'] ?? null,
                $data['expires_at'] ?? null,
                (int)($data['max_uses'] ?? 1),
                (string)($data['note'] ?? ''),
                $now,
            ]
        );
        return self::$db->lastInsertId();
    }

    public static function listAll(): array
    {
        return self::$db->query(
            'SELECT i.*, u.username AS creator
             FROM invites i
             LEFT JOIN users u ON u.id = i.created_by
             ORDER BY i.created_at DESC'
        );
    }

    public static function delete(int $id): void
    {
        self::$db->execute('DELETE FROM invites WHERE id = ?', [$id]);
    }
}