<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$cfg = require WIKI_ROOT . '/config/config.php';
$driver = (string)($cfg['db']['driver'] ?? 'sqlite');

$counts = [
    'admin.dashboard.users'   => (int)\Wiki\Models\User::count(),
    'admin.dashboard.groups'  => (int)\Wiki\Models\TranslationGroup::count(),
    'admin.dashboard.pages'   => (int)\Wiki\Models\Page::countAll(),
    'admin.dashboard.issues'  => (int)\Wiki\Models\Issue::countOpen(),
    'admin.dashboard.proposals' => (int)\Wiki\Models\Proposal::countOpen(),
];
?>
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
  <?php foreach ($counts as $labelKey => $n): ?>
    <div class="bg-white rounded shadow p-4">
      <div class="text-2xl font-bold"><?= (int)$n ?></div>
      <div class="text-sm text-slate-500"><?= e($t($labelKey)) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="bg-white rounded shadow p-6 text-sm text-slate-600 space-y-1">
  <div><?= e($t('admin.dashboard.php_version')) ?>: <?= e(PHP_VERSION) ?></div>
  <div><?= e($t('admin.dashboard.db_driver')) ?>: <?= e($driver) ?></div>
  <div><?= e($t('admin.dashboard.timezone')) ?>: <?= e(\Wiki\Models\Settings::get('site.timezone')) ?></div>
  <div><?= e($t('admin.dashboard.current_user')) ?>: <?= e(\Wiki\Auth\Auth::user()['username']) ?></div>
</div>
