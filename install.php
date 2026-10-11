<?php
declare(strict_types=1);

require __DIR__ . '/src/Bootstrap.php';

if (is_file(WIKI_ROOT . '/config/config.php')) {
    exit('已安装。如需重装，请删除 config/config.php 与数据文件。');
}

$errors = [];
$step   = $_POST['step'] ?? 'form';
$post   = $_POST;

$checks = [
    'PHP >= 8.0'        => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO 扩展'          => extension_loaded('pdo'),
    'PDO MySQL'         => extension_loaded('pdo_mysql'),
    'PDO SQLite'        => extension_loaded('pdo_sqlite'),
    'config/ 可写'      => is_writable(WIKI_ROOT) || @is_writable(WIKI_ROOT . '/config') || @mkdir(WIKI_ROOT . '/config', 0755, true),
    'data/ 可写'        => is_dir(WIKI_ROOT . '/data') ? is_writable(WIKI_ROOT . '/data') : @mkdir(WIKI_ROOT . '/data', 0755, true),
];

if ($step === 'install') {
    foreach ($checks as $label => $ok) {
        if (!$ok) $errors[] = "环境检测失败：$label";
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
        if ($dbCfg['database'] === '') $errors[] = 'MySQL 数据库名必填';
    }

    $adminUser = trim($post['admin_user'] ?? '');
    $adminMail = trim($post['admin_mail'] ?? '');
    $adminPass = (string)($post['admin_pass'] ?? '');
    if ($adminUser === '') $errors[] = '管理员用户名必填';
    if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) $errors[] = '管理员邮箱无效';
    if (strlen($adminPass) < 6) $errors[] = '管理员密码至少 6 位';

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
                $errors[] = '无法写入 config/config.php，请检查目录权限';
            } else {
                @chmod(WIKI_ROOT . '/config/config.php', 0640);
                $step = 'done';
            }
        } catch (\Throwable $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
    if ($errors) $step = 'form';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>安装 Wiki</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="max-w-2xl mx-auto py-12 px-4">
  <h1 class="text-3xl font-bold mb-2">Wiki 安装向导</h1>
  <p class="text-slate-500 mb-8">版本 <?= e(WIKI_VERSION) ?></p>

  <?php if ($step === 'done'): ?>
    <div class="bg-green-50 border border-green-200 rounded p-6">
      <h2 class="text-xl font-bold text-green-800 mb-2">安装完成 🎉</h2>
      <p class="text-green-700 mb-4">请立即删除 <code class="bg-white px-1 rounded">install.php</code>，然后进入后台。</p>
      <a href="admin.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">进入后台</a>
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
      <h2 class="font-bold mb-3">环境检测</h2>
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

      <h2 class="font-bold">数据库</h2>
      <label class="block">
        <span class="text-sm text-slate-600">类型</span>
        <select name="db_driver" id="db_driver" class="mt-1 w-full border rounded px-3 py-2"
                onchange="document.getElementById('mysql_fields').style.display = this.value==='mysql'?'block':'none'">
          <option value="sqlite">SQLite（推荐，零配置）</option>
          <option value="mysql">MySQL</option>
        </select>
      </label>

      <div id="mysql_fields" style="display:none" class="space-y-3 border-t pt-3">
        <label class="block">
          <span class="text-sm text-slate-600">主机</span>
          <input name="db_host" value="127.0.0.1" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600">端口</span>
          <input name="db_port" value="3306" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600">数据库名</span>
          <input name="db_name" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600">用户名</span>
          <input name="db_user" class="mt-1 w-full border rounded px-3 py-2">
        </label>
        <label class="block">
          <span class="text-sm text-slate-600">密码</span>
          <input name="db_pass" type="password" class="mt-1 w-full border rounded px-3 py-2">
        </label>
      </div>

      <h2 class="font-bold pt-4">管理员账号</h2>
      <label class="block">
        <span class="text-sm text-slate-600">用户名</span>
        <input name="admin_user" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>
      <label class="block">
        <span class="text-sm text-slate-600">邮箱</span>
        <input name="admin_mail" type="email" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>
      <label class="block">
        <span class="text-sm text-slate-600">密码（≥6位）</span>
        <input name="admin_pass" type="password" class="mt-1 w-full border rounded px-3 py-2" required>
      </label>

      <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">开始安装</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>