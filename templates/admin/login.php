<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.login')) ?> · <?= e($siteName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<form method="post" action="<?= e(url('admin/login')) ?>" class="bg-white rounded shadow p-8 w-80 space-y-4">
  <h1 class="text-xl font-bold text-center"><?= e($siteName) ?></h1>
  <?php if (!empty($loginError)): ?>
    <div class="bg-red-50 text-red-600 text-sm rounded p-2"><?= e($loginError) ?></div>
  <?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.username_or_email')) ?></span>
    <input name="username" class="mt-1 w-full border rounded px-3 py-2" required autofocus>
  </label>
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.password')) ?></span>
    <input name="password" type="password" class="mt-1 w-full border rounded px-3 py-2" required>
  </label>
  <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('auth.login')) ?></button>
</form>
</body>
</html>
