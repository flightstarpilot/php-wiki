<?php
declare(strict_types=1);

namespace Wiki\Models;

use Wiki\Database\DatabaseInterface;

class Settings
{
    private static ?DatabaseInterface $db = null;
    private static array $cache = [];
    private static bool $loaded = false;

    public static function init(DatabaseInterface $db): void
    {
        self::$db = $db;
    }

    public static function defaults(): array
    {
        return [
            'site.name'          => '我的 Wiki',
            'site.title'         => '我的 Wiki',
            'site.subtitle'      => '',
            'site.description'   => '',
            'site.base_url'      => '',
            'site.logo'          => '',
            'site.favicon'       => '',
            'site.footer'        => '',
            'site.timezone'      => 'Asia/Shanghai',
            'site.default_lang'  => 'zh-CN',
            'site.home_slug'     => 'home',
            'site.maintenance'   => '0',
            'site.force_https'   => '0',
            'site.maintenance_ips' => '',

            'seo.robots'          => 'index,follow',
            'seo.sitemap_enabled' => '1',
            'seo.404_content'     => '',
            'seo.404_page_slug'   => '',

            'mail.enabled'        => '0',
            'mail.transport'      => 'smtp',
            'mail.host'           => '',
            'mail.port'           => '465',
            'mail.encryption'     => 'ssl',
            'mail.username'       => '',
            'mail.password'       => '',
            'mail.from_address'   => '',
            'mail.from_name'      => '{site_name}',
            'mail.tpl.verify_subject' => '[{site_name}] Verify your email',
            'mail.tpl.verify_body'    => "Hi {username},\n\nPlease verify your email by visiting:\n{link}\n\nThis link expires in 24 hours.\n\n{site_name}",
            'mail.tpl.reset_subject'  => '[{site_name}] Password reset',
            'mail.tpl.reset_body'     => "Hi {username},\n\nReset your password by visiting:\n{link}\n\nThis link expires in 1 hour.\n\n{site_name}",

            'auth.allow_register'      => '1',
            'auth.invite_only'         => '0',
            'auth.email_verify'        => '0',
            'auth.register_review'     => '0',
            'auth.github_oauth'        => '0',
            'auth.github_client_id'    => '',
            'auth.github_client_secret'=> '',
            'auth.default_role'        => 'user',

            'feature.anonymous_read' => '1',
            'feature.comments'       => '1',
            'feature.issues'         => '1',
            'feature.proposals'      => '1',
            'feature.attachments'    => '1',
            'feature.history'        => '1',
            'feature.backlinks'      => '1',
            'feature.tags'           => '1',
            'feature.search'         => '1',
            'feature.fuzzy_search'   => '0',
            'feature.page_lock'      => '1',
            'feature.auto_redirect'  => '1',
            'feature.rss'            => '1',
            'feature.multi_lang'     => '1',
            'feature.api_read'       => '0',

            'storage.cache'          => 'none',
            'storage.redis_host'     => '127.0.0.1',
            'storage.redis_port'     => '6379',
            'storage.redis_password' => '',
            'storage.redis_db'       => '0',

            'attachment.driver'        => 'local',
            'attachment.allowed_types' => 'jpg,jpeg,png,gif,webp,svg,pdf,zip,md,txt',
            'attachment.max_size'      => '10485760',
            'attachment.s3_endpoint'   => '',
            'attachment.s3_bucket'     => '',
            'attachment.s3_region'     => 'auto',
            'attachment.s3_access_key' => '',
            'attachment.s3_secret_key' => '',
            'attachment.s3_public_url' => '',

            'search.mode' => 'simple',

            'ui.theme_color' => '#3b82f6',
            'ui.dark_mode'   => 'auto',
            'ui.custom_css'  => '',
            'ui.custom_js'   => '',
        ];
    }

    public static function seedDefaults(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach (self::defaults() as $k => $v) {
            $exists = self::$db->fetchValue('SELECT 1 FROM settings WHERE key_name = ?', [$k]);
            if (!$exists) {
                self::$db->execute(
                    'INSERT INTO settings (key_name, value, updated_at) VALUES (?, ?, ?)',
                    [$k, (string)$v, $now]
                );
            }
        }
    }

    private static function loadAll(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;
        try {
            $rows = self::$db->query('SELECT key_name, value FROM settings');
            foreach ($rows as $r) self::$cache[$r['key_name']] = $r['value'];
        } catch (\Throwable $e) {
            // table may not exist yet
        }
    }

    public static function get(string $key, $default = null)
    {
        self::loadAll();
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        $defs = self::defaults();
        return $defs[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? '1' : '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    public static function set(string $key, $value): void
    {
        self::loadAll();
        $value = (string)$value;
        $now = gmdate('Y-m-d H:i:s');
        $exists = self::$db->fetchValue('SELECT 1 FROM settings WHERE key_name = ?', [$key]);
        if ($exists) {
            self::$db->execute(
                'UPDATE settings SET value = ?, updated_at = ? WHERE key_name = ?',
                [$value, $now, $key]
            );
        } else {
            self::$db->execute(
                'INSERT INTO settings (key_name, value, updated_at) VALUES (?, ?, ?)',
                [$key, $value, $now]
            );
        }
        self::$cache[$key] = $value;
    }

    public static function all(): array
    {
        self::loadAll();
        return self::$cache;
    }
}
