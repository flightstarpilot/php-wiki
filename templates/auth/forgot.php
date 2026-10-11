<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
$sent = $sent ?? false;
$error = $error ?? '';
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.forgot')) ?> · <?= e($siteName) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<form method="post" action="<?= e(url('forgot')) ?>" class="bg-white rounded shadow p-8 w-80 space-y-4">
  <h1 class="text-xl font-bold text-center"><?= e($t('auth.forgot')) ?></h1>

  <?php if ($sent): ?>
    <div class="bg-green-50 text-green-700 text-sm rounded p-2"><?= e($t('auth.forgot_sent')) ?></div>
  <?php endif; ?>
  <?php if ($error === 'csrf'): ?>
    <div class="bg-red-50 text-red-600 text-sm rounded p-2"><?= e($t('auth.error.csrf')) ?></div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.email')) ?></span>
    <input name="email" type="email" required class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('auth.forgot_submit')) ?></button>

  <div class="text-center text-sm text-slate-500">
    <a href="<?= e(url('login')) ?>" class="text-blue-600 hover:underline"><?= e($t('auth.login')) ?></a>
  </div>
</form>
</body>
</html>