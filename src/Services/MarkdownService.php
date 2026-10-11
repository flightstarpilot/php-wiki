<?php
declare(strict_types=1);

namespace Wiki\Services;

/**
 * Markdown rendering through Parsedown.
 *
 * ParsedownExtra is optional. When present, it adds Markdown Extra syntax
 * such as footnotes and definition lists. When absent, the base Parsedown
 * parser is used, which covers the common syntax.
 *
 * If neither file is present under vendor/parsedown/, rendering falls back
 * to a plain-text block with a visible hint.
 */
class MarkdownService
{
    private static ?object $parser = null;

    private static function parser(): object
    {
        if (self::$parser) return self::$parser;

        $base = WIKI_ROOT . '/vendor/parsedown/';

        $parsedown = $base . 'Parsedown.php';
        $extra     = $base . 'ParsedownExtra.php';

        if (is_file($parsedown)) {
            require_once $parsedown;
        }
        if (is_file($extra)) {
            require_once $extra;
        }

        if (class_exists('\\ParsedownExtra')) {
            self::$parser = new \ParsedownExtra();
        } elseif (class_exists('\\Parsedown')) {
            self::$parser = new \Parsedown();
        } else {
            self::$parser = new class {
                public function setSafeMode(bool $v) { return $this; }
                public function setBreaksEnabled(bool $v) { return $this; }
                public function text(string $t): string {
                    return '<div class="text-xs text-red-600 mb-2">'
                         . htmlspecialchars('Markdown renderer not available. '
                           . 'Place Parsedown.php under vendor/parsedown/.', ENT_QUOTES, 'UTF-8')
                         . '</div>'
                         . '<pre class="whitespace-pre-wrap text-sm">'
                         . htmlspecialchars($t, ENT_QUOTES, 'UTF-8')
                         . '</pre>';
                }
            };
        }

        if (method_exists(self::$parser, 'setSafeMode')) {
            self::$parser->setSafeMode(true);
        }
        if (method_exists(self::$parser, 'setBreaksEnabled')) {
            self::$parser->setBreaksEnabled(true);
        }

        return self::$parser;
    }

    public static function preprocessWikiLinks(string $text): string
    {
        return preg_replace_callback(
            '/\[\[([^\]\|#]+)(#[^\]\|]+)?(\|([^\]]+))?\]\]/u',
            function ($m) {
                $slug   = trim($m[1]);
                $anchor = isset($m[2]) ? trim($m[2]) : '';
                $label  = isset($m[4]) && $m[4] !== '' ? trim($m[4]) : $slug;
                return '[' . $label . '](' . url('page/' . $slug) . $anchor . ')';
            },
            $text
        );
    }

    public static function render(string $text): string
    {
        return self::parser()->text(self::preprocessWikiLinks($text));
    }

    public static function extractLinkSlugs(string $text): array
    {
        if (!preg_match_all('/\[\[([^\]\|#]+)(#[^\]\|]+)?(\|[^\]]+)?\]\]/u', $text, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('trim', $m[1])));
    }

    public static function extractOutline(string $text): string
    {
        $lines = [];
        if (preg_match_all('/^#{1,6}\s+(.+)$/m', $text, $m)) {
            foreach ($m[1] as $h) $lines[] = trim($h);
        }
        return implode("\n", $lines);
    }
}
