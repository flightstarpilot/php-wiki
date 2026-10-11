<?php
$currentSlug = $group['slug'];
$pageTitle   = \Wiki\Services\I18nService::t('page.diff_title', ['v1' => $v1, 'v2' => $v2]);
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);

$baseUrl    = url('page/' . $group['slug'] . '/' . $page['lang'] . '/diff');
$sideUrl    = $baseUrl . '?v1=' . $v1 . '&v2=' . $v2 . '&mode=side';
$unifiedUrl = $baseUrl . '?v1=' . $v1 . '&v2=' . $v2 . '&mode=unified';
$backUrl    = url('page/' . $group['slug'] . '/' . $page['lang'] . '/history');

ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <a href="<?= e($backUrl) ?>" class="flex items-center gap-1 text-slate-500 hover:text-slate-900">
      <?= icon('arrow-left', 'w-4 h-4') ?><span><?= e($t('page.back_history')) ?></span>
    </a>
    <h1 class="text-xl font-bold flex-1">
      <?= e($t('page.diff_title', ['v1' => $v1, 'v2' => $v2])) ?>
    </h1>
    <div class="flex items-center gap-1 text-sm">
      <a href="<?= e($sideUrl) ?>"
         class="px-3 py-1 rounded <?= $mode === 'side' ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
        <?= e($t('page.side_by_side')) ?>
      </a>
      <a href="<?= e($unifiedUrl) ?>"
         class="px-3 py-1 rounded <?= $mode === 'unified' ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
        <?= e($t('page.unified')) ?>
      </a>
    </div>
  </header>

  <div class="px-6 py-3 border-b bg-slate-50 flex items-center gap-4 text-sm flex-wrap">
    <div class="flex items-center gap-2">
      <span class="font-mono font-medium">v<?= (int)$v1 ?></span>
      <span class="text-slate-600"><?= e($left['title']) ?></span>
      <span class="text-xs text-slate-400"><?= e($left['created_at']) ?></span>
    </div>
    <span class="text-slate-400">&rarr;</span>
    <div class="flex items-center gap-2">
      <span class="font-mono font-medium">v<?= (int)$v2 ?></span>
      <span class="text-slate-600"><?= e($right['title']) ?></span>
      <span class="text-xs text-slate-400"><?= e($right['created_at']) ?></span>
    </div>
    <div class="ml-auto flex items-center gap-3 text-xs font-mono">
      <span class="text-green-700">+<?= (int)$stats['adds'] ?></span>
      <span class="text-red-700">-<?= (int)$stats['dels'] ?></span>
    </div>
  </div>

  <div class="max-h-[calc(100vh-16rem)] overflow-auto">
    <?= $diffHtml ?>
  </div>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';