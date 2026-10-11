<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Models\Settings;

/**
 * Optional Redis cache. Falls back to a no-op when Redis is unavailable or
 * when storage.cache is not set to "redis".
 */
class CacheService
{
    private static ?\Redis $redis = null;
    private static bool $initialized = false;
    private static string $prefix = 'wiki:';

    public static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;

        if ((string)Settings::get('storage.cache', 'none') !== 'redis') return;
        if (!class_exists('Redis')) return;

        $host = (string)Settings::get('storage.redis_host', '127.0.0.1');
        $port = (int)Settings::get('storage.redis_port', 6379);
        $pass = (string)Settings::get('storage.redis_password', '');
        $db   = (int)Settings::get('storage.redis_db', 0);

        try {
            $r = new \Redis();
            if (!$r->connect($host, $port, 1.5)) return;
            if ($pass !== '') $r->auth($pass);
            if ($db > 0) $r->select($db);
            $r->ping();
            self::$redis = $r;
        } catch (\Throwable $e) {
            self::$redis = null;
        }
    }

    public static function available(): bool
    {
        self::init();
        return self::$redis !== null;
    }

    public static function get(string $key)
    {
        if (!self::available()) return null;
        try {
            $raw = self::$redis->get(self::$prefix . $key);
            if ($raw === false || $raw === null) return null;
            return json_decode((string)$raw, true);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function set(string $key, $value, int $ttl = 3600): void
    {
        if (!self::available()) return;
        try {
            self::$redis->setex(self::$prefix . $key, $ttl, json_encode($value));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function delete(string $key): void
    {
        if (!self::available()) return;
        try { self::$redis->del(self::$prefix . $key); } catch (\Throwable $e) {}
    }

    public static function flush(): void
    {
        if (!self::available()) return;
        try {
            $keys = self::$redis->keys(self::$prefix . '*');
            if ($keys) self::$redis->del($keys);
        } catch (\Throwable $e) {}
    }

    public static function test(): array
    {
        if (!class_exists('Redis')) {
            return ['ok' => false, 'message' => 'The Redis extension is not installed'];
        }
        if ((string)Settings::get('storage.cache', 'none') !== 'redis') {
            return ['ok' => false, 'message' => 'storage.cache is not set to "redis"'];
        }

        self::$initialized = false;
        self::$redis = null;
        self::init();

        if (self::$redis === null) {
            return ['ok' => false, 'message' => 'Could not connect to Redis'];
        }
        return ['ok' => true, 'message' => 'Connection OK'];
    }
}
