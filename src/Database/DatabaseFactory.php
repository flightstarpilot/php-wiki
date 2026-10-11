<?php
declare(strict_types=1);

namespace Wiki\Database;

class DatabaseFactory
{
    public static function make(array $cfg): DatabaseInterface
    {
        $driver = (string)($cfg['driver'] ?? 'sqlite');

        return match ($driver) {
            'mysql', 'sqlite' => new PdoDatabase($cfg),
            'http'            => new HttpDatabase($cfg),
            default           => throw new \RuntimeException('Unknown database driver: ' . $driver),
        };
    }
}
