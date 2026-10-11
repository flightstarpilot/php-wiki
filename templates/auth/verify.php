<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$siteName = \Wiki\Models\Settings::get('site.name');
$u = \Wiki\Auth\Auth::user();
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t('auth.verify_title')) ?> · <?= e($siteName) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded shadow p-8 w-96 space-y-4 text-center">
  <h1 class="text-xl font-bold"><?= e($t('auth.verify_title')) ?></h1>

  <?php if ($sent): ?>
    <p class="text-sm text-slate-600"><?= e($t('auth.verify_sent')) ?></p>
  <?php elseif ($done): ?>
    <p class="text-sm text-green-600"><?= e($t('auth.verify_done')) ?></p>
    <a href="<?= e(url('')) ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded"><?= e($t('nav.home')) ?></a>
  <?php elseif ($pending): ?>
    <p class="text-sm text-slate-600"><?= e($t('auth.verify_pending')) ?></p>
    <?php if ($u): ?>
      <form method="post" action="<?= e(url('verify/resend')) ?>">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <button class="bg-blue-600 text-white px-4 py-2 rounded"><?= e($t('auth.verify_resend')) ?></button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p class="text-sm text-slate-600"><?= e($t('auth.verify_invalid')) ?></p>
    <a href="<?= e(url('')) ?>" class="text-blue-600 hover:underline"><?= e($t('nav.home')) ?></a>
  <?php endif; ?>
</div>
</body>
</html>