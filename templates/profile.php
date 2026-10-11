<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
$pageTitle = $t('profile.title');

$flash = $_SESSION['flash_profile'] ?? null;
unset($_SESSION['flash_profile']);
$tab = $_GET['tab'] ?? 'account';

ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3">
    <h1 class="text-xl font-bold flex-1"><?= e($t('profile.title')) ?></h1>
  </header>

  <div class="px-6 py-2 border-b bg-slate-50 flex items-center gap-2 text-sm">
    <?php foreach (['account' => 'profile.tab.account', 'password' => 'profile.tab.password'] as $k => $lk): ?>
      <a href="<?= e(url('profile?tab=' . $k)) ?>"
         class="px-3 py-1 rounded <?= $tab === $k ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
        <?= e($t($lk)) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($flash): ?>
    <div class="px-6 py-3 border-b text-sm <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
      <?= e($flash['text']) ?>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'password'): ?>
    <form method="post" action="<?= e(url('profile/password')) ?>" class="px-6 py-6 space-y-4 max-w-md">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('profile.current_password')) ?></span>
        <input name="current" type="password" required class="mt-1 w-full border rounded px-3 py-2">
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('auth.new_password')) ?></span>
        <input name="password" type="password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2">
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('auth.confirm_password')) ?></span>
        <input name="password2" type="password" required minlength="6" class="mt-1 w-full border rounded px-3 py-2">
      </label>

      <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        <?= e($t('profile.update_password')) ?>
      </button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('profile')) ?>" class="px-6 py-6 space-y-4 max-w-md">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('auth.username')) ?></span>
        <input value="<?= e($user['username']) ?>" disabled class="mt-1 w-full border rounded px-3 py-2 bg-slate-50 text-slate-500">
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('profile.display_name')) ?></span>
        <input name="display_name" value="<?= e($user['display_name']) ?>" class="mt-1 w-full border rounded px-3 py-2">
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('auth.email')) ?></span>
        <input name="email" type="email" value="<?= e($user['email']) ?>" required class="mt-1 w-full border rounded px-3 py-2">
      </label>
      <label class="block">
        <span class="text-sm text-slate-600"><?= e($t('profile.avatar_url')) ?></span>
        <input name="avatar_url" value="<?= e($user['avatar_url']) ?>" class="mt-1 w-full border rounded px-3 py-2">
      </label>

      <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        <?= e($t('profile.save')) ?>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';
