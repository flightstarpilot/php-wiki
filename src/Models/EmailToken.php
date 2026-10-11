<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class EmailToken
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function create(int $userId, string $type, int $ttlSeconds = 86400): string
    {
        $token = bin2hex(random_bytes(32));
        $now   = gmdate('Y-m-d H:i:s');
        $exp   = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        self::$db->execute('DELETE FROM email_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
        self::$db->execute(
            'INSERT INTO email_tokens (user_id, type, token, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $token, $exp, $now]
        );
        return $token;
    }

    public static function findValid(string $token, string $type): ?array
    {
        $row = self::$db->fetchOne(
            'SELECT * FROM email_tokens WHERE token = ? AND type = ?',
            [$token, $type]
        );
        if (!$row) return null;
        if (!empty($row['used_at'])) return null;
        if (strtotime((string)$row['expires_at']) < time()) return null;
        return $row;
    }

    public static function markUsed(int $id): void
    {
        self::$db->execute(
            'UPDATE email_tokens SET used_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public static function purgeExpired(): void
    {
        self::$db->execute(
            'DELETE FROM email_tokens WHERE expires_at < ?',
            [gmdate('Y-m-d H:i:s')]
        );
    }
}