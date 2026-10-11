<?php
declare(strict_types=1);

define('WIKI_ROOT', dirname(__DIR__));
define('WIKI_VERSION', '0.1.0');

spl_autoload_register(function (string $class): void {
    $prefix = 'Wiki\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $file = WIKI_ROOT . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) require $file;
});

require WIKI_ROOT . '/src/Helpers/functions.php';

\Wiki\Auth\Auth::startSession();

$cfgFile = WIKI_ROOT . '/config/config.php';
if (!is_file($cfgFile)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

$cfg = require $cfgFile;

try {
    $db = \Wiki\Database\DatabaseFactory::make($cfg['db']);
} catch (\Throwable $e) {
    http_response_code(500);
    exit('Database connection failed: ' . e($e->getMessage()));
}

\Wiki\Models\Settings::init($db);
\Wiki\Models\User::init($db);
\Wiki\Models\EmailToken::init($db);
\Wiki\Models\Invite::init($db);
\Wiki\Models\TranslationGroup::init($db);
\Wiki\Models\Page::init($db);
\Wiki\Models\Issue::init($db);
\Wiki\Models\Proposal::init($db);
\Wiki\Models\Comment::init($db);
\Wiki\Models\Tag::init($db);
\Wiki\Models\Redirect::init($db);
\Wiki\Models\AuditLog::init($db);
\Wiki\Models\NotFoundLog::init($db);
\Wiki\Services\SearchService::init($db);
\Wiki\Services\AttachmentService::init($db);

$tz = \Wiki\Models\Settings::get('site.timezone', 'UTC');
@date_default_timezone_set((string)$tz);

\Wiki\Services\I18nService::detect();
\Wiki\Services\CacheService::init();

if (random_int(1, 100) === 1) {
    try { \Wiki\Models\EmailToken::purgeExpired(); } catch (\Throwable $e) {}
}

if (\Wiki\Models\Settings::getBool('site.force_https')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $isCli = (PHP_SAPI === 'cli');
    if (!$isHttps && !$isCli) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

if (\Wiki\Models\Settings::getBool('site.maintenance')) {
    $allowIps = array_filter(array_map('trim', explode(',', (string)\Wiki\Models\Settings::get('site.maintenance_ips', ''))));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed  = \Wiki\Auth\Auth::isAdmin() || in_array($clientIp, $allowIps, true);

    if (!$allowed) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['admin.php', 'install.php'], true)) {
            http_response_code(503);
            exit('Site is under maintenance.');
        }
    }
}
