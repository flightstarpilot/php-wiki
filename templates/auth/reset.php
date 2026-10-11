<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
$error = $error ?? '';
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.reset_title')) ?> · <?= e($siteName) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<form method="post" action="<?= e(url('reset')) ?>" class="bg-white rounded shadow p-8 w-80 space-y-4">
  <h1 class="text-xl font-bold text-center"><?= e($t('auth.reset_title')) ?></h1>

  <?php if ($error === 'weak'): ?>
    <div class="bg-red-50 text-red-600 text-sm rounded p-2"><?= e($t('auth.error.weak_password')) ?></div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
  <input type="hidden" name="token" value="<?= e($token) ?>">

  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.new_password')) ?></span>
    <input name="password" type="password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2">
  </label>
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.confirm_password')) ?></span>
    <input name="password2" type="password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2">
  </label>

  <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('auth.reset_submit')) ?></button>
</form>
</body>
</html>