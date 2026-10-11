<?php
/** @var string $action */
/** @var string $pageTitle */
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$menu = [
    'dashboard'      => $t('admin.menu.dashboard'),
    'pages'          => $t('admin.menu.pages'),
    'users'          => $t('admin.menu.users'),
    'invites'        => $t('admin.menu.invites'),
    'attachments'    => $t('admin.menu.attachments'),
    'redirects'      => $t('admin.menu.redirects'),
    'not_found'      => $t('admin.menu.not_found'),
    'audit'          => $t('admin.menu.audit'),
    'mail_templates' => $t('admin.menu.mail_templates'),
    'storage'        => $t('admin.menu.storage'),
    'settings'       => $t('admin.menu.settings'),
];
?>
<!doctype html>
<html lang="<?= e(\Wiki\Services\I18nService::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(\Wiki\Models\Settings::get('site.name')) ?></title>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
</head>
<body class="bg-slate-100 min-h-screen">
<div class="flex min-h-screen">
  <aside class="w-56 bg-slate-900 text-slate-300 flex-shrink-0 flex flex-col">
    <div class="p-4 border-b border-slate-700">
      <a href="<?= e(url('')) ?>" class="font-bold text-white flex items-center gap-2">
        <?= icon('arrow-left', 'w-4 h-4') ?><span><?= e(\Wiki\Models\Settings::get('site.name')) ?></span>
      </a>
    </div>
    <nav class="p-2 space-y-1 text-sm flex-1">
      <?php foreach ($menu as $k => $label):
          $active = $action === $k;
      ?>
        <a href="<?= e(adminUrl($k)) ?>"
           class="block px-3 py-2 rounded <?= $active ? 'bg-blue-600 text-white' : 'hover:bg-slate-800' ?>">
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-4 text-xs border-t border-slate-700">
      <div class="text-slate-500 mb-2">UI: <?= e(\Wiki\Services\I18nService::current()) ?></div>
      <a href="<?= e(adminUrl('logout')) ?>" class="text-slate-400 hover:text-white flex items-center gap-1">
        <?= icon('logout', 'w-3 h-3') ?><span><?= e($t('nav.logout')) ?></span>
      </a>
    </div>
  </aside>

  <main class="flex-1 p-8">
    <h1 class="text-2xl font-bold mb-6"><?= e($pageTitle) ?></h1>
    <?php require WIKI_ROOT . "/templates/admin/{$action}.php"; ?>
  </main>
</div>
</body>
</html>
