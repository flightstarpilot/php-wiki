<?php
declare(strict_types=1);

require __DIR__ . '/src/Bootstrap.php';

if (!isset($_GET['lang']) || !is_string($_GET['lang']) || !\Wiki\Services\I18nService::set($_GET['lang'])) {
    \Wiki\Services\I18nService::detect();
}
$t = static fn(string $key, array $params = []): string => \Wiki\Services\I18nService::t($key, $params);
$languageNames = [
    'en-US' => 'English',
    'zh-CN' => '简体中文',
    'ja-JP' => '日本語',
    'ko-KR' => '한국어',
    'fr-FR' => 'Français',
    'de-DE' => 'Deutsch',
    'es-ES' => 'Español',
    'ru-RU' => 'Русский',
];

if (is_file(WIKI_ROOT . '/config/config.php')) {
    exit($t('install.already_installed'));
}

$errors = [];
$step   = $_POST['step'] ?? 'form';
$post   = $_POST;

$checks = [
    $t('install.check.php')          => version_compare(PHP_VERSION, '8.0.0', '>='),
    $t('install.check.pdo')          => extension_loaded('pdo'),
    $t('install.check.pdo_mysql')    => extension_loaded('pdo_mysql'),
    $t('install.check.pdo_sqlite')   => extension_loaded('pdo_sqlite'),
    $t('install.check.config')       => is_writable(WIKI_ROOT) || @is_writable(WIKI_ROOT . '/config') || @mkdir(WIKI_ROOT . '/config', 0755, true),
    $t('install.check.data')         => is_dir(WIKI_ROOT . '/data') ? is_writable(WIKI_ROOT . '/data') : @mkdir(WIKI_ROOT . '/data', 0755, true),
];

if ($step === 'install') {
    foreach ($checks as $label => $ok) {
        if (!$ok) $errors[] = $t('install.error.environment', ['label' => $label]);
    }

    $driver = $post['db_driver'] ?? 'sqlite';
    $dbCfg  = ['driver' => $driver];

    if ($driver === 'sqlite') {
        $dbCfg['path'] = WIKI_ROOT . '/data/wiki.sqlite';
    } else {
        $dbCfg['host']     = trim($post['db_host'] ?? '127.0.0.1');
        $dbCfg['port']     = (int)($post['db_port'] ?? 3306);
        $dbCfg['database'] = trim($post['db_name'] ?? '');
        $dbCfg['username'] = trim($post['db_user'] ?? '');
        $dbCfg['password'] = (string)($post['db_pass'] ?? '');
        if ($dbCfg['database'] === '') $errors[] = $t('install.error.database_required');
    }

    $adminUser = trim($post['admin_user'] ?? '');
    $adminMail = trim($post['admin_mail'] ?? '');
    $adminPass = (string)($post['admin_pass'] ?? '');
    if ($adminUser === '') $errors[] = $t('install.error.username_required');
    if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) $errors[] = $t('install.error.email_invalid');
    if (strlen($adminPass) < 6) $errors[] = $t('install.error.password_short');

    if (!$errors) {
        try {
            $db = new \Wiki\Database\PdoDatabase($dbCfg);
            \Wiki\Database\Schema::install($db);
            \Wiki\Models\Settings::init($db);
            \Wiki\Models\User::init($db);
            \Wiki\Models\Settings::seedDefaults();

            $adminId = null;
            if (\Wiki\Models\User::count() === 0) {
                $adminId = \Wiki\Models\User::create([
                    'username'      => $adminUser,
                    'email'         => $adminMail,
                    'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                    'display_name'  => $adminUser,
                    'role'          => 'admin',
                ]);
            }

            // Initialize Models needed to seed the home page
            \Wiki\Models\TranslationGroup::init($db);
            \Wiki\Models\Page::init($db);

            // Create a starter home page so the front page is not empty
            $homeSlug = 'home';
            if (!\Wiki\Models\TranslationGroup::findBySlug($homeSlug) && $adminId) {
                $defaultLang = (string)\Wiki\Models\Settings::get('site.default_lang', 'zh-CN');
                $groupId = \Wiki\Models\TranslationGroup::create($homeSlug, $defaultLang);

                $welcome = "# " . \Wiki\Models\Settings::get('site.name', 'Wiki') . "\n\n"
                         . "Welcome. This is your home page.\n\n"
                         . "Edit it, or create additional pages from the **New page** button.\n\n"
                         . "## Getting started\n\n"
                         . "- Use `[[slug]]` to link to another page\n"
                         . "- Every save creates a version you can diff and roll back to\n"
                         . "- Open an issue or propose an edit from any page footer\n";

                \Wiki\Models\Page::create($groupId, $defaultLang, 'Home', $welcome, (int)$adminId, 'Initial home page');
            }

            // 写配置
            $appKey = bin2hex(random_bytes(32));
            $cfg = [
                'db'  => $dbCfg,
                'app' => [
                    'key'       => $appKey,
                    'installed' => true,
                    'version'   => WIKI_VERSION,
                    'installed_at' => gmdate('c'),
                ],
            ];
            $php = "<?php\n// 由 install.php 自动生成 " . date('c') . "\nreturn " . var_export($cfg, true) . ";\n";
            if (!is_dir(WIKI_ROOT . '/config')) @mkdir(WIKI_ROOT . '/config', 0755, true);
            if (file_put_contents(WIKI_ROOT . '/config/config.php', $php) === false) {
                $errors[] = $t('install.error.write_config');
            } else {
                @chmod(WIKI_ROOT . '/config/config.php', 0640);
                $step = 'done';
            }
        } catch (\Throwable $e) {
            $errors[] = $t('install.error.failed', ['message' => $e->getMessage()]);
        }
    }
    if ($errors) $step = 'form';
}
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('install.title')) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="max-w-2xl mx-auto py-12 px-4">
  <div class="flex items-center justify-between gap-4 mb-2">
    <h1 class="text-3xl font-bold"><?= e($t('install.heading')) ?></h1>
    <label class="text-sm text-slate-600">
      <span class="sr-only"><?= e($t('install.language')) ?></span>
      <select class="border rounded px-2 py-1" onchange="location.href='?lang='+encodeURIComponent(this.value)">
        <?php foreach ($languageNames as $code => $name): ?>
          <option value="<?= e($code) ?>" <?= \Wiki\Services\I18nService::current() === $code ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p class="text-slate-500 mb-8"><?= e($t('install.version', ['version' => WIKI_VERSION])) ?></p>

  <?php if ($step === 'done'): ?>
    <div class="bg-green-50 border border-green-200 rounded p-6">
      <h2 class="text-xl font-bold text-green-800 mb-2"><?= e($t('install.complete')) ?></h2>
      <p class="text-green-700 mb-4"><?= e($t('install.delete_installer')) ?></p>
      <a href="admin.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= e($t('install.admin_link')) ?></a>
    </div>
  <?php else: ?>

    <?php if ($errors): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6">
        <ul class="list-disc list-inside">
          <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded shadow p-6 mb-6">
      <h2 class="font-bold mb-3"><?= e($t('install.checks')) ?></h2>
      <ul class="space-y-1 text-sm">
        <?php foreach ($checks as $label => $ok): ?>
          <li class="<?= $ok ? 'text-green-700' : 'text-red-600' ?>">
            <?= $ok ? '✓' : '✗' ?> <?= e($label) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <form method="post" class="bg-white rounded shadow p-6 space-y-4">
      <input type="hidden" name="step" value="install">

      <h2 class="font-bold"><?= e($t('install.database')) ?></h2>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('install.database_type')) ?></span>
        <select name="db_driver" id="db_driver" class="mt-1 w-full border rounded px-3 py-2"
                onchange="document.getElementById('mysql_fields').style.display = this.value==='mysql'?'block':'none'">
          <option value="sqlite"><?= e($t('install.sqlite')) ?></option>
          <option value="mysql"><?= e($t('install.mysql')) ?></option>
        </select>
      </label>

      <div id="mysql_fields" style="display:none" class="space-y-3 border-t pt-3">
        <label class="block">
          <span class="text-sm text-slate-600"><?= e($t('install.host')) ?></span>
          <input name="db_host" value="127.0.0.1" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600"><?= e($t('install.port')) ?></span>
          <input name="db_port" value="3306" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600"><?= e($t('install.database_name')) ?></span>
          <input name="db_name" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600"><?= e($t('install.username')) ?></span>
          <input name="db_user" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600"><?= e($t('install.password')) ?></span>
          <input name="db_pass" type="password" class="mt-1 w-full border rounded px-3 py-2">
        </label>
      </div>

      <h2 class="font-bold pt-4"><?= e($t('install.admin_account')) ?></h2>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('install.username')) ?></span>
        <input name="admin_user" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('install.email')) ?></span>
        <input name="admin_mail" type="email" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('install.password_min')) ?></span>
        <input name="admin_pass" type="password" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>

      <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('install.start')) ?></button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>