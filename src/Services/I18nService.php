<?php
declare(strict_types=1);

namespace Wiki\Services;

/**
 * UI language negotiation and translation lookup.
 *
 * Resolution order:
 *   1. session override (set via /lang/{code})
 *   2. HTTP_ACCEPT_LANGUAGE header
 *   3. fallback to en-US
 *
 * Language packs consist of two files:
 *   lang/{code}.php        - the base pack
 *   lang/{code}.extra.php  - incremental additions merged on top
 */
class I18nService
{
    private static string $current = 'en-US';
    private static array $strings = [];
    private static bool $loaded = false;

    public static function supported(): array
    {
        return ['en-US', 'zh-CN', 'ja-JP', 'ko-KR', 'fr-FR', 'de-DE', 'es-ES', 'ru-RU'];
    }

    public static function detect(): void
    {
        $supported = self::supported();

        if (!empty($_SESSION['ui_lang']) && in_array($_SESSION['ui_lang'], $supported, true)) {
            self::$current = $_SESSION['ui_lang'];
            return;
        }

        $match = self::negotiate($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $supported);
        if ($match !== null) {
            self::$current = $match;
            return;
        }

        // Fall back to the site's configured default language when the
        // browser does not express a preference we can honour.
        $siteDefault = (string)\Wiki\Models\Settings::get('site.default_lang', '');
        if ($siteDefault !== '' && in_array($siteDefault, $supported, true)) {
            self::$current = $siteDefault;
            return;
        }

        self::$current = 'en-US';
    }

    private static function negotiate(string $header, array $supported): ?string
    {
        if ($header === '') return null;

        $entries = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') continue;

            $q = 1.0;
            if (strpos($part, ';q=') !== false) {
                [$tag, $qs] = explode(';q=', $part, 2);
                $q = (float)$qs;
            } else {
                $tag = $part;
            }
            $tag = trim($tag);
            if ($tag === '') continue;
            $entries[] = ['tag' => $tag, 'q' => $q];
        }

        usort($entries, fn($a, $b) => $b['q'] <=> $a['q']);

        foreach ($entries as $entry) {
            $tag = strtolower($entry['tag']);

            foreach ($supported as $s) {
                if (strtolower($s) === $tag) return $s;
            }

            $prefix = explode('-', $tag)[0];
            foreach ($supported as $s) {
                if (strtolower(explode('-', $s)[0]) === $prefix) return $s;
            }
        }

        return null;
    }

    public static function set(string $lang): bool
    {
        if (!in_array($lang, self::supported(), true)) return false;
        $_SESSION['ui_lang'] = $lang;
        self::$current = $lang;
        self::$strings = [];
        self::$loaded = false;
        return true;
    }

    public static function current(): string
    {
        return self::$current;
    }

    private static function load(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;

        self::$strings = self::readPack(self::$current);

        // Fallback to English for missing keys
        if (self::$current !== 'en-US') {
            self::$strings += self::readPack('en-US');
        }
    }

    private static function readPack(string $code): array
    {
        $out = [];

        $base = WIKI_ROOT . '/lang/' . $code . '.php';
        if (is_file($base)) {
            $arr = require $base;
            if (is_array($arr)) $out = $arr;
        }

        $extra = WIKI_ROOT . '/lang/' . $code . '.extra.php';
        if (is_file($extra)) {
            $arr = require $extra;
            if (is_array($arr)) $out = array_merge($out, $arr);
        }

        return $out;
    }

    public static function t(string $key, array $params = []): string
    {
        self::load();
        $s = self::$strings[$key] ?? $key;
        foreach ($params as $k => $v) {
            $s = str_replace('{' . $k . '}', (string)$v, $s);
        }
        return $s;
    }
}
