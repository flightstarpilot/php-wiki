<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
$inviteOnly = \Wiki\Models\Settings::getBool('auth.invite_only');
$oauth = \Wiki\Auth\GitHubOAuth::enabled();
$flash = $_SESSION['flash_register'] ?? [];
unset($_SESSION['flash_register']);
$data = $_SESSION['flash_register_data'] ?? [];
unset($_SESSION['flash_register_data']);
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.register')) ?> · <?= e($siteName) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<form method="post" action="<?= e(url('register')) ?>" class="bg-white rounded shadow p-8 w-96 space-y-4">
  <h1 class="text-xl font-bold text-center"><?= e($t('auth.register')) ?></h1>

  <?php if ($flash): ?>
    <div class="bg-red-50 text-red-600 text-sm rounded p-2 space-y-1">
      <?php foreach ($flash as $code): ?>
        <div><?= e($t('auth.error.' . $code)) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

  <?php if ($inviteOnly): ?>
    <label class="block">
      <span class="text-sm text-slate-600"><?= e($t('auth.invite_code')) ?></span>
      <input name="invite" value="<?= e($data['invite'] ?? '') ?>" required
             class="mt-1 w-full border rounded px-3 py-2 font-mono">
    </label>
  <?php endif; ?>

  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.username')) ?></span>
    <input name="username" value="<?= e($data['username'] ?? '') ?>" required
           pattern="[A-Za-z0-9_\-\.]{3,64}"
           class="mt-1 w-full border rounded px-3 py-2">
    <span class="text-xs text-slate-400"><?= e($t('auth.username_hint')) ?></span>
  </label>
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.email')) ?></span>
    <input name="email" type="email" value="<?= e($data['email'] ?? '') ?>" required
           class="mt-1 w-full border rounded px-3 py-2">
  </label>
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.password')) ?></span>
    <input name="password" type="password" required minlength="6"
           class="mt-1 w-full border rounded px-3 py-2">
    <span class="text-xs text-slate-400"><?= e($t('auth.password_hint')) ?></span>
  </label>

  <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('auth.register')) ?></button>

  <?php if ($oauth): ?>
    <div class="border-t pt-4">
      <a href="<?= e(url('auth/github')) ?>" class="block text-center border rounded py-2 hover:bg-slate-50">
        <?= e($t('auth.login_github')) ?>
      </a>
    </div>
  <?php endif; ?>

  <div class="text-center text-sm text-slate-500">
    <?= e($t('auth.have_account')) ?>
    <a href="<?= e(url('login')) ?>" class="text-blue-600 hover:underline"><?= e($t('auth.login')) ?></a>
  </div>
</form>
</body>
</html>