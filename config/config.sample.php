<?php
/**
 * Sample configuration file for the Wiki project.
 *
 * Copy this file to config/config.php and fill in the values for your
 * environment. The installer does this automatically; use this file only
 * when performing a manual installation.
 *
 * Generate a fresh application key with:
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */

return [
    'db' => [
        // One of: sqlite, mysql, http
        'driver' => 'sqlite',

        // SQLite only
        'path' => __DIR__ . '/../data/wiki.sqlite',

        // MySQL only
        // 'host'     => '127.0.0.1',
        // 'port'     => 3306,
        // 'database' => 'wiki',
        // 'username' => 'wiki',
        // 'password' => '',

        // HTTP only
        // 'endpoint'    => 'https://example.com/wiki-db',
        // 'method'      => 'POST',
        // 'auth_type'   => 'bearer',      // none | bearer | basic | header | query
        // 'auth_token'  => '',
        // 'auth_header' => 'X-API-Key',   // used when auth_type is "header"
        // 'timeout'     => 15,
    ],

    'app' => [
        // 64-character hex string. Keep this secret.
        'key'          => 'replace-with-64-character-hex-string',
        'installed'    => false,
        'version'      => '0.1.0',
        'installed_at' => null,
    ],
];
