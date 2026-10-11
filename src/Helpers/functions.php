<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = WIKI_ROOT . '/config/config.php';
        $cfg = is_file($file) ? require $file : [];
    }
    if ($key === null) return $cfg;

    $cur = $cfg;
    foreach (explode('.', $key) as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }
    return $cur;
}

function url(string $path = ''): string
{
    $base = rtrim((string)\Wiki\Models\Settings::get('site.base_url', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (basename($dir) === 'admin') $dir = dirname($dir);
        $base = $scheme . '://' . $host . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Build a clean admin URL.
 *
 * adminUrl('users')                          -> /admin/users
 * adminUrl('users', 'update', 5)             -> /admin/users/update/5
 * adminUrl('users', '', '', ['page' => 2])   -> /admin/users?page=2
 * adminUrl()                                 -> /admin
 */
function adminUrl(string $section = '', string $action = '', $id = '', array $query = []): string
{
    $path = 'admin';
    if ($section !== '') $path .= '/' . $section;
    if ($action  !== '') $path .= '/' . $action;
    if ($id !== '' && $id !== null) $path .= '/' . $id;
    if (!empty($query)) {
        $path .= '?' . http_build_query($query);
    }
    return url($path);
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function random_key(int $len = 32): string
{
    return bin2hex(random_bytes($len));
}

/**
 * Return inline SVG markup for the given icon name.
 * Icons are from Lucide (https://lucide.dev), licensed under MIT.
 */
function icon(string $name, string $class = 'w-4 h-4'): string
{
    static $icons = [
        'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'history'       => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'globe'         => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'user'          => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'home'          => '<path d="M3 12 12 3l9 9"/><path d="M9 21V12h6v9"/>',
        'undo'          => '<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6.7L3 13"/>',
        'compare'       => '<path d="M9 3 5 7l4 4"/><path d="M5 7h14"/><path d="m15 21 4-4-4-4"/><path d="M19 17H5"/>',
        'x'             => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'file'          => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'arrow-left'    => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'alert'         => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    ];

    $path = $icons[$name] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
         . 'class="' . e($class) . '">' . $path . '</svg>';
}
