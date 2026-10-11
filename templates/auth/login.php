<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
$errorKey = match ($error ?? '') {
    'invalid'         => 'auth.error.invalid',
    'csrf'            => 'auth.error.csrf',
    'pending'         => 'auth.error.pending_review',
    'invalid_token'   => 'auth.error.invalid_token',
    'oauth_state'     => 'auth.error.oauth_state',
    'oauth_token'     => 'auth.error.oauth_token',
    'oauth_user'      => 'auth.error.oauth_user',
    'register_disabled' => 'auth.error.register_disabled',
    'account_inactive'=> 'auth.error.account_inactive',
    default           => '',
};
$reset = !empty($_GET['reset']);
$oauth = \Wiki\Auth\GitHubOAuth::enabled();
$canRegister = \Wiki\Models\Settings::getBool('auth.allow_register') || \Wiki\Models\Settings::getBool('auth.invite_only');
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.login')) ?> · <?= e($siteName) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<form method="post" action="<?= e(url('login')) ?>" class="bg-white rounded shadow p-8 w-80 space-y-4">
  <h1 class="text-xl font-bold text-center"><?= e($siteName) ?></h1>
  <div class="text-sm text-slate-500 text-center"><?= e($t('auth.login')) ?></div>

  <?php if ($errorKey): ?>
    <div class="bg-red-50 text-red-600 text-sm rounded p-2"><?= e($t($errorKey)) ?></div>
  <?php endif; ?>
  <?php if ($reset): ?>
    <div class="bg-green-50 text-green-700 text-sm rounded p-2"><?= e($t('auth.reset_success')) ?></div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
  <input type="hidden" name="next" value="<?= e($next ?? '') ?>">

  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.username_or_email')) ?></span>
    <input name="username" class="mt-1 w-full border rounded px-3 py-2" required autofocus>
  </label>
  <label class="block">
    <span class="text-sm text-slate-600"><?= e($t('auth.password')) ?></span>
    <input name="password" type="password" class="mt-1 w-full border rounded px-3 py-2" required>
  </label>

  <button class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700"><?= e($t('auth.login')) ?></button>

  <div class="flex items-center justify-between text-sm">
    <a href="<?= e(url('forgot')) ?>" class="text-slate-500 hover:text-slate-900"><?= e($t('auth.forgot')) ?></a>
    <?php if ($canRegister): ?>
      <a href="<?= e(url('register')) ?>" class="text-blue-600 hover:underline"><?= e($t('auth.register')) ?></a>
    <?php endif; ?>
  </div>

  <?php if ($oauth): ?>
    <div class="border-t pt-4">
      <a href="<?= e(url('auth/github')) ?>"
         class="block text-center border rounded py-2 hover:bg-slate-50">
        <?= e($t('auth.login_github')) ?>
      </a>
    </div>
  <?php endif; ?>

  <div class="text-center text-xs text-slate-400 pt-2">
    <a href="<?= e(url('')) ?>" class="hover:underline"><?= e($t('nav.home')) ?></a>
  </div>
</form>
</body>
</html>