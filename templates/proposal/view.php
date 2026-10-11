<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = $group['slug'] ?? '';
$pageUrl     = url('page/' . ($group['slug'] ?? '') . ($page['lang'] !== (string)\Wiki\Models\Settings::get('site.default_lang') ? '/' . $page['lang'] : ''));
$showConflict = !empty($_GET['conflict']);
$mode = ($_GET['mode'] ?? 'side') === 'unified' ? 'unified' : 'side';
$diffHtml = $mode === 'unified'
    ? \Wiki\Services\DiffService::renderUnified($diff)
    : \Wiki\Services\DiffService::renderSideBySide($diff);

ob_start();
?>
<article class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b">
    <div class="flex items-start gap-3 flex-wrap">
      <span class="text-slate-400"><?= icon('compare', 'w-5 h-5') ?></span>
      <h1 class="text-xl font-bold flex-1">
        <?= e($proposal['title']) ?>
        <span class="text-slate-400 font-normal">#<?= (int)$proposal['id'] ?></span>
      </h1>
      <span class="text-xs px-2 py-1 rounded
        <?= $proposal['status'] === 'open' ? 'bg-green-100 text-green-700' : '' ?>
        <?= $proposal['status'] === 'merged' ? 'bg-purple-100 text-purple-700' : '' ?>
        <?= $proposal['status'] === 'closed' ? 'bg-slate-100 text-slate-500' : '' ?>">
        <?= e($t('proposal.status.' . $proposal['status'])) ?>
      </span>
    </div>
    <div class="mt-2 text-xs text-slate-400 flex items-center gap-3 flex-wrap">
      <span><?= e($t('proposal.by', ['user' => $proposal['author'] ?? '-'])) ?></span>
      <span class="flex items-center gap-1"><?= icon('clock', 'w-3 h-3') ?><?= e($proposal['created_at']) ?></span>
      <a href="<?= e($pageUrl) ?>" class="text-blue-600 hover:underline flex items-center gap-1">
        <?= icon('file', 'w-3 h-3') ?><?= e($group['slug'] ?? '') ?>
      </a>
      <span><?= e($t('proposal.base_version', ['v' => (int)$proposal['base_version']])) ?></span>
    </div>
  </header>

  <?php if ($showConflict || $hasConflict): ?>
    <div class="bg-amber-50 text-amber-800 text-sm px-6 py-3 border-b flex items-center gap-2">
      <?= icon('alert', 'w-4 h-4') ?>
      <span><?= e($t('proposal.conflict_notice', ['base' => (int)$proposal['base_version'], 'current' => (int)$page['current_version']])) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($proposal['description'])): ?>
    <div class="px-6 py-4 border-b whitespace-pre-wrap text-sm text-slate-700"><?= e($proposal['description']) ?></div>
  <?php endif; ?>

  <div class="px-6 py-3 border-b bg-slate-50 flex items-center gap-2 text-sm">
    <a href="?mode=side" class="px-3 py-1 rounded <?= $mode === 'side' ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
      <?= e($t('page.side_by_side')) ?>
    </a>
    <a href="?mode=unified" class="px-3 py-1 rounded <?= $mode === 'unified' ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
      <?= e($t('page.unified')) ?>
    </a>
    <div class="ml-auto flex items-center gap-3 text-xs font-mono">
      <span class="text-green-700">+<?= (int)$stats['adds'] ?></span>
      <span class="text-red-700">-<?= (int)$stats['dels'] ?></span>
    </div>
  </div>

  <div class="max-h-[600px] overflow-auto"><?= $diffHtml ?></div>

  <div class="px-6 py-4 border-t flex items-center gap-2 flex-wrap">
    <?php if ($proposal['status'] === 'open' && $canReview): ?>
      <form method="post" action="<?= e(url('proposal/' . (int)$proposal['id'] . '/merge')) ?>" class="inline"
            onsubmit="return confirm('<?= e($t('proposal.confirm_merge')) ?>')">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <?php if ($hasConflict): ?>
          <input type="hidden" name="force" value="1">
        <?php endif; ?>
        <button class="text-sm px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700">
          <?= e($hasConflict ? $t('proposal.force_merge') : $t('proposal.merge')) ?>
        </button>
      </form>
    <?php endif; ?>

    <?php if ($proposal['status'] === 'open' && $canManage): ?>
      <form method="post" action="<?= e(url('proposal/' . (int)$proposal['id'] . '/close')) ?>" class="inline">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <button class="text-sm px-3 py-1.5 rounded border text-slate-600 hover:bg-slate-50">
          <?= e($t('proposal.close')) ?>
        </button>
      </form>
    <?php elseif ($proposal['status'] !== 'open' && $canManage): ?>
      <form method="post" action="<?= e(url('proposal/' . (int)$proposal['id'] . '/reopen')) ?>" class="inline">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <button class="text-sm px-3 py-1.5 rounded border text-slate-600 hover:bg-slate-50">
          <?= e($t('proposal.reopen')) ?>
        </button>
      </form>
    <?php endif; ?>

    <?php if ($proposal['status'] === 'merged' && !empty($proposal['merged_version'])): ?>
      <div class="text-sm text-purple-700 flex items-center gap-1">
        <?= icon('file', 'w-4 h-4') ?>
        <span><?= e($t('proposal.merged_as', ['v' => (int)$proposal['merged_version']])) ?></span>
      </div>
    <?php endif; ?>

    <?php if (\Wiki\Auth\Auth::isAdmin()): ?>
      <form method="post" action="<?= e(url('proposal/' . (int)$proposal['id'] . '/delete')) ?>" class="inline ml-auto"
            onsubmit="return confirm('<?= e($t('proposal.confirm_delete')) ?>')">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <button class="text-sm px-3 py-1.5 rounded text-red-600 hover:bg-red-50">
          <?= e($t('proposal.delete')) ?>
        </button>
      </form>
    <?php endif; ?>
  </div>
</article>

<?php
$commentAction = url('proposal/' . (int)$proposal['id'] . '/comment');
$canComment    = \Wiki\Auth\Auth::check();
require WIKI_ROOT . '/templates/partials/comments.php';
?>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';